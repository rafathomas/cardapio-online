<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Establishment;
use App\Models\Product;
use App\Services\Plans\PlanGate;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Importa produtos em lote a partir de uma planilha (CSV ou XLSX).
 *
 * Cada linha e tratada de forma independente: uma linha invalida vira um
 * erro reportado ao usuario, mas nao interrompe as demais. O limite do
 * plano e respeitado como se cada produto fosse criado um a um pela tela.
 */
class ProductImportService
{
    /** Cabecalhos aceitos (em portugues) mapeados para as chaves internas. */
    private const COLUMN_ALIASES = [
        'nome' => 'name',
        'produto' => 'name',
        'descricao' => 'description',
        'descrição' => 'description',
        'preco' => 'price',
        'preço' => 'price',
        'valor' => 'price',
        'preco_promocional' => 'promo_price',
        'preço_promocional' => 'promo_price',
        'promocao' => 'promo_price',
        'promoção' => 'promo_price',
        'categoria' => 'category',
        'ativo' => 'is_active',
        'disponivel' => 'is_active',
        'disponível' => 'is_active',
    ];

    public function __construct(
        private readonly PlanGate $plans,
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * @return array{created: int, errors: array<int, array{row: int, message: string}>}
     */
    public function import(Establishment $establishment, UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            return ['created' => 0, 'errors' => [['row' => 1, 'message' => 'A planilha está vazia.']]];
        }

        $header = array_shift($rows);
        $columns = $this->mapHeader($header);

        if (! isset($columns['name']) || ! isset($columns['price'])) {
            return [
                'created' => 0,
                'errors' => [['row' => 1, 'message' => 'A planilha precisa ter as colunas "nome" e "preco".']],
            ];
        }

        $created = 0;
        $errors = [];
        $categoryCache = [];
        $remaining = $this->plans->remainingProducts($establishment);
        $remainingCategories = $this->plans->remainingCategories($establishment);
        $nextSortOrder = ((int) $establishment->products()->max('sort_order')) + 1;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 pelo cabecalho, +1 porque planilhas comecam em 1.

            if ($this->isBlankRow($row)) {
                continue;
            }

            if ($remaining !== null && $remaining <= 0) {
                $errors[] = ['row' => $rowNumber, 'message' => 'Limite de produtos do plano atingido. Linha não importada.'];
                continue;
            }

            try {
                $this->createProduct($establishment, $columns, $row, $categoryCache, $nextSortOrder, $remainingCategories);
                $created++;
                $nextSortOrder++;

                if ($remaining !== null) {
                    $remaining--;
                }
            } catch (Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /** @return array<int, array<int, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, false, false);
    }

    /** @param array<int, mixed> $header @return array<string, int> */
    private function mapHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $position => $label) {
            $key = self::COLUMN_ALIASES[$this->normalizeKey((string) $label)] ?? null;

            if ($key !== null) {
                $columns[$key] = $position;
            }
        }

        return $columns;
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(' ', '_', $value);
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int> $columns @param array<int, mixed> $row @param array<string, Category> $categoryCache */
    private function createProduct(
        Establishment $establishment,
        array $columns,
        array $row,
        array &$categoryCache,
        int $sortOrder,
        ?int &$remainingCategories,
    ): void {
        $name = trim((string) ($row[$columns['name']] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Nome do produto é obrigatório.');
        }

        $priceCents = $this->parsePrice($row[$columns['price']] ?? null);

        if ($priceCents === null) {
            throw new InvalidArgumentException('Preço inválido ou ausente.');
        }

        $promoCents = isset($columns['promo_price'])
            ? $this->parsePrice($row[$columns['promo_price']] ?? null)
            : null;

        if ($promoCents !== null && $promoCents >= $priceCents) {
            $promoCents = null;
        }

        $categoryId = isset($columns['category'])
            ? $this->resolveCategory($establishment, (string) ($row[$columns['category']] ?? ''), $categoryCache, $remainingCategories)
            : null;

        $isActive = isset($columns['is_active'])
            ? $this->parseBoolean($row[$columns['is_active']] ?? null)
            : true;

        DB::transaction(function () use ($establishment, $name, $priceCents, $promoCents, $categoryId, $isActive, $sortOrder): void {
            $establishment->products()->create([
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => $this->slugs->unique(
                    $name,
                    fn (string $candidate) => $establishment->products()->withTrashed()->where('slug', $candidate),
                ),
                'price_cents' => $priceCents,
                'promo_price_cents' => $promoCents,
                'is_active' => $isActive,
                'is_featured' => false,
                'sort_order' => $sortOrder,
            ]);
        });
    }

    private function parsePrice(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Money::fromDecimal((string) $value)->cents;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function parseBoolean(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));

        return ! in_array($normalized, ['nao', 'não', 'no', '0', 'false', 'inativo'], true);
    }

    /** @param array<string, Category> $categoryCache */
    private function resolveCategory(
        Establishment $establishment,
        string $name,
        array &$categoryCache,
        ?int &$remainingCategories,
    ): ?int {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $key = mb_strtolower($name);

        if (isset($categoryCache[$key])) {
            return $categoryCache[$key]->id;
        }

        $category = $establishment->categories()->whereRaw('LOWER(name) = ?', [$key])->first();

        if (! $category) {
            // Sem categoria nova disponivel no plano: o produto entra sem categoria.
            if ($remainingCategories !== null && $remainingCategories <= 0) {
                return null;
            }

            $category = $establishment->categories()->create([
                'name' => $name,
                'slug' => $this->slugs->unique(
                    $name,
                    fn (string $candidate) => $establishment->categories()->where('slug', $candidate),
                ),
                'sort_order' => ((int) $establishment->categories()->max('sort_order')) + 1,
            ]);

            if ($remainingCategories !== null) {
                $remainingCategories--;
            }
        }

        $categoryCache[$key] = $category;

        return $category->id;
    }
}
