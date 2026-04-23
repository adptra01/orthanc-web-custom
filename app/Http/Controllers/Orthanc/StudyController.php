<?php

namespace App\Http\Controllers\Orthanc;

use App\Http\Controllers\Controller;
use App\Services\Orthanc\OrthancStudyService;
use App\Services\OrthancAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class StudyController extends Controller
{
    public function __construct(
        protected OrthancStudyService $studies,
    ) {}

    /**
     * Detail satu study (JSON).
     */
    public function show(string $orthancId): JsonResponse
    {
        try {
            $study = $this->studies->getById($orthancId);

            OrthancAuditLogger::logViewStudy($orthancId, $study);

            return response()->json([
                'data' => array_merge($study, [
                    'viewerUrl' => $this->studies->viewerUrl($orthancId),
                ]),
            ]);
        } catch (RuntimeException $e) {
            return $this->gatewayError($e);
        }
    }

    /**
     * Redirect ke Stone Web Viewer di Orthanc untuk study ini.
     *
     * Alternatif view (Tahap 5) akan meng-embed via iframe.
     */
    public function viewer(Request $request, string $orthancId): RedirectResponse
    {
        $viewer = $request->query('viewer');
        $url = $this->studies->viewerUrl($orthancId, is_string($viewer) ? $viewer : null);

        OrthancAuditLogger::log('opened viewer', 'study', $orthancId, [
            'viewer' => $viewer ?? config('orthanc.viewer.default'),
        ]);

        return redirect()->away($url);
    }

    protected function gatewayError(RuntimeException $e): JsonResponse
    {
        return response()->json([
            'message' => 'Orthanc gateway error',
            'error' => $e->getMessage(),
        ], 502);
    }
}
