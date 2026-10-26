<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Support\BookingCalendar;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Week view of the booking calendar: bookings, admin blocks and the imported Google
 * Calendar. Clicking a slot blocks or frees it; the booking form on the website only
 * offers slots that are free here.
 */
class BookingCalendarPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'Ügyfélkapcsolat';

    protected static ?string $navigationLabel = 'Naptár';

    protected static ?string $title = 'Naptár és szabad időpontok';

    protected static ?string $slug = 'naptar';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.booking-calendar';

    public const STATE_LABELS = [
        'free' => 'Szabad',
        'booked' => 'Foglalás',
        'blocked' => 'Zárolva',
        'busy' => 'Google: foglalt',
        'closed' => 'Nem foglalási nap',
        'past' => 'Már nem foglalható',
        'later' => 'Még nem foglalható',
    ];

    public const WEEKDAYS = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek', 6 => 'Szombat', 7 => 'Vasárnap'];

    /** Monday of the shown week (Y-m-d). */
    #[Url(as: 'het')]
    public string $week = '';

    public function mount(): void
    {
        $this->week = $this->monday($this->week);
    }

    public function previousWeek(): void
    {
        $this->week = $this->monday($this->monday($this->week) . ' -7 days');
    }

    public function nextWeek(): void
    {
        $this->week = $this->monday($this->monday($this->week) . ' +7 days');
    }

    public function thisWeek(): void
    {
        $this->week = $this->monday('');
    }

    private function monday(string $value): string
    {
        $zone = new \DateTimeZone('Europe/Budapest');
        try {
            $date = $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( [+-]\d+ days)?$/', $value)
                ? new \DateTimeImmutable($value, $zone)
                : new \DateTimeImmutable('today', $zone);
        } catch (Throwable) {
            $date = new \DateTimeImmutable('today', $zone);
        }

        return $date->modify('-' . ((int) $date->format('N') - 1) . ' days')->format('Y-m-d');
    }

    // ------------------------------------------------------------------ data for the view

    public function calendarData(): array
    {
        $start = $this->monday($this->week);
        $end = (new \DateTimeImmutable($start))->modify('+6 days')->format('Y-m-d');
        try {
            return [
                'error' => null,
                'days' => BookingCalendar::occupancy($start, $end),
                'rules' => BookingCalendar::rules(),
                'google' => BookingCalendar::google(),
                'manual' => BookingCalendar::manualBlocks(),
                'start' => $start,
                'end' => $end,
            ];
        } catch (Throwable $error) {
            report($error);

            return ['error' => $error->getMessage(), 'days' => [], 'rules' => null, 'google' => null, 'manual' => [], 'start' => $start, 'end' => $end];
        }
    }

    public function bookingUrl(array $booking): string
    {
        return BookingResource::getUrl('index', ['tableSearch' => $booking['name'] ?? '']);
    }

    public static function weekdayName(int $day): string
    {
        return self::WEEKDAYS[$day] ?? '';
    }

    public static function bookingStatus(?string $status): string
    {
        return Booking::STATUSES[$status] ?? (string) $status;
    }

    // ------------------------------------------------------------------ actions from the grid

    public function toggleSlot(string $date, string $time): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return;
        }
        try {
            $day = BookingCalendar::occupancy($date, $date)[0];
            $slot = collect($day['slots'])->firstWhere('time', $time);
            if (! $slot) {
                return;
            }
            $duration = BookingCalendar::rules()['duration'];
            switch ($slot['state']) {
                case 'booked':
                    Notification::make()->title('Ezen az időponton foglalás van')
                        ->body(($slot['booking']['name'] ?? '') . ' – a foglalást a Foglalások menüben tudod módosítani vagy lemondani.')
                        ->warning()->send();

                    return;
                case 'busy':
                    Notification::make()->title('Ezt a Google Naptár foglalja')
                        ->body('„' . ($slot['block']['title'] ?? '') . '” – a Google Naptárban módosítható, a változás 10 percen belül ide is átjön.')
                        ->info()->send();

                    return;
                case 'blocked':
                    $block = $slot['block'];
                    $start = new \DateTimeImmutable("{$date} {$time}:00");
                    $isThisSlot = ! $block['all_day']
                        && $block['starts_at'] === $start->format('Y-m-d H:i:s')
                        && $block['ends_at'] === $start->modify("+{$duration} minutes")->format('Y-m-d H:i:s');
                    if (! $isThisSlot) {
                        Notification::make()->title('Ez egy hosszabb zárolás része')
                            ->body(($block['title'] ?: 'Zárolás') . ' (' . $this->period($block) . '). Az oldal alján, a zárolások listájában törölheted.')
                            ->warning()->send();

                        return;
                    }
                    BookingCalendar::deleteBlock((int) $block['id']);
                    Notification::make()->title("{$time} feloldva")->body('Az ügyfelek újra választhatják.')->success()->send();

                    return;
                default:
                    $start = new \DateTimeImmutable("{$date} {$time}:00");
                    BookingCalendar::addBlock($start->format('Y-m-d H:i:s'), $start->modify("+{$duration} minutes")->format('Y-m-d H:i:s'), false, 'Zárolva');
                    Notification::make()->title("{$time} zárolva")->body('Ezt az időpontot az ügyfelek nem választhatják.')->success()->send();
            }
        } catch (Throwable $error) {
            report($error);
            Notification::make()->title('A módosítás nem sikerült')->body($error->getMessage())->danger()->send();
        }
    }

    public function toggleDay(string $date): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        try {
            $day = BookingCalendar::occupancy($date, $date)[0];
            $start = $date . ' 00:00:00';
            $end = (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d H:i:s');
            foreach ($day['blocks'] as $block) {
                if ($block['source'] === 'manual' && $block['all_day'] && $block['starts_at'] === $start && $block['ends_at'] === $end) {
                    BookingCalendar::deleteBlock((int) $block['id']);
                    Notification::make()->title('A nap feloldva')->success()->send();

                    return;
                }
            }
            BookingCalendar::addBlock($start, $end, true, 'Zárolt nap');
            $booked = count($day['bookings']);
            Notification::make()->title('A nap zárolva')
                ->body($booked ? "Figyelem: erre a napra már van {$booked} foglalás, azok megmaradnak." : 'Erre a napra nem lehet időpontot foglalni.')
                ->{$booked ? 'warning' : 'success'}()->send();
        } catch (Throwable $error) {
            report($error);
            Notification::make()->title('A módosítás nem sikerült')->body($error->getMessage())->danger()->send();
        }
    }

    public function deleteBlock(int $id): void
    {
        try {
            BookingCalendar::deleteBlock($id)
                ? Notification::make()->title('Zárolás törölve')->success()->send()
                : Notification::make()->title('A zárolás már nem létezik')->warning()->send();
        } catch (Throwable $error) {
            report($error);
            Notification::make()->title('A törlés nem sikerült')->body($error->getMessage())->danger()->send();
        }
    }

    public function period(array $block): string
    {
        $start = new \DateTimeImmutable($block['starts_at']);
        $end = new \DateTimeImmutable($block['ends_at']);
        if ($block['all_day']) {
            $last = $end->modify('-1 day');

            return $start->format('Y-m-d') === $last->format('Y-m-d')
                ? $start->format('Y. m. d.') . ', egész nap'
                : $start->format('Y. m. d.') . ' – ' . $last->format('Y. m. d.') . ', egész nap';
        }

        return $start->format('Y-m-d') === $end->format('Y-m-d')
            ? $start->format('Y. m. d. H:i') . '–' . $end->format('H:i')
            : $start->format('Y. m. d. H:i') . ' – ' . $end->format('Y. m. d. H:i');
    }

    // ------------------------------------------------------------------ header actions

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')->label('Zárolás hozzáadása')->icon('heroicon-m-lock-closed')
                ->modalHeading('Időszak zárolása')
                ->modalDescription('Erre az időszakra az ügyfelek nem tudnak időpontot foglalni (pl. szabadság, külső program).')
                ->modalSubmitActionLabel('Zárolás')
                ->schema([
                    DatePicker::make('from')->label('Első nap')->required()->native(false)
                        ->displayFormat('Y. m. d.')->firstDayOfWeek(1)->closeOnDateSelection()->default(now('Europe/Budapest')->toDateString()),
                    Toggle::make('all_day')->label('Egész nap')->default(true)->live(),
                    DatePicker::make('until')->label('Utolsó nap')->native(false)->displayFormat('Y. m. d.')->firstDayOfWeek(1)
                        ->closeOnDateSelection()->afterOrEqual('from')->helperText('Több napos zároláshoz, pl. szabadság. Üresen: csak az első nap.')
                        ->visible(fn (Get $get) => (bool) $get('all_day')),
                    TextInput::make('start_time')->label('Kezdete')->placeholder('09:00')->required()
                        ->regex('/^([01]?\d|2[0-3]):[0-5]\d$/')->visible(fn (Get $get) => ! $get('all_day')),
                    TextInput::make('end_time')->label('Vége')->placeholder('12:00')->required()
                        ->regex('/^([01]?\d|2[0-3]):[0-5]\d$/')->visible(fn (Get $get) => ! $get('all_day')),
                    TextInput::make('title')->label('Megjegyzés (csak az admin látja)')->placeholder('pl. Szabadság')->maxLength(190),
                ])
                ->action(function (array $data): void {
                    try {
                        $from = substr((string) $data['from'], 0, 10);
                        if ($data['all_day'] ?? true) {
                            $until = substr((string) ($data['until'] ?: $from), 0, 10);
                            $start = "{$from} 00:00:00";
                            $end = (new \DateTimeImmutable($until))->modify('+1 day')->format('Y-m-d H:i:s');
                        } else {
                            $start = $from . ' ' . str_pad((string) $data['start_time'], 5, '0', STR_PAD_LEFT) . ':00';
                            $end = $from . ' ' . str_pad((string) $data['end_time'], 5, '0', STR_PAD_LEFT) . ':00';
                        }
                        BookingCalendar::addBlock($start, $end, (bool) ($data['all_day'] ?? true), (string) ($data['title'] ?? ''));
                        $this->week = $this->monday($from);
                        Notification::make()->title('Zárolás mentve')->success()->send();
                    } catch (Throwable $error) {
                        Notification::make()->title('A zárolás nem menthető')->body($error->getMessage())->danger()->send();
                    }
                }),

            Action::make('rules')->label('Foglalási szabályok')->icon('heroicon-m-adjustments-horizontal')->color('gray')
                ->modalHeading('Mikor lehet időpontot foglalni?')
                ->modalSubmitActionLabel('Mentés')
                ->fillForm(function (): array {
                    $rules = BookingCalendar::rules();

                    return [
                        'weekdays' => array_map('strval', $rules['weekdays']),
                        'slots' => $rules['slots'],
                        'duration' => $rules['duration'],
                        'days_ahead' => $rules['days_ahead'],
                        'min_notice_days' => (string) $rules['min_notice_days'],
                    ];
                })
                ->schema([
                    CheckboxList::make('weekdays')->label('Foglalható napok')->options(self::WEEKDAYS)
                        ->columns(4)->required(),
                    TagsInput::make('slots')->label('Kezdési időpontok')->placeholder('Új időpont, pl. 13:30, majd Enter')
                        ->helperText('Ezek közül választhat az ügyfél. ÓÓ:PP formátumban.')
                        ->required(),
                    TextInput::make('duration')->label('Egy konzultáció hossza')->numeric()->integer()->minValue(10)->maxValue(480)->suffix('perc')->required()
                        ->helperText('Ennyi ideig foglalt a kezdés után: egy Google-esemény vagy zárolás akkor is kizárja az időpontot, ha csak részben fedi.'),
                    TextInput::make('days_ahead')->label('Ennyi napra előre lehet foglalni')->numeric()->integer()->minValue(1)->maxValue(180)->suffix('nap')->required(),
                    Select::make('min_notice_days')->label('Legkorábbi foglalás')->native(false)->required()
                        ->options(['0' => 'Aznapra is (legalább 1 órával előtte)', '1' => 'Legkorábban holnapra', '2' => 'Legalább 2 nappal előre', '3' => 'Legalább 3 nappal előre', '7' => 'Legalább 1 héttel előre']),
                ])
                ->action(function (array $data): void {
                    try {
                        BookingCalendar::saveRules($data);
                        Notification::make()->title('Foglalási szabályok mentve')->body('A weboldal időpontfoglalója azonnal ezeket használja.')->success()->send();
                    } catch (\InvalidArgumentException $error) {
                        Notification::make()->title('Nem menthető')->body($error->getMessage())->danger()->send();
                    }
                }),

            Action::make('google')->label('Google Naptár')->icon('heroicon-m-link')->color('gray')
                ->modalHeading('Google Naptár összekapcsolása')
                ->modalDescription(new \Illuminate\Support\HtmlString(
                    'A Google Naptárban: <b>Beállítások</b> → bal oldalt válaszd ki a naptárat → <b>Naptár integrálása</b> → '
                    . '<b>Titkos cím iCal formátumban</b>. Ezt a címet másold be ide. A weboldal 10 percenként frissíti, '
                    . 'és a foglalt időpontokat az ügyfelek nem tudják kiválasztani. A címet ne oszd meg senkivel.'
                ))
                ->modalSubmitActionLabel('Mentés és frissítés')
                ->fillForm(function (): array {
                    $google = BookingCalendar::google();

                    return ['urls' => implode("\n", $google['urls']), 'allday_blocks' => $google['allday_blocks']];
                })
                ->schema([
                    Textarea::make('urls')->label('Titkos iCal-cím (soronként egy naptár, legfeljebb 5)')->rows(3)
                        ->placeholder('https://calendar.google.com/calendar/ical/…/private-…/basic.ics')
                        ->helperText('Üresen hagyva a Google-kapcsolat megszűnik.'),
                    Toggle::make('allday_blocks')->label('Az egész napos események (pl. szabadság) lezárják a napot')
                        ->helperText('A Google az egész napos eseményeket alapból „szabad”-nak jelöli; bekapcsolva ezek is foglaltnak számítanak.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $urls = preg_split('/\R/', (string) ($data['urls'] ?? '')) ?: [];
                        $google = BookingCalendar::saveGoogle($urls, (bool) ($data['allday_blocks'] ?? true));
                    } catch (\InvalidArgumentException $error) {
                        Notification::make()->title('Nem menthető')->body($error->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    if (! $google['urls']) {
                        Notification::make()->title('Google-kapcsolat kikapcsolva')->success()->send();

                        return;
                    }
                    $this->notifySync(BookingCalendar::sync());
                }),

            Action::make('sync')->label('Frissítés most')->icon('heroicon-m-arrow-path')->color('gray')
                ->visible(fn () => $this->hasGoogle())
                ->action(fn () => $this->notifySync(BookingCalendar::sync())),

            Action::make('upload')->label('.ics feltöltése')->icon('heroicon-m-arrow-up-tray')->color('gray')
                ->modalHeading('Naptárfájl feltöltése')
                ->modalDescription('Ha nem szeretnéd a titkos címet megadni: a Google Naptárban Beállítások → Importálás és exportálás → Exportálás. '
                    . 'A letöltött ZIP-ből a .ics fájlt töltsd fel. Egy új feltöltés lecseréli az előzőt; ez nem frissül magától.')
                ->modalSubmitActionLabel('Feltöltés')
                ->schema([
                    FileUpload::make('file')->label('.ics fájl')->required()->storeFiles(false)->maxSize(10240)
                        ->acceptedFileTypes(['text/calendar', 'application/ics', 'text/x-vcalendar', 'text/plain', 'application/octet-stream']),
                ])
                ->action(function (array $data): void {
                    $file = is_array($data['file'] ?? null) ? reset($data['file']) : ($data['file'] ?? null);
                    try {
                        if (! $file || ! method_exists($file, 'get')) {
                            throw new \InvalidArgumentException('A fájl nem érkezett meg, próbáld újra.');
                        }
                        $count = BookingCalendar::importUpload((string) $file->get(), (string) $file->getClientOriginalName());
                        Notification::make()->title("{$count} esemény betöltve")->body('A naptárban foglalt időpontokat az ügyfelek nem választhatják.')->success()->send();
                    } catch (Throwable $error) {
                        Notification::make()->title('A feltöltés nem sikerült')->body($error->getMessage())->danger()->persistent()->send();
                    }
                }),

            Action::make('clearUpload')->label('Feltöltött naptár törlése')->icon('heroicon-m-trash')->color('danger')->outlined()
                ->visible(fn () => $this->hasUpload())
                ->requiresConfirmation()
                ->modalDescription('A feltöltött .ics fájl eseményei törlődnek, az általuk foglalt időpontok újra szabadok lesznek.')
                ->action(function (): void {
                    BookingCalendar::clearUpload();
                    Notification::make()->title('Feltöltött naptár törölve')->success()->send();
                }),
        ];
    }

    private function hasGoogle(): bool
    {
        try {
            return (bool) BookingCalendar::google()['urls'];
        } catch (Throwable) {
            return false;
        }
    }

    private function hasUpload(): bool
    {
        try {
            return ! empty(BookingCalendar::google()['upload']['file']);
        } catch (Throwable) {
            return false;
        }
    }

    private function notifySync(array $state): void
    {
        if ($state['ok'] ?? false) {
            Notification::make()->title('Google Naptár frissítve')
                ->body(($state['count'] ?? 0) . ' esemény betöltve a következő 200 napra.')
                ->success()->send();

            return;
        }
        Notification::make()->title('A Google Naptár nem frissíthető')
            ->body(($state['error'] ?? 'Ismeretlen hiba') . ' A korábban betöltött események érvényben maradnak.')
            ->danger()->persistent()->send();
    }
}
