<?php

namespace Tests\Feature\Admin;

use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Models\Order;
use App\Services\Agent\Agent;
use App\Services\Updater\Updater;
use App\Support\Modules\ModuleRegistry;
use Database\Seeders\EssentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * The update agent against a faked central server (real Ed25519 signatures)
 * and a throwaway site folder. Covers the security decisions: signatures,
 * client binding, checksums, hand-edited files, the checkout hold, the outbox.
 */
class UpdateAgentTest extends TestCase
{
    use RefreshDatabase;

    protected string $tmp;

    protected string $site;

    protected string $keypair;

    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EssentialSeeder::class);
        $this->actingAsAdmin();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->tmp = sys_get_temp_dir().'/agent-test-'.Str::random(8);
        $this->site = $this->tmp.'/site';
        $this->write('VERSION', "1.0.0\n");
        $this->write('app/Support/Money.php', '<?php // original');
        $this->write('public/index.php', 'index');
        @mkdir($this->site.'/modules', 0777, true);

        config(['agent.state_path' => $this->tmp.'/agent', 'agent.spawn_finish' => false, 'agent.allow_insecure' => false]);
        $this->app->instance(Updater::class, new Updater($this->site, $this->site.'/public', $this->tmp.'/work'));
        $this->app->instance(ModuleRegistry::class, new ModuleRegistry($this->site));

        $this->keypair = sodium_crypto_sign_keypair();
        AgentConnection::create([
            'server_url' => 'https://central.test',
            'installation_id' => (string) Str::uuid(),
            'client_key' => 'client-acme',
            'secret' => 'shared-secret',
            'signing_key_id' => 'key1',
            'signing_public_key' => base64_encode(sodium_crypto_sign_publickey($this->keypair)),
            'signing_fingerprint' => 'AAAA',
            'check_interval' => 15,
        ]);
    }

    protected function tearDown(): void
    {
        app(Updater::class)->bringOnline();
        $this->rmrf($this->tmp);
        parent::tearDown();
    }

    public function test_a_signed_module_is_installed_and_the_result_reported(): void
    {
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'gift-wrap', 'version' => '1.1.0', 'type' => 'module']), 'src/A.php' => '<?php']);
        $this->fakeCentral($this->manifest('module', 'gift-wrap', '1.1.0', $zip));

        $result = app(Agent::class)->sync(force: true);
        $this->assertSame('needs_finish', $result['status']);
        $this->assertSame('done', app(Agent::class)->finishPending()['status']);

        $this->assertFileExists($this->site.'/modules/gift-wrap/src/A.php');
        $complete = $this->lastCall('complete');
        $this->assertSame('succeeded', $complete['result']);
        $this->assertSame('1.1.0', $complete['version']);
        foreach (['download', 'backup', 'extract', 'install', 'finish'] as $step) {
            $this->assertContains($step, array_column($this->calls('progress'), 'step'));
        }

        // Requests are signed with the installation secret.
        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'heartbeat')) {
                return false;
            }
            $h = fn ($k) => $r->header($k)[0] ?? '';
            $canonical = implode("\n", ['POST', 'api/v1/heartbeat', $h('X-Timestamp'), $h('X-Nonce'), hash('sha256', $r->body())]);

            return hash_equals(hash_hmac('sha256', $canonical, 'shared-secret'), $h('X-Signature'));
        });
    }

    public function test_a_tampered_signature_is_refused_and_nothing_installed(): void
    {
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'gift-wrap', 'version' => '1.1.0', 'type' => 'module'])]);
        $m = $this->manifest('module', 'gift-wrap', '1.1.0', $zip);
        $m['json'] = str_replace('"1.1.0"', '"1.2.0"', $m['json']); // altered after signing
        $this->fakeCentral($m);

        $this->assertSame('failed', app(Agent::class)->sync(force: true)['status']);
        $this->assertDirectoryDoesNotExist($this->site.'/modules/gift-wrap');
        $this->assertSame(['failed', 'bad_signature', false], [$this->lastCall('complete')['result'], $this->lastCall('complete')['error_code'], $this->lastCall('complete')['retryable']]);
    }

    public function test_another_clients_extension_is_refused(): void
    {
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'globex-pay', 'version' => '1.0.0', 'type' => 'extension'])]);
        $this->fakeCentral($this->manifest('extension', 'globex-pay', '1.0.0', $zip, client: 'client-globex'));

        app(Agent::class)->sync(force: true);
        $this->assertDirectoryDoesNotExist($this->site.'/extensions/globex-pay');
        $this->assertSame('incompatible', $this->lastCall('complete')['error_code']);
        $this->assertStringContainsString('belongs to another client', $this->lastCall('complete')['error']);
    }

    public function test_a_package_that_does_not_match_its_signed_checksum_is_rejected(): void
    {
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'gift-wrap', 'version' => '1.1.0', 'type' => 'module'])]);
        $m = $this->manifest('module', 'gift-wrap', '1.1.0', $zip);
        $m['bytes'] = $this->zip(['module.json' => '{"evil":true}']); // swapped in transit
        $this->fakeCentral($m);

        app(Agent::class)->sync(force: true);
        $this->assertSame(['checksum', true], [$this->lastCall('complete')['error_code'], $this->lastCall('complete')['retryable']]);
        $this->assertDirectoryDoesNotExist($this->site.'/modules/gift-wrap');
    }

    public function test_hand_edited_core_files_block_a_core_update(): void
    {
        $baseline = $this->manifest('core', 'core', '1.0.0', $this->zip(['VERSION' => "1.0.0\n"]), files: ['app/Support/Money.php' => hash('sha256', '<?php // original'), 'public/index.php' => 'ignored']);
        $this->write('app/Support/Money.php', '<?php // customised by hand');

        $this->fakeCentral($this->manifest('core', 'core', '1.1.0', $this->zip(['VERSION' => "1.1.0\n"])), $baseline);
        app(Agent::class)->sync(force: true);

        $complete = $this->lastCall('complete');
        $this->assertSame('modified_files', $complete['error_code']);
        $this->assertStringContainsString('app/Support/Money.php', $complete['error']);
        $this->assertStringNotContainsString('public/index.php', $complete['error']);
        $this->assertSame("1.0.0\n", file_get_contents($this->site.'/VERSION'));
        $this->assertSame('<?php // customised by hand', file_get_contents($this->site.'/app/Support/Money.php'));
    }

    public function test_installs_wait_while_customers_are_checking_out(): void
    {
        Order::query()->create([
            'customer_name' => 'Ada', 'customer_phone' => '+2348000000000', 'fulfilment' => 'delivery', 'shipping_country' => 'NG',
            'address' => '1 Road', 'city' => 'Lagos', 'payment_method' => 'delivery', 'subtotal' => 1000, 'delivery_fee' => 0, 'total' => 1000, 'currency_code' => 'NGN',
        ]);
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'gift-wrap', 'version' => '1.1.0', 'type' => 'module'])]);
        $this->fakeCentral($this->manifest('module', 'gift-wrap', '1.1.0', $zip));

        $this->assertSame('waiting', app(Agent::class)->sync(force: true)['status']);
        $this->assertDirectoryDoesNotExist($this->site.'/modules/gift-wrap');
        $this->assertSame('waiting', $this->lastCall('progress')['step']);
        $this->assertEmpty($this->calls('complete'));

        // An administrator can install anyway.
        $this->assertSame('needs_finish', app(Agent::class)->sync(force: true, manual: true)['status']);
    }

    public function test_results_are_kept_and_resent_when_the_server_is_unreachable(): void
    {
        $zip = $this->zip(['module.json' => json_encode(['slug' => 'gift-wrap', 'version' => '1.1.0', 'type' => 'module'])]);
        $this->fakeCentral($this->manifest('module', 'gift-wrap', '1.1.0', $zip), completeStatus: 503);
        app(Agent::class)->sync(force: true);
        app(Agent::class)->finishPending();

        $this->assertFileExists($this->tmp.'/agent/outbox.json');
        $this->assertTrue(AgentLog::query()->where('message', 'like', '%sent on the next run%')->exists());

        $this->fakeCentral(null);
        app(Agent::class)->sync(force: true);
        $this->assertSame('succeeded', $this->lastCall('complete')['result']);
        $this->assertFileDoesNotExist($this->tmp.'/agent/outbox.json');
    }

    public function test_an_unreachable_server_never_breaks_anything(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

        $result = app(Agent::class)->sync(force: true);
        $this->assertSame('offline', $result['status']);
        $this->assertStringContainsString('could not be reached', AgentConnection::current()->last_error);
        $this->get('/')->assertOk();
    }

    public function test_plain_http_servers_are_refused_unless_allowed(): void
    {
        $this->post('/admin/updates/agent/connect', ['server_url' => 'http://central.test', 'code' => 'X'])->assertSessionHasErrors(['code' => 'The update server address must start with https://']);
    }

    public function test_the_updates_page_shows_the_connection(): void
    {
        $this->get('/admin/updates')->assertOk()->assertInertia(fn ($p) => $p->where('agent.server_url', 'https://central.test')->missing('agent.secret'));
    }

    /* ------------------------------------------------------------------ */

    /** A signed manifest for a package, as the central server would send it. */
    protected function manifest(string $type, string $slug, string $version, string $zipBytes, ?string $client = null, ?array $files = null): array
    {
        $tmp = $this->tmp.'/m-'.Str::random(5).'.zip';
        file_put_contents($tmp, $zipBytes);
        $z = new ZipArchive;
        $z->open($tmp);
        $list = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $list[$z->getNameIndex($i)] = hash('sha256', $z->getFromIndex($i));
        }
        $z->close();
        unlink($tmp);

        $json = json_encode([
            'format' => 1, 'release' => 'rel-'.$slug.'-'.$version, 'type' => $type, 'slug' => $slug, 'version' => $version,
            'client' => $type === 'extension' ? ($client ?? 'client-acme') : null,
            'requires' => (object) [], 'upgrade_from' => ['min' => null, 'max' => null], 'migrations' => false,
            'rollback' => ['files' => true, 'database' => false],
            'package' => ['sha256' => hash('sha256', $zipBytes), 'size' => strlen($zipBytes), 'root' => ''],
            'files' => (object) ($files ?? $list), 'key_id' => 'key1',
        ], JSON_UNESCAPED_SLASHES);

        return ['json' => $json, 'sig' => base64_encode(sodium_crypto_sign_detached($json, sodium_crypto_sign_secretkey($this->keypair))), 'bytes' => $zipBytes, 'type' => $type, 'slug' => $slug, 'version' => $version];
    }

    /** Fake central: one deploy instruction for $m (if given), and records what the agent sends. */
    protected function fakeCentral(?array $m, ?array $baseline = null, int $completeStatus = 200): void
    {
        Http::swap(new HttpFactory);
        Http::fake(function (Request $r) use ($m, $baseline, $completeStatus) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            $body = $r->body() ? json_decode($r->body(), true) : [];
            $this->sent[] = ['path' => $path, 'body' => $body];

            return match (true) {
                str_ends_with($path, '/heartbeat') => Http::response(['status' => 'active', 'check_interval' => 15, 'instructions' => $m ? [['type' => 'deploy', 'target' => 7, 'release' => ['type' => $m['type'], 'slug' => $m['slug'], 'version' => $m['version']]]] : []]),
                str_ends_with($path, '/accept') => Http::response(['target' => 7, 'action' => 'deploy', 'release' => ['uuid' => 'rel-'.$m['slug'].'-'.$m['version']], 'manifest' => $m['json'], 'signature' => $m['sig'], 'key_id' => 'key1', 'download' => ['path' => 'api/v1/deployments/7/package'], 'options' => []]),
                str_ends_with($path, '/package') => Http::response($m['bytes'], 200, ['Content-Type' => 'application/zip']),
                str_contains($path, '/manifests/') => $baseline ? Http::response(['manifest' => $baseline['json'], 'signature' => $baseline['sig'], 'key_id' => 'key1']) : Http::response(['error' => 'not_found'], 404),
                str_ends_with($path, '/complete') => Http::response(['ok' => $completeStatus === 200], $completeStatus),
                default => Http::response(['ok' => true]),
            };
        });
    }

    protected function calls(string $endpoint): array
    {
        return array_values(array_map(fn ($c) => $c['body'], array_filter($this->sent, fn ($c) => str_ends_with($c['path'], '/'.$endpoint))));
    }

    protected function lastCall(string $endpoint): array
    {
        $calls = $this->calls($endpoint);
        $this->assertNotEmpty($calls, "No {$endpoint} call was made.");

        return end($calls);
    }

    protected function zip(array $files): string
    {
        $path = $this->tmp.'/z-'.Str::random(5).'.zip';
        @mkdir($this->tmp, 0777, true);
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE);
        foreach ($files as $n => $c) {
            $z->addFromString($n, $c);
        }
        $z->close();
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    protected function write(string $rel, string $content): void
    {
        @mkdir(dirname($this->site.'/'.$rel), 0777, true);
        file_put_contents($this->site.'/'.$rel, $content);
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
