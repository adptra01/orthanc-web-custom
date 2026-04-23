<?php

namespace App\Livewire\Orthanc;

use App\Services\Orthanc\OrthancPatientService;
use App\Services\OrthancAuditLogger;
use Illuminate\View\View;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

class PatientList extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $patients = [];

    public bool $loading = false;

    public ?string $error = null;

    public function mount(): void
    {
        $this->load();
    }

    public function updatedSearch(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            /** @var OrthancPatientService $service */
            $service = app(OrthancPatientService::class);

            $term = trim($this->search);

            $this->patients = $term !== ''
                ? $service->searchByName($term.'*')
                : $service->all();

            OrthancAuditLogger::logListPatients($term !== '' ? $term : null);
        } catch (RuntimeException $e) {
            $this->patients = [];
            $this->error = $e->getMessage();

            LivewireAlert::title('Gagal memuat data patient')
                ->text($e->getMessage())
                ->error()
                ->show();
        } finally {
            $this->loading = false;
        }
    }

    public function refreshList(): void
    {
        $this->load();
    }

    public function render(): View
    {
        return view('livewire.orthanc.patient-list');
    }
}
