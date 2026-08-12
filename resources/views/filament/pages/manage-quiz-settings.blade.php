<x-filament-panels::page>
    <form wire:submit="save" class="max-w-lg space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Saqlash
        </x-filament::button>
    </form>
</x-filament-panels::page>
