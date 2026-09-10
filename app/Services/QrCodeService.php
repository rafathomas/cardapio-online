<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Establishment;
use App\Models\QrCode as QrCodeModel;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * QR Code do cardapio publico.
 *
 * A imagem e materializada no disco publico para poder ser baixada e impressa.
 * Regenerar cria uma nova versao e substitui o arquivo anterior.
 */
class QrCodeService
{
    public function forEstablishment(Establishment $establishment): QrCodeModel
    {
        $qrCode = $establishment->qrCode()->first();

        if ($qrCode && $qrCode->target_url === $establishment->publicUrl() && $qrCode->path
            && Storage::disk('public')->exists($qrCode->path)) {
            return $qrCode;
        }

        return $this->generate($establishment);
    }

    public function generate(Establishment $establishment, bool $bumpVersion = false): QrCodeModel
    {
        $qrCode = $establishment->qrCode()->first();
        $targetUrl = $establishment->publicUrl();

        $version = $qrCode ? ($bumpVersion ? $qrCode->version + 1 : $qrCode->version) : 1;

        $path = "establishments/{$establishment->id}/qrcode/menu-v{$version}.png";

        if ($qrCode?->path && $qrCode->path !== $path) {
            Storage::disk('public')->delete($qrCode->path);
        }

        Storage::disk('public')->put($path, $this->pngContents($targetUrl));

        if ($qrCode) {
            $qrCode->update([
                'target_url' => $targetUrl,
                'path' => $path,
                'version' => $version,
            ]);

            return $qrCode->refresh();
        }

        return $establishment->qrCode()->create([
            'token' => Str::lower(Str::random(24)),
            'target_url' => $targetUrl,
            'path' => $path,
            'version' => $version,
        ]);
    }

    public function regenerate(Establishment $establishment): QrCodeModel
    {
        return $this->generate($establishment, bumpVersion: true);
    }

    public function pngContents(string $url, int $size = 640): string
    {
        return $this->build($url, $size, new PngWriter);
    }

    public function svgContents(string $url, int $size = 640): string
    {
        return $this->build($url, $size, new SvgWriter);
    }

    private function build(string $url, int $size, object $writer): string
    {
        return (new Builder(
            writer: $writer,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getString();
    }
}
