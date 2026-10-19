<?php

namespace Tests\Feature;

use App\Filament\Pages\BlogPosts;
use App\Filament\Pages\ExpertProfiles;
use App\Filament\Pages\SiteDesign;
use App\Filament\Pages\SiteTexts;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\BookingResource\Pages\ManageBookings;
use App\Filament\Resources\CalculationResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\FeedbackResource;
use App\Models\AdminUser;
use App\Models\Booking;
use App\Models\Customer;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Runs in CI before every deployment: renders every admin screen against a copy of the
 * legacy schema and exercises the main write paths. A failing test blocks the upload.
 */
class AdminPanelSmokeTest extends TestCase
{
    private AdminUser $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        DB::unprepared((string) file_get_contents(base_path('tests/fixtures/legacy_schema.sqlite.sql')));
        Artisan::call('migrate', ['--force' => true]);

        $this->admin = AdminUser::query()->create(['name' => 'Teszt Admin', 'email' => 'admin@example.test', 'password' => 'nagyon-titkos-jelszo']);

        $now = now('UTC')->format('Y-m-d H:i:s');

        $customerId = DB::table('users')->insertGetId([
            'name' => 'Kiss Anna', 'email' => 'anna@example.test', 'phone' => '+36 30 123 4567',
            'password_hash' => password_hash('regi-jelszo-123', PASSWORD_DEFAULT), 'created_at' => $now,
        ]);
        $this->customer = Customer::query()->findOrFail($customerId);

        DB::table('calculations')->insert([
            ['user_id' => $this->customer->id, 'calculator_type' => 'loan', 'created_at' => $now,
                'inputs_json' => json_encode(['amount' => 30000000, 'rate' => 7.5, 'years' => 20]),
                'results_json' => json_encode(['payment' => 241678.1, 'total' => 58002710, 'cost' => 28002710])],
            ['user_id' => $this->customer->id, 'calculator_type' => 'reserve', 'created_at' => $now,
                'inputs_json' => json_encode(['expense' => 600000, 'dependants' => 2, 'months' => 9, 'current' => 2500000]),
                'results_json' => json_encode(['target' => 5940000, 'coverage' => 4.17, 'gap' => 3440000])],
        ]);
        DB::table('feedback')->insert(['user_id' => $this->customer->id, 'expert' => 'Szakértő', 'rating' => 5, 'message' => 'Nagyon hasznos volt.', 'created_at' => $now]);
        DB::table('contacts')->insert(['name' => 'Nagy Béla', 'email' => 'bela@example.test', 'phone' => '', 'topic' => 'Befektetés', 'message' => 'Visszahívást kérek.', 'status' => 'new', 'created_at' => $now]);

        $date = now('Europe/Budapest')->addWeekday()->toDateString();
        DB::table('bookings')->insert([
            'public_token' => str_repeat('a', 48), 'created_at' => $now, 'name' => 'Kiss Anna', 'email' => 'anna@example.test',
            'phone' => '', 'topic' => 'Befektetés', 'meeting' => 'Online konzultáció', 'booking_date' => $date,
            'booking_time' => '09:00:00', 'message' => 'Teszt', 'status' => 'requested', 'active_slot' => $date . ' 09:00',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_guest_is_redirected_to_login_and_login_page_renders(): void
    {
        auth()->logout();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Golden Tor Admin', false);
    }

    public function test_every_admin_screen_renders(): void
    {
        $urls = [
            '/admin',
            BookingResource::getUrl('index'),
            ContactResource::getUrl('index'),
            CustomerResource::getUrl('index'),
            CustomerResource::getUrl('view', ['record' => $this->customer]),
            CustomerResource::getUrl('edit', ['record' => $this->customer]),
            FeedbackResource::getUrl('index'),
            CalculationResource::getUrl('index'),
            BlogPosts::getUrl(),
            SiteTexts::getUrl(),
            SiteDesign::getUrl(),
            ExpertProfiles::getUrl(),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_setup_page_is_hidden_once_an_admin_exists(): void
    {
        $this->get('/admin/setup')->assertNotFound();
    }

    public function test_confirming_a_booking_updates_status_and_slot(): void
    {
        $booking = Booking::query()->firstOrFail();

        Livewire::test(ManageBookings::class)
            ->callTableAction('confirm', $booking)
            ->assertHasNoTableActionErrors();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($booking->booking_date->format('Y-m-d') . ' 09:00', DB::table('bookings')->value('active_slot'));
        $this->assertSame('09:00:00', DB::table('bookings')->value('booking_time'));
    }

    public function test_double_booking_is_rejected(): void
    {
        $existing = Booking::query()->firstOrFail();

        Livewire::test(ManageBookings::class)
            ->callAction('create', [
                'name' => 'Második Ügyfél', 'email' => 'masik@example.test', 'topic' => 'Ingatlan',
                'meeting' => 'Telefonos egyeztetés', 'booking_date' => $existing->booking_date->format('Y-m-d'),
                'booking_time' => '09:00', 'status' => 'requested',
            ])
            ->assertHasActionErrors(['booking_time']);

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_blog_page_saves_payload_in_the_legacy_format(): void
    {
        Livewire::test(BlogPosts::class)
            ->assertFormSet(['language' => 'hu'])
            ->set('data.posts', [
                'item-1' => ['status' => 'published', 'title' => 'Új cikk az öröklésről', 'slug' => '', 'category' => '',
                    'date' => '2026. október', 'image' => '', 'image_upload' => null, 'excerpt' => 'Rövid bevezető',
                    'points' => ['Első pont'], 'body' => "Első bekezdés.\n\nMásodik bekezdés."],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $payload = SiteContent::get('blog_posts', 'hu');
        $this->assertSame('uj-cikk-az-orokleserol', $payload['posts'][0]['slug']);
        $this->assertSame(['Első bekezdés.', 'Második bekezdés.'], $payload['posts'][0]['body']);
        $this->assertSame('Golden Tor', $payload['posts'][0]['category']);
    }

    public function test_site_texts_and_design_save(): void
    {
        Livewire::test(SiteTexts::class)
            ->set('data.pages.home.title', 'Új főcím')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Új főcím', SiteContent::get('site_texts', 'hu')['home']['title']);

        Livewire::test(SiteDesign::class)
            ->set('data.navy', '#101010')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('#101010', SiteContent::get('site_design', 'hu')['navy']);
    }

    public function test_customer_password_reset_produces_a_php_compatible_hash(): void
    {
        Livewire::test(CustomerResource\Pages\ListCustomers::class)
            ->callTableAction('setPassword', $this->customer, ['password' => 'uj-jelszo-12345', 'password_confirmation' => 'uj-jelszo-12345'])
            ->assertHasNoTableActionErrors();

        $hash = DB::table('users')->where('id', $this->customer->id)->value('password_hash');
        $this->assertTrue(password_verify('uj-jelszo-12345', $hash));
    }
}
