<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Services\Updater\Updater;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Runs real updates against a throwaway copy of a "live site" in a temp folder,
 * with GitHub faked. Checks the files that must change do, and the client's
 * own files (.env, storage, custom public files) never do.
 */
class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmp;

    protected string $site;

    protected string $zipBytes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
        $this->actingAsAdmin();

        $this->tmp = sys_get_temp_dir().'/updater-test-'.Str::random(8);
        $this->site = $this->tmp.'/site';

        // The "live site", version 1.0.0.
        $this->write('VERSION', "1.0.0\n");
        $this->write('artisan', 'old artisan');
        $this->write('app/Old.php', 'old');
        $this->write('vendor/autoload.php', 'old vendor');
        $this->write('bootstrap/app.php', 'old bootstrap');
        $this->write('.env', 'APP_KEY=client-secret');
        $this->write('storage/app/public/photo.jpg', 'client upload');
        $this->write('public/build/manifest.json', '{"old":true}');
        $this->write('public/index.php', 'custom public_html index');
        $this->write('public/.htaccess', '# cPanel PHP handler');

        // The release package, version 1.1.0.
        $this->zipBytes = $this->makeZip([
            'VERSION' => "1.1.0\n",
            'artisan' => 'new artisan',
            'app/New.php' => 'new',
            'vendor/autoload.php' => 'new vendor',
            'bootstrap/app.php' => 'new bootstrap',
            '.env' => 'APP_KEY=from-package',
            'storage/app/public/photo.jpg' => 'package file',
            'public/build/manifest.json' => '{"new":true}',
            'public/index.php' => 'stock index',
            'public/.htaccess' => 'stock htaccess',
            'public/robots.txt' => 'new file',
        ]);

        config(['updater.repository' => 'acme/catalog', 'updater.token' => 'secret-token']);
        $this->app->instance(Updater::class, new Updater($this->site, $this->site.'/public', $this->tmp.'/work'));
        $this->fakeGithub('v1.1.0', hash('sha256', $this->zipBytes));
    }

    protected function tearDown(): void
    {
        app(Updater::class)->bringOnline();
        $this->rmrf($this->tmp);
        parent::tearDown();
    }

    public function test_a_full_update_replaces_code_and_keeps_client_files(): void
    {
        $this->get('/admin/updates')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Updates')
            ->where('status.current', '1.0.0')
            ->where('status.latest', '1.1.0')
            ->where('status.available', true)
            ->where('source.effective', 'acme/catalog'));

        foreach (Updater::STEPS as $step) {
            $this->postJson("/admin/updates/step/{$step}")->assertOk()->assertJsonStructure(['message']);
        }

        // Code replacedâ€¦
        $this->assertSame("1.1.0\n", $this->read('VERSION'));
        $this->assertSame('new vendor', $this->read('vendor/autoload.php'));
        $this->assertFileExists($this->site.'/app/New.php');
        $this->assertFileDoesNotExist($this->site.'/app/Old.php');
        $this->assertSame('{"new":true}', $this->read('public/build/manifest.json'));
        $this->assertSame('new file', $this->read('public/robots.txt'));

        // â€¦client files untouched.
        $this->assertSame('APP_KEY=client-secret', $this->read('.env'));
        $this->assertSame('client upload', $this->read('storage/app/public/photo.jpg'));
        $this->assertSame('custom public_html index', $this->read('public/index.php'));
        $this->assertSame('# cPanel PHP handler', $this->read('public/.htaccess'));

        // Online again, work files cleaned up, a restorable backup with a database dump kept.
        $this->assertFalse(app(Updater::class)->isDown());
        $this->assertNull(app(Updater::class)->pending());
        $this->assertDirectoryDoesNotExist($this->tmp.'/work/staging');
        $backups = app(Updater::class)->backups();
        $this->assertCount(1, $backups);
        $this->assertSame('1.0.0', $backups[0]['version']);
        $this->assertSame('1.1.0', $backups[0]['replaced_by']);
        $this->get("/admin/updates/backups/{$backups[0]['id']}/database")->assertOk()->assertDownload();

        // Restore puts the old version back exactly.
        $this->postJson("/admin/updates/backups/{$backups[0]['id']}/restore")->assertOk();
        $this->assertSame("1.0.0\n", $this->read('VERSION'));
        $this->assertSame('old vendor', $this->read('vendor/autoload.php'));
        $this->assertFileExists($this->site.'/app/Old.php');
        $this->assertFileDoesNotExist($this->site.'/app/New.php');
        $this->assertFileDoesNotExist($this->site.'/public/robots.txt');
        $this->assertSame('{"old":true}', $this->read('public/build/manifest.json'));
        $this->assertSame('APP_KEY=client-secret', $this->read('.env'));
        $this->assertFalse(app(Updater::class)->isDown());

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_a_failed_install_puts_every_file_back(): void
    {
        $failing = new class($this->site, $this->site.'/public', $this->tmp.'/work') extends Updater
        {
            protected function move(string $from, string $to): void
            {
                if (str_ends_with($from, '/staging/vendor')) {
                    throw new RuntimeException('disk full');
                }
                parent::move($from, $to);
            }
        };
        $this->app->instance(Updater::class, $failing);

        foreach (['download', 'backup', 'extract'] as $step) {
            $this->postJson("/admin/updates/step/{$step}")->assertOk();
        }
        $this->postJson('/admin/updates/step/install')
            ->assertStatus(422)
            ->assertJson(['message' => 'Installing failed, so the previous version was put back. Nothing was changed. (disk full)']);

        $this->assertSame("1.0.0\n", $this->read('VERSION'));
        $this->assertSame('old vendor', $this->read('vendor/autoload.php'));
        $this->assertSame('old artisan', $this->read('artisan'));
        $this->assertFileExists($this->site.'/app/Old.php');
        $this->assertFileDoesNotExist($this->site.'/app/New.php');
        $this->assertSame('{"old":true}', $this->read('public/build/manifest.json'));
        $this->assertFalse($failing->isDown());
        // Can be retried: still waiting at the install step.
        $this->assertSame('extract', $failing->pending()['step']);
    }

    public function test_a_damaged_download_is_rejected(): void
    {
        $this->fakeGithub('v1.1.0', str_repeat('0', 64));

        $this->postJson('/admin/updates/step/download')->assertStatus(422)->assertJsonFragment(['message' => 'The downloaded file is damaged (checksum mismatch). Please try again.']);
        $this->assertNull(app(Updater::class)->pending());
    }

    public function test_steps_cannot_run_out_of_order(): void
    {
        $this->postJson('/admin/updates/step/install')->assertStatus(422)->assertJsonFragment(['message' => 'The update steps ran out of order. Please start the update again.']);
        $this->assertSame("1.0.0\n", $this->read('VERSION'));
    }

    public function test_up_to_date_and_error_states(): void
    {
        $this->fakeGithub('v1.0.0', null);
        $this->get('/admin/updates?refresh=1')->assertInertia(fn (Assert $p) => $p->where('status.available', false)->where('status.error', null));

        Http::swap(new HttpFactory);
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
        $this->get('/admin/updates?refresh=1')->assertInertia(fn (Assert $p) => $p->where('status.available', false)->where('status.error', fn ($e) => str_contains($e, 'No published release found')));

        config(['updater.repository' => null]);
        $this->get('/admin/updates?refresh=1')->assertInertia(fn (Assert $p) => $p->where('status.error', fn ($e) => str_contains($e, 'No update source')));
    }

    public function test_update_source_is_saved_with_an_encrypted_token(): void
    {
        $this->put('/admin/updates/source', ['repository' => 'https://github.com/my-co/shop.git', 'token' => 'ghp_abc'])->assertRedirect();

        $s = BusinessSetting::query()->first();
        $this->assertSame('my-co/shop', $s->update_repository);
        $this->assertSame('ghp_abc', $s->update_token);
        $this->assertStringNotContainsString('ghp_abc', (string) \DB::table('business_settings')->value('update_token'));

        $this->get('/admin/updates')->assertInertia(fn (Assert $p) => $p->where('source.has_token', true)->missing('source.token'));

        $this->put('/admin/updates/source', ['repository' => 'not a repo'])->assertSessionHasErrors('repository');
    }

    public function test_unsafe_package_paths_are_rejected(): void
    {
        $this->zipBytes = $this->makeZip(['VERSION' => '1.1.0', '../evil.php' => 'x']);
        $this->fakeGithub('v1.1.0', hash('sha256', $this->zipBytes));

        $this->postJson('/admin/updates/step/download')->assertOk();
        $this->postJson('/admin/updates/step/backup')->assertOk();
        $this->postJson('/admin/updates/step/extract')->assertStatus(422)->assertJsonFragment(['message' => 'The update package contains unsafe file paths and was rejected.']);
        $this->assertFileDoesNotExist($this->tmp.'/work/evil.php');
    }

    public function test_the_update_page_works_during_maintenance_but_the_shop_does_not(): void
    {
        $this->artisan('down');

        $this->get('/admin/updates')->assertOk();
        $this->get('/')->assertStatus(503);

        $this->post('/admin/updates/online')->assertRedirect();
        $this->get('/')->assertOk();
    }

    public function test_versions_compare_numerically(): void
    {
        $this->assertTrue(Updater::isNewer('v1.10.0', '1.9.2'));
        $this->assertFalse(Updater::isNewer('1.0.0', 'v1.0.0'));
        $this->assertSame('owner/repo', Updater::cleanRepository('github.com/owner/repo/'));
        $this->assertNull(Updater::cleanRepository('owner'));
    }

    /* helpers */

    protected function fakeGithub(string $tag, ?string $digest): void
    {
        // Earlier fakes take priority over later ones, so start from a clean client.
        Http::swap(new HttpFactory);
        Http::fake([
            'api.github.com/repos/acme/catalog/releases/latest' => Http::response([
                'tag_name' => $tag,
                'name' => $tag,
                'body' => "## Changes\n- Faster pages",
                'published_at' => '2026-11-01T10:00:00Z',
                'html_url' => "https://github.com/acme/catalog/releases/{$tag}",
                'assets' => [[
                    'id' => 1,
                    'name' => "catalog-{$tag}.zip",
                    'size' => strlen($this->zipBytes),
                    'url' => 'https://api.github.com/repos/acme/catalog/releases/assets/1',
                    'digest' => $digest ? "sha256:{$digest}" : null,
                ]],
            ]),
            // Like the real API: the file only when octet-stream is the *only* accepted type,
            // otherwise the asset's JSON description.
            'api.github.com/repos/acme/catalog/releases/assets/1' => fn ($request) => $request->header('Accept') === ['application/octet-stream']
                ? Http::response($this->zipBytes, 200, ['Content-Type' => 'application/octet-stream'])
                : Http::response(['id' => 1, 'name' => "catalog-{$tag}.zip", 'state' => 'uploaded']),
        ]);
        cache()->forget('updater.latest');
    }

    protected function makeZip(array $files): string
    {
        $path = $this->tmp.'/build-'.Str::random(4).'.zip';
        @mkdir($this->tmp, 0777, true);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    protected function write(string $relative, string $content): void
    {
        @mkdir(dirname($this->site.'/'.$relative), 0777, true);
        file_put_contents($this->site.'/'.$relative, $content);
    }

    protected function read(string $relative): string
    {
        return file_get_contents($this->site.'/'.$relative);
    }

    protected function rmrf(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        foreach (glob($path.'/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $item) {
            $this->rmrf($item);
        }
        @rmdir($path);
    }
}
