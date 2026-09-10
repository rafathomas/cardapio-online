<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Upload de imagens com redimensionamento e recompressao.
 *
 * O cardapio publico e mobile-first: imagens gigantes sao o maior custo de
 * carregamento, entao tudo e normalizado para WebP no envio.
 */
class ImageUploadService
{
    public function storeProductImage(UploadedFile $file, int $establishmentId): string
    {
        return $this->store($file, "establishments/{$establishmentId}/products", (int) config('cardapio.uploads.image_max_width'));
    }

    public function storeLogo(UploadedFile $file, int $establishmentId): string
    {
        return $this->store($file, "establishments/{$establishmentId}/brand", (int) config('cardapio.uploads.logo_max_width'));
    }

    public function storeCover(UploadedFile $file, int $establishmentId): string
    {
        return $this->store($file, "establishments/{$establishmentId}/brand", (int) config('cardapio.uploads.image_max_width'));
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function store(UploadedFile $file, string $directory, int $maxWidth): string
    {
        $filename = Str::uuid()->toString().'.webp';
        $path = "{$directory}/{$filename}";

        $image = Image::decodePath($file->getRealPath());

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $encoded = $image->encode(
            new WebpEncoder(quality: (int) config('cardapio.uploads.quality', 82)),
        );

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }
}
