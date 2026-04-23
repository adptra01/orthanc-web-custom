<div>
    @if ($loading)
        <div class="flex items-center justify-center p-8">
            <x-flux::icon.spinner class="h-8 w-8 animate-spin text-blue-500" />
            <span class="ml-2 text-gray-500">Loading patient data...</span>
        </div>
    @else
        @if ($patient)
            <div class="space-y-6">
                {{-- Patient Header --}}
                <div class="bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl p-8 text-white shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold">
                                {{ $patient['MainDicomTags']['PatientName'] ?? 'Unknown Patient' }}</h1>
                            <p class="text-blue-100 mt
