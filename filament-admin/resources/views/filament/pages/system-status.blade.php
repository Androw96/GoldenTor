@php
    $ok = 'color:#4ade80;font-weight:600';
    $bad = 'color:#f87171;font-weight:600';
    $cell = 'padding:6px 10px;border-bottom:1px solid rgba(127,127,127,.2);vertical-align:top;text-align:left';
    $mono = 'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;white-space:pre-wrap;word-break:break-word';
@endphp
<x-filament-panels::page>
    <x-filament::section heading="Környezet">
        @if (isset($environment['error']))
            <div style="{{ $bad }}">{{ $environment['error'] }}</div>
        @else
            <table style="width:100%;border-collapse:collapse">
                @foreach ($environment as $key => $value)
                    <tr>
                        <th style="{{ $cell }};width:30%">{{ $key }}</th>
                        <td style="{{ $cell }};{{ $mono }}">{{ is_array($value) ? (count($value) ? implode(', ', $value) : '–') : ($value ?? '–') }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="Adatbázis-táblák">
        <table style="width:100%;border-collapse:collapse">
            <tr><th style="{{ $cell }}">Tábla</th><th style="{{ $cell }}">Állapot</th><th style="{{ $cell }}">Sorok</th><th style="{{ $cell }}">Hiányzó oszlopok</th></tr>
            @foreach ($tables as $table => $info)
                <tr>
                    <td style="{{ $cell }};{{ $mono }}">{{ $table }}</td>
                    <td style="{{ $cell }};{{ ($info['exists'] ?? false) ? $ok : $bad }}">{{ ($info['exists'] ?? false) ? 'megvan' : 'HIÁNYZIK' }}</td>
                    <td style="{{ $cell }}">{{ $info['rows'] ?? '–' }}</td>
                    <td style="{{ $cell }};{{ $mono }};{{ empty($info['missing_columns']) ? '' : $bad }}">{{ empty($info['missing_columns']) ? '–' : implode(', ', $info['missing_columns']) }}{{ isset($info['error']) ? ' ' . $info['error'] : '' }}</td>
                </tr>
            @endforeach
        </table>
    </x-filament::section>

    <x-filament::section heading="Írható mappák">
        <table style="width:100%;border-collapse:collapse">
            @foreach ($writable as $folder => $isWritable)
                <tr>
                    <td style="{{ $cell }};{{ $mono }}">{{ $folder }}</td>
                    <td style="{{ $cell }};{{ $isWritable ? $ok : $bad }}">{{ $isWritable === true ? 'írható' : (is_bool($isWritable) ? 'NEM ÍRHATÓ' : $isWritable) }}</td>
                </tr>
            @endforeach
        </table>
    </x-filament::section>

    <x-filament::section heading="Legutóbbi hibák a naplóban" description="Csak az első sor, e-mail-címek nélkül. A legfrissebb van felül.">
        @forelse ($recent_errors as $entry)
            <div style="padding:8px 0;border-bottom:1px solid rgba(127,127,127,.2)">
                <div style="font-size:12px;opacity:.7">{{ $entry['time'] ?? '' }} · {{ $entry['level'] ?? '' }}</div>
                <div style="{{ $mono }}">{{ $entry['message'] ?? ($entry ?? '') }}</div>
            </div>
        @empty
            <div style="{{ $ok }}">Nincs rögzített hiba.</div>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
