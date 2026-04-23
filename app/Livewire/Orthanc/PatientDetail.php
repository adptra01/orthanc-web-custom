<?php

namespace App\Livewire\Orthanc;

use App\Services\Orthanc\OrthancPatientService;
use App\Services\Orthanc\OrthancStudyService;
use App\Services\OrthancAuditLogger;
use Livewire\Attributes\Url;
use Livewire\Component;

class PatientDetail extends Component
{
    #[Url]
    public string $patientId = '';

    public array $patient = [];

    public array $studies = [];

    public bool $loading = false;

    public string $error = '';

    public function mount(string $patientId): void
    {
        $this->patientId = $patientId;
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = '';

        try {
            $this->patient = app(OrthancPatientService::class)->show($this->patientId);
            $this->studies = app(OrthancStudyService::class)->listByPatient($this->patientId);
            OrthancAuditLogger::logViewPatient($this->patientId, $this->patient);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }

        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.orthanc.patient-detail');
    }
}
