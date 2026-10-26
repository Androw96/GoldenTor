<?php

namespace Tests\Feature;

use App\Filament\Pages\BookingCalendarPage;
use App\Models\AdminUser;
use App\Support\BookingCalendar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin calendar (Ügyfélkapcsolat → Naptár) and the shared unas/calendar.php rules. */
class BookingCalendarTest extends TestCase
{
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();
        config(['goldentor.mail_enabled' => false]);
        DB::unprepared((string) file_get_contents(base_path('tests/fixtures/legacy_schema.sqlite.sql')));
        Artisan::call('migrate', ['--force' => true]);
        $this->actingAs(AdminUser::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'nagyon-titkos-jelszo']));

        // A weekday at least two days ahead, so the default "from tomorrow" rule always allows it.
        $day = now('Europe/Budapest')->addDays(2);
        while ($day->isWeekend()) {
            $day = $day->addDay();
        }
        $this->day = $day->toDateString();
    }

    private function slotState(string $time): string
    {
        return collect(BookingCalendar::occupancy($this->day, $this->day)[0]['slots'])->firstWhere('time', $time)['state'];
    }

    public function test_page_renders_bookings_blocks_and_google_events(): void
    {
        DB::table('bookings')->insert([
            'public_token' => str_repeat('b', 48), 'created_at' => now('UTC'), 'name' => 'Kovács Péter', 'email' => 'p@example.test',
            'phone' => '', 'topic' => 'Ingatlan', 'meeting' => 'Online konzultáció', 'booking_date' => $this->day,
            'booking_time' => '11:00:00', 'message' => '', 'status' => 'confirmed', 'active_slot' => $this->day . ' 11:00',
        ]);
        BookingCalendar::addBlock("{$this->day} 09:00:00", "{$this->day} 09:45:00", false, 'Zárolva');
        $ics = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:x\r\nDTSTART;TZID=Europe/Budapest:" . str_replace('-', '', $this->day) . "T133000\r\nDTEND;TZID=Europe/Budapest:" . str_replace('-', '', $this->day) . "T143000\r\nSUMMARY:Fogorvos\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $this->assertSame(1, BookingCalendar::importUpload($ics, 'sajat.ics'));

        $this->assertSame('blocked', $this->slotState('09:00'));
        $this->assertSame('booked', $this->slotState('11:00'));
        $this->assertSame('busy', $this->slotState('14:00'));
        $this->assertSame('free', $this->slotState('16:00'));

        $this->get(BookingCalendarPage::getUrl(['het' => $this->day]))
            ->assertOk()
            ->assertSee('Kovács Péter')
            ->assertSee('Fogorvos')
            ->assertSee('sajat.ics')
            ->assertSee('Foglalási szabályok');
    }

    public function test_clicking_a_slot_blocks_and_frees_it(): void
    {
        $page = Livewire::test(BookingCalendarPage::class)->assertOk();

        $page->call('toggleSlot', $this->day, '14:00');
        $this->assertSame('blocked', $this->slotState('14:00'));
        $this->assertNotNull(\GoldenTorCalendar::slotProblem(BookingCalendar::pdo(), $this->day, '14:00'));

        $page->call('toggleSlot', $this->day, '14:00');
        $this->assertSame('free', $this->slotState('14:00'));
        $this->assertNull(\GoldenTorCalendar::slotProblem(BookingCalendar::pdo(), $this->day, '14:00'));

        $page->call('toggleDay', $this->day);
        $this->assertSame(['blocked'], array_values(array_unique(array_column(BookingCalendar::occupancy($this->day, $this->day)[0]['slots'], 'state'))));
        $this->assertCount(1, BookingCalendar::manualBlocks());

        // A slot inside a whole-day block is not freed one by one.
        $page->call('toggleSlot', $this->day, '09:00');
        $this->assertSame('blocked', $this->slotState('09:00'));

        $page->call('toggleDay', $this->day);
        $this->assertSame('free', $this->slotState('09:00'));
        $this->assertCount(0, BookingCalendar::manualBlocks());
    }

    public function test_week_navigation(): void
    {
        $monday = now('Europe/Budapest')->startOfWeek()->toDateString();
        Livewire::test(BookingCalendarPage::class)
            ->assertSet('week', $monday)
            ->call('nextWeek')
            ->assertSet('week', now('Europe/Budapest')->startOfWeek()->addWeek()->toDateString())
            ->call('previousWeek')->call('previousWeek')
            ->assertSet('week', now('Europe/Budapest')->startOfWeek()->subWeek()->toDateString())
            ->call('thisWeek')
            ->assertSet('week', $monday);
    }

    public function test_block_action_creates_a_multi_day_block(): void
    {
        $until = now('Europe/Budapest')->addDays(9)->toDateString();
        Livewire::test(BookingCalendarPage::class)
            ->callAction('block', ['from' => $this->day, 'all_day' => true, 'until' => $until, 'title' => 'Szabadság'])
            ->assertHasNoActionErrors();

        $blocks = BookingCalendar::manualBlocks();
        $this->assertCount(1, $blocks);
        $this->assertSame('Szabadság', $blocks[0]['title']);
        $this->assertSame($this->day . ' 00:00:00', $blocks[0]['starts_at']);
        $this->assertSame(now('Europe/Budapest')->addDays(10)->toDateString() . ' 00:00:00', $blocks[0]['ends_at']);
        $this->assertSame('blocked', $this->slotState('09:00'));
    }

    public function test_rules_action_changes_the_bookable_slots(): void
    {
        Livewire::test(BookingCalendarPage::class)
            ->callAction('rules', [
                'weekdays' => ['1', '2', '3', '4', '5', '6'], 'slots' => ['10:00', '8:30'],
                'duration' => 60, 'days_ahead' => 30, 'min_notice_days' => '1',
            ])
            ->assertHasNoActionErrors();

        $rules = BookingCalendar::rules();
        $this->assertSame(['08:30', '10:00'], $rules['slots']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $rules['weekdays']);
        $this->assertSame(60, $rules['duration']);
        $this->assertSame(['08:30' => '08:30', '10:00' => '10:00'], BookingCalendar::slotOptions());
        $this->assertNull(\GoldenTorCalendar::slotProblem(BookingCalendar::pdo(), $this->day, '10:00'));
        $this->assertNotNull(\GoldenTorCalendar::slotProblem(BookingCalendar::pdo(), $this->day, '09:00'));

        // Invalid time: nothing is saved.
        Livewire::test(BookingCalendarPage::class)
            ->callAction('rules', ['weekdays' => ['1'], 'slots' => ['25:99'], 'duration' => 45, 'days_ahead' => 21, 'min_notice_days' => '1']);
        $this->assertSame(['08:30', '10:00'], BookingCalendar::rules()['slots']);
    }

    public function test_google_settings_validate_the_address_and_can_be_cleared(): void
    {
        Livewire::test(BookingCalendarPage::class)
            ->callAction('google', ['urls' => 'http://nem-biztonsagos.example/naptar.ics', 'allday_blocks' => true]);
        $this->assertSame([], BookingCalendar::google()['urls']);

        BookingCalendar::saveGoogle(['webcal://calendar.google.com/calendar/ical/a%40gmail.com/private-x/basic.ics'], false);
        $google = BookingCalendar::google();
        $this->assertSame(['https://calendar.google.com/calendar/ical/a%40gmail.com/private-x/basic.ics'], $google['urls']);
        $this->assertFalse($google['allday_blocks']);

        Livewire::test(BookingCalendarPage::class)
            ->callAction('google', ['urls' => '', 'allday_blocks' => true])
            ->assertHasNoActionErrors();
        $this->assertSame([], BookingCalendar::google()['urls']);
    }

    public function test_booking_form_offers_the_configured_slots(): void
    {
        BookingCalendar::saveRules(['weekdays' => [1, 2, 3, 4, 5], 'slots' => ['08:00', '12:30'], 'duration' => 30, 'days_ahead' => 14, 'min_notice_days' => 1]);

        Livewire::test(\App\Filament\Resources\BookingResource\Pages\ManageBookings::class)
            ->callAction('create', [
                'name' => 'Új Ügyfél', 'email' => 'uj@example.test', 'topic' => 'Ingatlan',
                'meeting' => 'Telefonos egyeztetés', 'booking_date' => $this->day,
                'booking_time' => '12:30', 'status' => 'requested',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('12:30:00', DB::table('bookings')->value('booking_time'));
        $this->assertSame('booked', collect(BookingCalendar::occupancy($this->day, $this->day)[0]['slots'])->firstWhere('time', '12:30')['state']);
    }
}
