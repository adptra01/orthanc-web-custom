<?php

namespace App\Http\Controllers\Orthanc;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDicomRequest;
use App\Services\Orthanc\OrthancService;
use App\Services\OrthancAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk upload DICOM ke Orthanc via endpoint `/instances`.
 *
 * Mengirim body sebagai `application/dicom` raw stream (body mentah file).
 */
class DicomUploadController extends Controller
{
    public function __construct(
        protected OrthancService $orthanc,
    ) {}

    public function store(UploadDicomRequest $request): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null) {
            return response()->json(['message' => 'File missing.'], 422);
        }

        $filename = $file->getClientOriginalName();
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            return response()->json(['message' => 'Gagal membaca file upload.'], 500);
        }

        $baseUrl = rtrim((string) config('orthanc.base_url'), '/');
        $username = (string) config('orthanc.username');
        $password = (string) config('orthanc.password');
        $timeout = (int) config('orthanc.timeout', 30);
        $verifySsl = (bool) config('orthanc.verify_ssl', true);

        $client = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->withBasicAuth($username, $password)
            ->withBody($contents, 'application/dicom')
            ->acceptJson();

        if (! $verifySsl) {
            $client = $client->withoutVerifying();
        }

        $response = $client->post('/instances');

        if (! $response->successful()) {
            Log::warning('Orthanc DICOM upload failed', [
                'status' => $response->status(),
                'filename' => $filename,
            ]);

            return response()->json([
                'message' => 'Upload DICOM gagal.',
                'status' => $response->status(),
                'error' => $response->body(),
            ], 502);
        }

        $json = (array) $response->json();
        $parentStudy = is_string($json['ParentStudy'] ?? null) ? $json['ParentStudy'] : null;

        OrthancAuditLogger::logUploadDicom($parentStudy, $filename);

        return response()->json([
            'message' => 'DICOM berhasil diunggah.',
            'data' => $json,
        ], 201);
    }
}
