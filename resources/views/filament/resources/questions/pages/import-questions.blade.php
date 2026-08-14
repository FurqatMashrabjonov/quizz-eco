<x-filament-panels::page>
    @if (! $parsed)
        <form wire:submit="preview" class="max-w-lg space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit">
                Ko'rish
            </x-filament::button>
        </form>
    @else
        @php $counts = $this->counts(); @endphp

        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <x-filament::badge color="success">Yangi: {{ $counts['new'] }}</x-filament::badge>
                <x-filament::badge color="gray">Mavjud: {{ $counts['exists'] }}</x-filament::badge>
                <x-filament::badge color="warning">Takror: {{ $counts['duplicate'] }}</x-filament::badge>
                <x-filament::badge color="danger">Xato: {{ $counts['invalid'] }}</x-filament::badge>
            </div>

            @if ($this->looksAlreadyImported())
                <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
                    Diqqat: qatorlarning aksariyati allaqachon mavjud — bu fayl avvalroq import qilingan bo'lishi mumkin.
                </x-filament::badge>
            @endif

            @if (count($rows) > 0)
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-start font-medium">Qator</th>
                                <th class="px-3 py-2 text-start font-medium">Savol</th>
                                <th class="px-3 py-2 text-start font-medium">A</th>
                                <th class="px-3 py-2 text-start font-medium">B</th>
                                <th class="px-3 py-2 text-start font-medium">C</th>
                                <th class="px-3 py-2 text-start font-medium">D</th>
                                <th class="px-3 py-2 text-start font-medium">To'g'ri</th>
                                <th class="px-3 py-2 text-start font-medium">Izoh</th>
                                <th class="px-3 py-2 text-start font-medium">Holat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="import-row-{{ $row['row'] }}" class="border-t border-gray-100 dark:border-white/5">
                                    <td class="px-3 py-2 text-gray-500">{{ $row['row'] }}</td>
                                    <td class="px-3 py-2">{{ $row['body'] }}</td>
                                    <td class="px-3 py-2">{{ $row['options']['a'] }}</td>
                                    <td class="px-3 py-2">{{ $row['options']['b'] }}</td>
                                    <td class="px-3 py-2">{{ $row['options']['c'] }}</td>
                                    <td class="px-3 py-2">{{ $row['options']['d'] }}</td>
                                    <td class="px-3 py-2 font-mono uppercase">{{ $row['answer'] }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['explanation'] ?: '-' }}</td>
                                    <td class="px-3 py-2">
                                        @php
                                            [$label, $color] = match ($row['status']) {
                                                'new' => ["Yangi", 'success'],
                                                'exists' => ["Mavjud", 'gray'],
                                                'duplicate' => ["Takror", 'warning'],
                                                'invalid' => ["Xato", 'danger'],
                                            };
                                        @endphp
                                        <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-filament::badge color="gray">Faylda hech qanday qator topilmadi.</x-filament::badge>
            @endif

            <div class="flex gap-2">
                <x-filament::button color="gray" wire:click="resetImport">
                    Bekor qilish
                </x-filament::button>

                <x-filament::button
                    wire:click="import"
                    :disabled="$counts['new'] === 0"
                >
                    {{ $counts['new'] }} ta savolni import qilish
                </x-filament::button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
