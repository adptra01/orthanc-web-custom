<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Spatie\Activitylog\Contracts\Activity;

/**
 * Helper untuk menulis entri audit log Orthanc secara konsisten
 * menggunakan spatie/laravel-activitylog.
 *
 * Simpan hanya metadata non-PHI (orthancId, resource_type, IP, user agent,
 * aksi). PHI tidak pernah disimpan di DB Laravel.
 */
class OrthancAuditLogger
{
    public const LOG_NAME = 'orthanc';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        string $action,
        string $resourceType,
        ?string $orthancId = null,
        array $metadata = []
    ): ?Activity {
        $properties = array_merge($metadata, array_filter([
            'orthanc_id' => $orthancId,
            'resource_type' => $resourceType,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => RequestFacade::userAgent(),
        ], static fn ($v) => $v !== null && $v !== ''));

        /** @var Activity|null $activity */
        $activity = activity(self::LOG_NAME)
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->log($action);

        return $activity;
    }

    public static function logListPatients(?string $search = null): void
    {
        self::log(
            action: $search ? 'searched patients' : 'listed patients',
            resourceType: 'patient',
            metadata: array_filter(['search' => $search], static fn ($v) => $v !== null && $v !== ''),
        );
    }

    /**
     * @param  array<string, mixed>  $patient
     */
    public static function logViewPatient(string $orthancId, array $patient = []): void
    {
        self::log('viewed patient', 'patient', $orthancId, [
            'patient_id' => $patient['patientId'] ?? null,
            'patient_name' => $patient['patientName'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $study
     */
    public static function logViewStudy(string $orthancId, array $study = []): void
    {
        self::log('viewed study', 'study', $orthancId, [
            'study_description' => $study['studyDescription'] ?? null,
            'study_date' => $study['studyDate'] ?? null,
        ]);
    }

    public static function logUploadDicom(?string $parentStudyId, string $filename): void
    {
        self::log('uploaded dicom', 'instance', $parentStudyId, [
            'filename' => $filename,
        ]);
    }

    public static function logDeletePatient(string $orthancId): void
    {
        self::log('deleted patient', 'patient', $orthancId);
    }

    public static function logDeleteStudy(string $orthancId): void
    {
        self::log('deleted study', 'study', $orthancId);
    }
}
