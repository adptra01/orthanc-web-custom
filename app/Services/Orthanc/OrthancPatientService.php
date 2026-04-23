<?php

namespace App\Services\Orthanc;

/**
 * Service untuk resource Patient pada Orthanc.
 *
 * Mengembalikan array terstruktur (bukan DTO) sesuai keputusan arsitektur plan.
 * PHI tidak disimpan ke DB Laravel; hasil hanya dikembalikan ke caller.
 */
class OrthancPatientService extends OrthancService
{
    /**
     * Ambil ID semua patient di Orthanc.
     *
     * @return array<int, string>
     */
    public function listIds(): array
    {
        /** @var array<int, string> $ids */
        $ids = $this->get('/patients');

        return $ids;
    }

    /**
     * Ambil seluruh patient (detail ringkas) sekaligus.
     *
     * PERHATIAN: untuk instance Orthanc besar, gunakan pagination via
     * `/tools/find` atau query `since`/`limit`. Ini untuk MVP.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_map(
            fn (string $id): array => $this->getById($id),
            $this->listIds()
        );
    }

    /**
     * Detail patient berdasarkan Orthanc ID.
     *
     * @return array<string, mixed>
     */
    public function getById(string $orthancId): array
    {
        $data = $this->get('/patients/'.$orthancId);
        $tags = (array) ($data['MainDicomTags'] ?? []);

        return [
            'orthancId' => $orthancId,
            'patientId' => (string) ($tags['PatientID'] ?? ''),
            'patientName' => (string) ($tags['PatientName'] ?? 'Unknown'),
            'birthDate' => $tags['PatientBirthDate'] ?? null,
            'sex' => $tags['PatientSex'] ?? null,
            'studiesCount' => is_array($data['Studies'] ?? null) ? count($data['Studies']) : 0,
            'studies' => (array) ($data['Studies'] ?? []),
            'mainDicomTags' => $tags,
            'lastUpdate' => $data['LastUpdate'] ?? null,
        ];
    }

    /**
     * Cari patient berdasarkan nama (mendukung wildcard Orthanc, mis. "DOE*").
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchByName(string $query): array
    {
        $ids = $this->post('/tools/find', [
            'Level' => 'Patient',
            'Query' => [
                'PatientName' => $query,
            ],
        ]);

        /** @var array<int, string> $ids */
        return array_map(
            fn (string $id): array => $this->getById($id),
            array_values(array_filter((array) $ids, 'is_string'))
        );
    }

    /**
     * Daftar studies milik patient.
     *
     * @return array<int, string>
     */
    public function studyIdsOf(string $orthancId): array
    {
        $data = $this->get('/patients/'.$orthancId);

        return (array) ($data['Studies'] ?? []);
    }

    /**
     * Hapus patient di Orthanc.
     *
     * Catatan: method ini memanggil `deleteResource()` dari base class untuk
     * menghindari konflik/rekursi dengan nama method `delete`.
     */
    public function remove(string $orthancId): void
    {
        $this->deleteResource('/patients/'.$orthancId);
    }
}
