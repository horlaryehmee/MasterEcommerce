<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Agent\Agent;
use App\Services\Agent\AgentException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/** Admin → Updates → Update server: connect, check in, install what the server sent. */
class AgentController extends Controller
{
    public function __construct(protected Agent $agent) {}

    public function connect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_url' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
        ]);

        try {
            $connection = $this->agent->connect($data['server_url'], $data['code']);
        } catch (AgentException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Connected to the update server. Key fingerprint: '.$connection->signing_fingerprint);
    }

    public function disconnect(): RedirectResponse
    {
        $this->agent->disconnect();

        return back()->with('success', 'Disconnected from the update server.');
    }

    /** Check in now (no installing). */
    public function check(): RedirectResponse
    {
        $result = $this->agent->sync(force: true, work: false);
        if ($result['status'] === 'offline') {
            return back()->with('error', 'Could not reach the update server: '.$result['message']);
        }

        return back()->with('success', $result['status'] === 'busy' ? $result['message'] : ($result['message'] === 'An update is waiting.' ? 'An update from the update server is waiting.' : 'Checked in. No updates waiting.'));
    }

    /**
     * Install what the server sent, now. Returns JSON; when it answers
     * "needs_finish" the page calls finish() in a new request (new code).
     */
    public function run(): JsonResponse
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        try {
            return response()->json($this->agent->sync(force: true, work: true, manual: true));
        } catch (Throwable $e) {
            return response()->json(['status' => 'failed', 'message' => $e->getMessage()], 422);
        }
    }

    public function finish(): JsonResponse
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        return response()->json($this->agent->finishPending());
    }
}
