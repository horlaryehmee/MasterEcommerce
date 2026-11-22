<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentConnection;
use App\Models\AgentLog;
use App\Models\BusinessSetting;
use App\Services\Agent\Agent;
use App\Services\Updater\Updater;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Admin → Updates. The page drives the update one step at a time
 * (POST /admin/updates/step/{step}); these routes stay reachable while the
 * site is in maintenance mode (see bootstrap/app.php).
 */
class UpdateController extends Controller
{
    public function __construct(protected Updater $updater) {}

    public function index(Request $request): Response
    {
        $connection = AgentConnection::current();
        // When connected to a central update server, updates come from there instead of GitHub.
        $status = $connection
            ? ['current' => $this->updater->currentVersion(), 'available' => false, 'error' => null, 'managed' => true]
            : $this->updater->check($request->boolean('refresh'));
        $source = $this->updater->source();
        $settings = BusinessSetting::current();

        return Inertia::render('Admin/Updates', [
            'agent' => $connection ? [
                'server_url' => $connection->server_url,
                'installation_id' => $connection->installation_id,
                'fingerprint' => $connection->signing_fingerprint,
                'status' => $connection->status,
                'connected_at' => $connection->connected_at?->toIso8601String(),
                'last_contact_at' => $connection->last_contact_at?->toIso8601String(),
                'last_error' => $connection->last_error,
                'check_interval' => $connection->check_interval,
                'pending' => $connection->pending ?: [],
                'job' => ($job = app(Agent::class)->job()) ? ['step' => $job['step'] ?? null, 'label' => $job['label'] ?? null] : null,
                'modules' => array_values(app(ModuleRegistry::class)->report()),
                'module_problems' => collect(app(ModuleRegistry::class)->all())->filter(fn ($m) => ! empty($m['problem']))->map(fn ($m) => "{$m['slug']}: {$m['problem']}")->values(),
                'logs' => AgentLog::query()->latest('id')->limit(30)->get(['id', 'level', 'message', 'target_id', 'created_at']),
                'cron_ok' => $connection->last_attempt_at?->gt(now()->subMinutes($connection->check_interval * 2 + 5)) ?? false,
                'cron_command' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
            ] : null,
            'status' => $status,
            'checks' => $this->updater->preflight($status['asset']['size'] ?? null),
            'pending' => $this->updater->pending(),
            'down' => $this->updater->isDown(),
            'backups' => $this->updater->backups(),
            'steps' => Updater::STEPS,
            'source' => [
                'repository' => $settings->update_repository ?? '',
                'has_token' => filled($settings->update_token),
                'env_repository' => config('updater.repository'),
                'env_token' => filled(config('updater.token')),
                'effective' => $source['repository'],
            ],
        ]);
    }

    public function step(string $step): JsonResponse
    {
        abort_unless(in_array($step, Updater::STEPS, true), 404);

        $lock = Cache::lock('updater.step', 900);
        if (! $lock->get()) {
            return response()->json(['message' => 'Another update step is still running. Please wait a moment.'], 409);
        }

        try {
            return response()->json($this->updater->{$step}());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            rescue(fn () => $lock->release(), report: false);
        }
    }

    public function cancel(): RedirectResponse
    {
        try {
            $this->updater->cancel();
        } catch (Throwable $e) {
            return redirect()->route('admin.updates.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.updates.index')->with('success', 'The unfinished update was cancelled.');
    }

    public function online(): RedirectResponse
    {
        $this->updater->bringOnline();

        return redirect()->route('admin.updates.index')->with('success', 'Your website is back online.');
    }

    public function restore(string $backup): JsonResponse
    {
        try {
            return response()->json($this->updater->restore($backup));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** One-click rollback: undo later updates' database changes, then put the previous files back. */
    public function rollback(string $backup, Agent $agent): JsonResponse
    {
        try {
            $result = $this->updater->rollbackTo($backup);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // The signed file list of the newer version no longer matches; forget it so later updates compare correctly.
        $agent->forgetInstalled($result['kind'], $result['slug']);
        if (AgentConnection::current()) {
            AgentLog::write('warning', $result['message'].' (by the website admin) '.implode(' ', array_slice($result['details'], 0, -1)));
        }

        return response()->json($result);
    }

    public function database(string $backup): BinaryFileResponse
    {
        try {
            $path = $this->updater->databaseBackupPath($backup);
        } catch (Throwable) {
            abort(404);
        }

        return response()->download($path, 'database-backup-'.$backup.'.sql.gz');
    }

    public function source(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'repository' => ['nullable', 'string', 'max:200'],
            'token' => ['nullable', 'string', 'max:500'],
            'remove_token' => ['boolean'],
        ]);

        $repository = filled($data['repository'] ?? null) ? Updater::cleanRepository($data['repository']) : null;
        if (filled($data['repository'] ?? null) && ! $repository) {
            return redirect()->route('admin.updates.index')->withErrors(['repository' => 'Enter the repository as owner/name, e.g. my-company/catalog.']);
        }

        $settings = BusinessSetting::current();
        $settings->update_repository = $repository;
        if (filled($data['token'] ?? null)) {
            $settings->update_token = trim($data['token']);
        } elseif ($request->boolean('remove_token')) {
            $settings->update_token = null;
        }
        $settings->save();
        Cache::forget('updater.latest');

        return redirect()->route('admin.updates.index', ['refresh' => 1])->with('success', 'Update source saved.');
    }
}
