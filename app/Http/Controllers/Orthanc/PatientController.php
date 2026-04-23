<?php

namespace App\Http\Controllers\Orthanc;

use App\Http\Controllers\Controller;
use App\Services\Orthanc\OrthancPatientService;
use App\Services\Orthanc\OrthancStudyService;
use App\Services\OrthancAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Gateway endpoint untuk resource Patient di Orthanc.
 *
 * Sementara mengembalikan JSON. Tahap 5 akan menambahkan Livewire view.
 */
class PatientController extends Controller
{
    public function __construct(
        protected OrthancPatientService $patients,
        protected OrthancStudyService $studies,
    ) {}

    /**
     * Daftar patient. Dukung search sederhana via query `?q=...`.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        try {
            $data = $search !== ''
                ? $this->patients->searchByName($search.'*')
                : $this->patients->all();

            OrthancAuditLogger::logListPatients($search !== '' ? $search : null);

            return response()->json([
                'data' => $data,
                'meta' => [
                    'count' => count($data),
                    'search' => $search !== '' ? $search : null,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->gatewayError($e);
        }
    }

    /**
     * Detail satu patient beserta study miliknya.
     */
    public function show(string $orthancId): JsonResponse
    {
        try {
            $patient = $this->patients->getById($orthancId);

            OrthancAuditLogger::logViewPatient($orthancId, $patient);

            $studies = array_map(
                fn (string $id): array => $this->studies->getById($id),
                $patient['studies'] ?? []
            );

            return response()->json([
                'data' => array_merge($patient, [
                    'studies' => $studies,
                ]),
            ]);
        } catch (RuntimeException $e) {
            return $this->gatewayError($e);
        }
    }

    protected function gatewayError(RuntimeException $e): JsonResponse
    {
        return response()->json([
            'message' => 'Orthanc gateway error',
            'error' => $e->getMessage(),
        ], 502);
    }
}
