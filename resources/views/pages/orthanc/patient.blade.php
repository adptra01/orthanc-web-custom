<x-layouts::app :title="'Patient Detail'">
    <div class="mx-auto w-full max-w-6xl p-6">
        <livewire:orthanc.patient-detail :patient-id="$patientId" />
    </div>
</x-layouts::app>
