<?php

namespace Tests\Feature;

use App\Filament\Pages\FullPageTexts;
use App\Models\AdminUser;
use App\Support\PageLayouts;
use App\Support\PageTexts;
use App\Support\SiteContent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class InlineTextEditorTest extends TestCase
{
    private AdminUser $admin;

    private string $plainKey;

    private string $richKey;

    protected function setUp(): void
    {
        parent::setUp();
        config(['goldentor.mail_enabled' => false]);
        DB::unprepared((string) file_get_contents(base_path('tests/fixtures/legacy_schema.sqlite.sql')));
        Artisan::call('migrate', ['--force' => true]);
        $this->admin = AdminUser::query()->create(['name' => 'Szerkesztő', 'email' => 'editor@example.test', 'password' => 'nagyon-titkos-jelszo']);

        $manifest = PageTexts::manifest();
        $this->assertGreaterThan(100, count($manifest), 'page-text.json manifest missing – run scripts/tag-editable-text.py');
        $this->plainKey = (string) collect($manifest)->search(fn ($entry) => ! $entry['rich'] && $entry['page'] === 'index');
        $this->richKey = (string) collect($manifest)->search(fn ($entry) => $entry['rich'] && $entry['page'] === 'index');
    }

    public function test_guests_get_no_editor_session_and_cannot_save(): void
    {
        $this->getJson('/admin/inline-editor/session')->assertOk()->assertJson(['editor' => false, 'csrf' => null]);
        $this->postJson('/admin/inline-editor/texts', ['changes' => [$this->plainKey => ['value' => 'x']]])->assertUnauthorized();
        $this->assertNull(SiteContent::get(PageTexts::CONTENT_KEY, 'hu'));
    }

    public function test_admins_get_a_session_and_a_readable_hint_cookie(): void
    {
        $this->actingAs($this->admin);
        $session = $this->getJson('/admin/inline-editor/session')->assertOk()->assertJson(['editor' => true])->json();
        $this->assertNotEmpty($session['csrf']);
        $this->get('/admin')->assertOk()->assertCookie('gt_editor', '1', false);
    }

    public function test_saving_sanitises_rich_text_and_drops_unchanged_values(): void
    {
        $this->actingAs($this->admin);
        $manifest = PageTexts::manifest();

        $response = $this->postJson('/admin/inline-editor/texts', [
            'page' => '/index.html',
            'changes' => [
                $this->plainKey => ['value' => "  Új   szöveg <b>tag nélkül</b>  ", 'rich' => false],
                $this->richKey => ['value' => 'Új cím<br><em>kiemelve</em><script>alert(1)</script><a href="javascript:x()">rossz</a>', 'rich' => true],
            ],
        ])->assertOk()->json();
        $this->assertEqualsCanonicalizing([$this->plainKey, $this->richKey], $response['saved']);

        $saved = SiteContent::get(PageTexts::CONTENT_KEY, 'hu');
        $this->assertSame('Új szöveg tag nélkül', $saved[$this->plainKey]['v']);
        $this->assertSame('Új cím<br><em>kiemelve</em><a>rossz</a>', $saved[$this->richKey]['v']);
        $this->assertSame('editor@example.test', $saved[$this->plainKey]['by']);

        // Writing back the original text removes the override; reset removes it too.
        $this->postJson('/admin/inline-editor/texts', ['changes' => [
            $this->plainKey => ['value' => $manifest[$this->plainKey]['text']],
            $this->richKey => ['reset' => true],
        ]])->assertOk()->assertJson(['saved' => []]);
        $this->assertSame([], SiteContent::get(PageTexts::CONTENT_KEY, 'hu'));
    }

    public function test_invalid_ids_are_rejected(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/admin/inline-editor/texts', ['changes' => ['../etc' => ['value' => 'x']]])->assertStatus(422);
        $this->postJson('/admin/inline-editor/texts', ['changes' => []])->assertStatus(422);
    }

    public function test_full_page_texts_screen_lists_and_saves_texts(): void
    {
        $this->actingAs($this->admin);
        $this->get(FullPageTexts::getUrl(['oldal' => 'footer']))->assertOk();
        $this->get(FullPageTexts::getUrl(['oldal' => 'index', 'csak' => 'modositott']))->assertOk();

        $footerKey = (string) collect(PageTexts::manifest())->search(fn ($entry) => $entry['page'] === 'footer' && ! $entry['rich']);
        Livewire::withQueryParams(['oldal' => 'footer'])
            ->test(FullPageTexts::class)
            ->assertSet('selectedPage', 'footer')
            ->set('data.' . FullPageTexts::field($footerKey), 'Golden Tor Consulting Kft.')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Golden Tor Consulting Kft.', SiteContent::get(PageTexts::CONTENT_KEY, 'hu')[$footerKey]['v']);

        // Emptying a field restores the original text.
        Livewire::withQueryParams(['oldal' => 'footer'])
            ->test(FullPageTexts::class)
            ->set('data.' . FullPageTexts::field($footerKey), '')
            ->call('save');
        $this->assertArrayNotHasKey($footerKey, SiteContent::get(PageTexts::CONTENT_KEY, 'hu'));
    }

    public function test_layout_changes_are_validated_and_stored(): void
    {
        $this->postJson('/admin/inline-editor/layout', ['layouts' => ['index' => []]])->assertUnauthorized();
        $this->actingAs($this->admin);

        $layout = [
            'order' => ['index.main' => ['index.s2', 'index.s1'], 'index.l3' => ['index.i9', 'index.c1', 'index.i8']],
            'hidden' => ['index.i3', 'index.12'],
            'clones' => ['index.c1' => ['source' => 'index.i8', 'list' => 'index.l3']],
            'images' => ['index.m2' => ['src' => '/assets/uploads/site/2026/10/kep.webp', 'alt' => 'Szolgáltatás']],
            'links' => ['index.k7' => ['href' => 'idopont.html'], 'index.k8' => ['href' => 'tel:+36 20 745 6490']],
        ];
        $this->postJson('/admin/inline-editor/layout', ['layouts' => ['index' => $layout]])->assertOk();
        $stored = PageLayouts::forPrefix('index');
        $this->assertSame(['index.s2', 'index.s1'], $stored['order']['index.main']);
        $this->assertSame(['source' => 'index.i8', 'list' => 'index.l3'], $stored['clones']['index.c1']);
        $this->assertSame('tel:+36207456490', $stored['links']['index.k8']['href']);

        foreach ([
            ['links' => ['index.k1' => ['href' => 'javascript:alert(1)']]],
            ['images' => ['index.m1' => ['src' => 'data:image/png;base64,AAAA']]],
            ['hidden' => ['footer.i1']],
            ['order' => ['index.main' => ['index.i1']]],
        ] as $bad) {
            $this->postJson('/admin/inline-editor/layout', ['layouts' => ['index' => $bad]])->assertStatus(422);
        }
        // Rejected requests leave the stored layout untouched.
        $this->assertSame($stored, PageLayouts::forPrefix('index'));

        // An empty layout removes the prefix again.
        $this->postJson('/admin/inline-editor/layout', ['layouts' => ['index' => []]])->assertOk();
        $this->assertSame([], PageLayouts::forPrefix('index'));
    }

    public function test_texts_of_copied_items_use_their_own_ids(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/admin/inline-editor/texts', ['changes' => [$this->plainKey . '@c1' => ['value' => 'A másolat saját szövege']]])
            ->assertOk()->assertJson(['saved' => [$this->plainKey . '@c1']]);
        $saved = SiteContent::get(PageTexts::CONTENT_KEY, 'hu');
        $this->assertSame('A másolat saját szövege', $saved[$this->plainKey . '@c1']['v']);
        $this->assertSame(PageTexts::manifest()[$this->plainKey]['text'], $saved[$this->plainKey . '@c1']['orig']);
    }

    public function test_image_upload_stores_safe_images_only(): void
    {
        Storage::fake('site');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $this->post('/admin/inline-editor/upload', ['image' => UploadedFile::fake()->createWithContent('kep.png', $png)], ['Accept' => 'application/json'])
            ->assertUnauthorized();

        $this->actingAs($this->admin);
        $url = $this->post('/admin/inline-editor/upload', ['image' => UploadedFile::fake()->createWithContent('kep.png', $png)], ['Accept' => 'application/json'])
            ->assertOk()->json('url');
        $this->assertMatchesRegularExpression('#^/assets/uploads/site/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#', $url);
        Storage::disk('site')->assertExists(ltrim($url, '/'));

        $svg = UploadedFile::fake()->createWithContent('rossz.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/admin/inline-editor/upload', ['image' => $svg], ['Accept' => 'application/json'])->assertStatus(422);
        $php = UploadedFile::fake()->createWithContent('rossz.php', '<?php echo 1;');
        $this->post('/admin/inline-editor/upload', ['image' => $php], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_layout_can_be_reset_from_the_admin(): void
    {
        $this->actingAs($this->admin);
        PageLayouts::store(['footer' => PageLayouts::clean('footer', ['hidden' => ['footer.3']])]);

        Livewire::withQueryParams(['oldal' => 'footer'])
            ->test(FullPageTexts::class)
            ->callAction('resetLayout');

        $this->assertSame([], PageLayouts::forPrefix('footer'));
    }
}
