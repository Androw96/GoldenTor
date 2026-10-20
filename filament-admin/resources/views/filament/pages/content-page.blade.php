<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" color="primary">Mentés</x-filament::button>
    </form>
</x-filament-panels::page>
