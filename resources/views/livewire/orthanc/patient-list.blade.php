<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="xl">Patients</flux:heading>

        <flux:button variant="primary" icon="arrow-path" wire:click="refreshList" :loading="true"
            wire:loading.attr="disabled">
            Refresh
        </flux:button>
    </div>

    <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nama pasien (mis. DOE)..."
        icon="magnifying-glass" clearable />

    @if ($loading)
        <div
            class="rounded-lg border border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Memuat data...
        </div>
    @elseif ($error)
        <div
            class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
            <div class="font-medium">Gagal terhubung ke Orthanc.</div>
            <div class="mt-1 text-xs opacity-80">{{ $error }}</div>
        </div>
    @elseif (empty($patients))
        <div
            class="rounded-lg border border-zinc-200 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Tidak ada patient yang cocok.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        <th
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Patient ID</th>
                        <th
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Name</th>
                        <th
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Birth Date</th>
                        <th
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Sex</th>
                        <th
                            class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            Studies</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800">
                    @foreach ($patients as $patient)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900">
                            <td
                                class="whitespace-nowrap px-4 py-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $patient['patientId'] ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $patient['patientName'] ?: 'Unknown' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $patient['birthDate'] ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $patient['sex'] ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300">
                                {{ $patient['studiesCount'] ?? 0 }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                <flux:button size="sm" variant="ghost"
                                    href="{{ route('orthanc.patients.show', ['orthancId' => $patient['orthancId']]) }}"
                                    icon="eye">
                                    Detail
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
