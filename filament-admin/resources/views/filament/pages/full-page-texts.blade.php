<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <label style="display:flex;flex-direction:column;gap:4px;font-size:.875rem;font-weight:600;min-width:min(100%,22rem)">
            Oldal
            <x-filament::input.wrapper>
                <x-filament::input.select onchange="window.location.href = this.value">
                    @foreach ($this->pageOptions() as $page => $label)
                        <option value="{{ $this->pageUrl($page) }}" @selected($page === $selectedPage)>{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <div style="display:flex;gap:16px;align-items:center;font-size:.875rem;padding-top:22px">
            @if ($onlyChanged)
                <a href="{{ $this->pageUrl($selectedPage, false) }}" style="text-decoration:underline">Összes szöveg mutatása</a>
            @else
                <a href="{{ $this->pageUrl($selectedPage, true) }}" style="text-decoration:underline">Csak a módosítottak</a>
            @endif
            <a href="{{ $this->publicUrl(false) }}" target="_blank" rel="noopener" style="text-decoration:underline">Oldal megnyitása ↗</a>
        </div>
    </div>

    <p style="font-size:.875rem;opacity:.75;margin:0">
        Tipp: a weboldalon is szerkeszthetsz – belépett adminként jobb alul megjelenik a „✎ Szövegek szerkesztése” gomb.
        A ● jelölt mezők módosítva vannak; üresre törölve az eredeti szöveg áll vissza.
    </p>

    <form wire:submit="save" style="display:flex;flex-direction:column;gap:16px">
        @if (count($this->entries()) === 0)
            <x-filament::section>Ezen az oldalon nincs {{ $onlyChanged ? 'módosított ' : '' }}szöveg.</x-filament::section>
        @else
            {{ $this->form }}
            <div>
                <x-filament::button type="submit" color="primary" icon="heroicon-m-check">Mentés</x-filament::button>
            </div>
        @endif
    </form>
</x-filament-panels::page>
