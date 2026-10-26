@php
    $data = $this->calendarData();
    $today = now('Europe/Budapest')->toDateString();
    $nowTime = now('Europe/Budapest')->format('Y-m-d H:i:s');
    $sourceLabels = ['manual' => 'zárolás', 'google' => 'Google', 'upload' => 'feltöltött naptár'];
    $short = [1 => 'H', 2 => 'K', 3 => 'Sze', 4 => 'Cs', 5 => 'P', 6 => 'Szo', 7 => 'V'];
    $noticeLabels = [0 => 'aznapra is', 1 => 'legkorábban holnapra', 2 => 'legalább 2 nappal előre', 3 => 'legalább 3 nappal előre', 7 => 'legalább 1 héttel előre'];
    $hu = fn (string $date, string $format) => \Carbon\Carbon::parse($date)->locale('hu')->isoFormat($format);
    $time = fn (string $value) => substr($value, 11, 5);
@endphp
<x-filament-panels::page>
    <style>
        .gtcal{display:flex;flex-direction:column;gap:16px}
        .gtcal-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:12px}
        .gtcal-card{border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:12px 14px;font-size:13.5px;line-height:1.5}
        .gtcal-card h3{font-size:12px;letter-spacing:.06em;text-transform:uppercase;opacity:.65;margin:0 0 4px;font-weight:600}
        .gtcal-ok{color:#16a34a;font-weight:600}.dark .gtcal-ok{color:#4ade80}
        .gtcal-bad{color:#dc2626;font-weight:600}.dark .gtcal-bad{color:#f87171}
        .gtcal-muted{opacity:.7}
        .gtcal-nav{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}
        .gtcal-nav h2{font-size:1.05rem;font-weight:600;margin:0}
        .gtcal-nav-buttons{display:flex;gap:6px;flex-wrap:wrap}
        .gtcal-scroll{overflow-x:auto;padding-bottom:4px}
        .gtcal-grid{display:grid;grid-template-columns:repeat(7,minmax(148px,1fr));gap:8px;min-width:1080px}
        .gtcal-day{border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:10px;display:flex;flex-direction:column;gap:6px;background:rgba(127,127,127,.03)}
        .gtcal-day.is-today{border-color:#b8962e;box-shadow:inset 0 0 0 1px #b8962e}
        .gtcal-day.is-off{background:transparent;border-style:dashed}
        .gtcal-day-head{display:flex;justify-content:space-between;align-items:flex-start;gap:6px}
        .gtcal-day-head b{display:block;font-size:14px}
        .gtcal-day-head span{font-size:12px;opacity:.7}
        .gtcal-link{font-size:11.5px;text-decoration:underline;opacity:.8;background:none;border:0;padding:0;cursor:pointer;color:inherit;white-space:nowrap}
        .gtcal-link:hover{opacity:1}
        .gtcal-slot{display:block;width:100%;text-align:left;border-radius:8px;padding:6px 9px;font-size:12.5px;line-height:1.3;border:1px solid;cursor:pointer;color:inherit;text-decoration:none;transition:filter .12s,transform .12s}
        .gtcal-slot:hover{filter:brightness(1.08);transform:translateY(-1px)}
        .gtcal-slot b{font-size:13.5px;margin-right:4px}
        .gtcal-slot small{display:block;opacity:.85;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .gtcal-free{border-color:rgba(34,197,94,.55);background:rgba(34,197,94,.10)}
        .gtcal-booked{border-color:rgba(59,130,246,.6);background:rgba(59,130,246,.15)}
        .gtcal-blocked{border-color:rgba(127,127,127,.55);background:repeating-linear-gradient(45deg,rgba(127,127,127,.20) 0 6px,rgba(127,127,127,.08) 6px 12px)}
        .gtcal-busy{border-color:rgba(168,85,247,.6);background:rgba(168,85,247,.14)}
        .gtcal-closed,.gtcal-past,.gtcal-later{border-style:dashed;border-color:rgba(127,127,127,.35);background:transparent;opacity:.7}
        .gtcal-events{border-top:1px solid rgba(127,127,127,.2);padding-top:6px;margin-top:2px;display:flex;flex-direction:column;gap:3px;font-size:11.5px}
        .gtcal-events div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .gtcal-legend{display:flex;flex-wrap:wrap;gap:8px;font-size:12px;align-items:center}
        .gtcal-legend span{border:1px solid;border-radius:6px;padding:2px 8px}
        .gtcal-table{width:100%;border-collapse:collapse;font-size:13.5px}
        .gtcal-table td,.gtcal-table th{padding:7px 8px;border-bottom:1px solid rgba(127,127,127,.2);text-align:left;vertical-align:top}
        .gtcal-del{color:#dc2626;background:none;border:0;cursor:pointer;text-decoration:underline;font-size:13px}.dark .gtcal-del{color:#f87171}
    </style>

    <div class="gtcal">
        @if ($data['error'])
            <x-filament::section>
                <div class="gtcal-bad">A naptár nem tölthető be.</div>
                <div style="margin-top:6px;font-size:13px">{{ $data['error'] }}</div>
            </x-filament::section>
        @else
            @php $rules = $data['rules']; @endphp
            @php $google = $data['google']; @endphp
            <div class="gtcal-cards">
                <div class="gtcal-card">
                    <h3>Foglalási szabályok</h3>
                    <div>
                        {{ implode(', ', array_map(fn ($d) => $short[$d], $rules['weekdays'])) ?: 'nincs foglalható nap' }}
                        · {{ implode(', ', $rules['slots']) ?: 'nincs időpont' }}
                    </div>
                    <div class="gtcal-muted">
                        {{ $rules['duration'] }} perces konzultáció · {{ $rules['days_ahead'] }} napra előre · {{ $noticeLabels[$rules['min_notice_days']] ?? 'legalább ' . $rules['min_notice_days'] . ' nappal előre' }}
                    </div>
                </div>
                <div class="gtcal-card">
                    <h3>Google Naptár</h3>
                    @if (! $google['urls'])
                        <div class="gtcal-muted">Nincs összekapcsolva. A „Google Naptár” gombbal add meg a naptár titkos iCal-címét.</div>
                    @else
                        @php $sync = $google['sync']; @endphp
                        @if (empty($sync['at']))
                            <div class="gtcal-muted">Összekapcsolva, még nem frissült.</div>
                        @elseif ($sync['ok'] ?? false)
                            <div><span class="gtcal-ok">● Összekapcsolva</span> ({{ count($google['urls']) }} naptár) · {{ $sync['count'] ?? 0 }} esemény</div>
                            <div class="gtcal-muted">Utolsó frissítés: {{ \App\Support\BookingCalendar::utcLabel($sync['at']) }} · 10 percenként magától frissül</div>
                        @else
                            <div><span class="gtcal-bad">● Frissítési hiba</span> · {{ \App\Support\BookingCalendar::utcLabel($sync['at']) }}</div>
                            <div style="font-size:12.5px">{{ $sync['error'] ?? '' }}</div>
                            <div class="gtcal-muted">
                                {{ ! empty($sync['last_ok_at']) ? 'Utoljára sikeres: ' . \App\Support\BookingCalendar::utcLabel($sync['last_ok_at']) . ' – az akkor betöltött ' . ($sync['count'] ?? 0) . ' esemény érvényben marad.' : 'Még egyszer sem sikerült betölteni.' }}
                            </div>
                        @endif
                    @endif
                    @if (! empty($google['upload']['file']))
                        <div style="margin-top:4px">Feltöltött fájl: <b>{{ $google['upload']['file'] }}</b> · {{ $google['upload']['count'] ?? 0 }} esemény · {{ \App\Support\BookingCalendar::utcLabel($google['upload']['at'] ?? null) }}</div>
                    @endif
                </div>
            </div>

            <div class="gtcal-nav">
                <h2>{{ $hu($data['start'], 'YYYY. MMMM D.') }} – {{ $hu($data['end'], substr($data['start'], 0, 7) === substr($data['end'], 0, 7) ? 'D.' : 'MMMM D.') }}</h2>
                <div class="gtcal-nav-buttons">
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousWeek">Előző hét</x-filament::button>
                    <x-filament::button color="gray" size="sm" wire:click="thisWeek">Ez a hét</x-filament::button>
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" wire:click="nextWeek">Következő hét</x-filament::button>
                </div>
            </div>

            <div class="gtcal-legend">
                <span class="gtcal-free">Szabad – kattintva zárolod</span>
                <span class="gtcal-blocked">Zárolva – kattintva feloldod</span>
                <span class="gtcal-booked">Ügyfél foglalása</span>
                <span class="gtcal-busy">Google-ban foglalt</span>
                <span class="gtcal-closed">Nem foglalható</span>
            </div>

            <div class="gtcal-scroll" wire:loading.class="opacity-60">
                <div class="gtcal-grid">
                    @foreach ($data['days'] as $day)
                        @php
                            $nextDay = \Carbon\Carbon::parse($day['date'])->addDay()->toDateString() . ' 00:00:00';
                            $dayLocked = collect($day['blocks'])->contains(fn ($b) => $b['source'] === 'manual' && $b['all_day'] && $b['starts_at'] === $day['date'] . ' 00:00:00' && $b['ends_at'] === $nextDay);
                        @endphp
                        <div @class(['gtcal-day', 'is-today' => $day['date'] === $today, 'is-off' => ! $day['workday']]) wire:key="day-{{ $day['date'] }}">
                            <div class="gtcal-day-head">
                                <div>
                                    <b>{{ \App\Filament\Pages\BookingCalendarPage::weekdayName($day['weekday']) }}</b>
                                    <span>{{ $hu($day['date'], 'MMMM D.') }}{{ $day['date'] === $today ? ' · ma' : '' }}</span>
                                </div>
                                @if ($day['date'] >= $today)
                                    <button type="button" class="gtcal-link" wire:click="toggleDay(@js($day['date']))">{{ $dayLocked ? 'Nap feloldása' : 'Nap zárolása' }}</button>
                                @endif
                            </div>

                            @forelse ($day['slots'] as $slot)
                                @php $state = $slot['state']; @endphp
                                @if ($state === 'booked')
                                    <a class="gtcal-slot gtcal-booked" href="{{ $this->bookingUrl($slot['booking']) }}" title="Foglalás megnyitása">
                                        <b>{{ $slot['time'] }}</b>{{ \App\Filament\Pages\BookingCalendarPage::bookingStatus($slot['booking']['status']) }}
                                        <small>{{ $slot['booking']['name'] }} · {{ $slot['booking']['topic'] }}</small>
                                    </a>
                                @else
                                    @php
                                        $label = match ($state) {
                                            'blocked' => ($slot['block']['title'] ?: 'Zárolva'),
                                            'busy' => ($slot['block']['title'] ?: 'Foglalt') . ' (' . ($sourceLabels[$slot['block']['source']] ?? '') . ')',
                                            default => '',
                                        };
                                    @endphp
                                    <button type="button" @class(['gtcal-slot', 'gtcal-' . $state]) wire:click="toggleSlot(@js($day['date']), @js($slot['time']))"
                                        title="{{ $state === 'blocked' ? 'Kattints a feloldáshoz' : ($state === 'busy' ? 'A Google Naptárban módosítható' : 'Kattints a zároláshoz') }}">
                                        <b>{{ $slot['time'] }}</b>{{ $state === 'busy' ? 'Foglalt' : (\App\Filament\Pages\BookingCalendarPage::STATE_LABELS[$state] ?? '') }}
                                        @if (in_array($state, ['blocked', 'busy'], true))
                                            <small>{{ $label }}</small>
                                        @endif
                                    </button>
                                @endif
                            @empty
                                <div class="gtcal-muted" style="font-size:12px">Nincs beállított időpont.</div>
                            @endforelse

                            @php
                                $events = collect($day['blocks'])->reject(fn ($b) => $b['source'] === 'manual' && ! $b['all_day'] && $b['title'] === 'Zárolva');
                                $offSlot = collect($day['bookings'])->reject(fn ($b) => in_array($b['booking_time'], $rules['slots'], true));
                            @endphp
                            @if ($events->isNotEmpty() || $offSlot->isNotEmpty())
                                <div class="gtcal-events">
                                    @foreach ($events as $event)
                                        <div title="{{ $event['title'] }}">
                                            {{ $event['all_day'] ? 'Egész nap' : (substr($event['starts_at'], 0, 10) === $day['date'] ? $time($event['starts_at']) : '…') . '–' . (substr($event['ends_at'], 0, 10) === $day['date'] ? $time($event['ends_at']) : '…') }}
                                            · {{ $event['title'] ?: 'Esemény' }}
                                            <span class="gtcal-muted">({{ $sourceLabels[$event['source']] ?? '' }}{{ $event['counts'] ? '' : ', szabadként jelölve' }})</span>
                                        </div>
                                    @endforeach
                                    @foreach ($offSlot as $booking)
                                        <div>{{ $booking['booking_time'] }} · {{ $booking['name'] }} <span class="gtcal-muted">(foglalás)</span></div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <x-filament::section heading="Zárolások" description="Az általad felvett, még le nem járt zárolások. A Google-események a Google Naptárban módosíthatók.">
                @if (! $data['manual'])
                    <div class="gtcal-muted" style="font-size:13.5px">Nincs aktív zárolás. Egy időpontra vagy a „Nap zárolása” linkre kattintva, illetve a „Zárolás hozzáadása” gombbal vehetsz fel.</div>
                @else
                    <table class="gtcal-table">
                        <tr><th>Időszak</th><th>Megjegyzés</th><th></th></tr>
                        @foreach ($data['manual'] as $block)
                            <tr wire:key="block-{{ $block['id'] }}">
                                <td>{{ $this->period(['starts_at' => $block['starts_at'], 'ends_at' => $block['ends_at'], 'all_day' => (bool) $block['all_day']]) }}</td>
                                <td>{{ $block['title'] ?: '–' }}</td>
                                <td style="text-align:right"><button type="button" class="gtcal-del" wire:click="deleteBlock({{ (int) $block['id'] }})" wire:confirm="Biztosan törlöd ezt a zárolást?">Törlés</button></td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
