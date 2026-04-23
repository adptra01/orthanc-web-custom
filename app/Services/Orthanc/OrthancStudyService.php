<?php

namespace App\Services\Orthanc;

/**
 * Service untuk resource Study pada Orthanc.
 */
class OrthancStudyService extends OrthancService
{
    /**
     * Daftar semua study ID.
     *
     * @return array<int, string>
     */
    public function listIds(): array
    {
        /** @var array<int, string> $ids */
        $ids = $this->get('/studies');

        return $ids;
    }

    /**
     * Detail study berdasarkan Orthanc ID.
     *
     * @return array<string, mixed>
     */
    public function getById(string $orthancId): array
    {
        $data = $this->get('/studies/'.$orthancId);
        $tags = (array) ($data['MainDicomTags'] ?? []);
        $patient = (array) ($data['PatientMainDicomTags'] ?? []);

        return [
            'orthancId' => $orthancId,
            'studyInstanceUid' => (string) ($tags['StudyInstanceUID'] ?? ''),
            'studyDate' => $tags['StudyDate'] ?? null,
            'studyTime' => $tags['StudyTime'] ?? null,
            'studyDescription' => (string) ($tags['StudyDescription'] ?? ''),
            'accessionNumber' => $tags['AccessionNumber'] ?? null,
            'referringPhysician' => $tags['ReferringPhysicianName'] ?? null,
            'patient' => [
                'orthancId' => (string) ($data['ParentPatient'] ?? ''),
                'patientId' => (string) ($patient['PatientID'] ?? ''),
                'patientName' => (string) ($patient['PatientName'] ?? 'Unknown'),
                'birthDate' => $patient['PatientBirthDate'] ?? null,
                'sex' => $patient['PatientSex'] ?? null,
            ],
            'series' => (array) ($data['Series'] ?? []),
            'seriesCount' => is_array($data['Series'] ?? null) ? count($data['Series']) : 0,
            'mainDicomTags' => $tags,
            'lastUpdate' => $data['LastUpdate'] ?? null,
        ];
    }

    /**
     * Cari study (contoh kriteria: StudyDate, Modality, PatientID).
     *
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function search(array $query): array
    {
        $ids = $this->post('/tools/find', [
            'Level' => 'Study',
            'Query' => $query,
        ]);

        return array_map(
            fn (string $id): array => $this->getById($id),
            array_values(array_filter((array) $ids, 'is_string'))
        );
    }

    /**
     * URL viewer untuk study (Stone Web Viewer by default).
     */
    public function viewerUrl(string $orthancId, ?string $viewer = null): string
    {
        $viewerKey = $viewer ?? (string) config('orthanc.viewer.default', 'stone');
        $path = $viewerKey === 'osimis'
            ? (string) config('orthanc.viewer.osimis_viewer_path')
            : (string) config('orthanc.viewer.stone_web_viewer_path');

        $separator = str_contains($path, '?') ? '&' : '?';

        return rtrim((string) config('orthanc.base_url'), '/').$path.$separator.'study='.$orthancId;
    }

    public function remove(string $orthancId): void
    {
        $this->deleteResource('/studies/'.$orthancId);
    }
}
