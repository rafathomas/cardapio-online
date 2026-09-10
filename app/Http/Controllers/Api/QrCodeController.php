<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QrCodeResource;
use App\Models\Establishment;
use App\Services\AuditLogger;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        return response()->json([
            'data' => new QrCodeResource($this->qrCodes->forEstablishment($establishment)),
        ]);
    }

    public function regenerate(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('update', $establishment);

        $qrCode = $this->qrCodes->regenerate($establishment);

        $this->audit->log('qrcode.regenerated', $qrCode, $request->user(), $establishment);

        return response()->json([
            'data' => new QrCodeResource($qrCode),
            'message' => 'QR Code gerado novamente.',
        ]);
    }

    /** Download direto em PNG ou SVG, pronto para impressao. */
    public function download(Request $request, Establishment $establishment): Response
    {
        $this->authorize('view', $establishment);

        $format = $request->string('format')->lower()->value() === 'svg' ? 'svg' : 'png';
        $url = $establishment->publicUrl();

        [$contents, $mime] = $format === 'svg'
            ? [$this->qrCodes->svgContents($url), 'image/svg+xml']
            : [$this->qrCodes->pngContents($url, 1024), 'image/png'];

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="qrcode-'.$establishment->slug.'.'.$format.'"',
        ]);
    }
}
