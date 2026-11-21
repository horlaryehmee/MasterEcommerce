import { router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Circle, Download, ExternalLink, History, Loader2, PowerOff, RefreshCw, RotateCcw, ShieldCheck, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Badge, Button, Card, ConfirmModal, FormActions, Modal, PageHeader, TextInput, Toggle } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const STEP_LABELS = {
    download: 'Downloading the update',
    backup: 'Backing up your database',
    extract: 'Unpacking and checking the files',
    install: 'Installing the new version',
    finish: 'Updating the database and finishing',
};

function xsrf() {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function post(url) {
    let res;
    try {
        res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' } });
    } catch {
        throw new Error('Lost the connection to your website. Check your internet connection, then press Try again.');
    }
    let data = {};
    try {
        data = await res.json();
    } catch {
        /* not JSON (e.g. a host timeout page) */
    }
    if (!res.ok) {
        if (data.message) throw new Error(data.message);
        if (res.status === 419) throw new Error('Your session expired. Reload this page and try again.');
        if ([502, 503, 504, 524].includes(res.status)) throw new Error('This step took longer than your host allows for one request. Press Try again: it continues where it stopped.');
        throw new Error(`The server returned an error (${res.status}). Press Try again.`);
    }
    return data;
}

const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '');
const nextStep = (steps, done) => (done ? steps[steps.indexOf(done) + 1] : steps[0]);

/** Live progress of a running update. */
function Progress({ steps, states, error }) {
    return (
        <ol className="space-y-3">
            {steps.map((s) => {
                const st = states[s] || 'waiting';
                return (
                    <li key={s} className="flex items-start gap-3">
                        <span className="mt-0.5 shrink-0">
                            {st === 'done' && <CheckCircle2 size={20} className="text-emerald-600" />}
                            {st === 'running' && <Loader2 size={20} className="animate-spin text-zinc-900" />}
                            {st === 'error' && <XCircle size={20} className="text-red-600" />}
                            {st === 'waiting' && <Circle size={20} className="text-zinc-300" />}
                        </span>
                        <span className="min-w-0">
                            <span className={`block text-[15px] sm:text-sm ${st === 'waiting' ? 'text-zinc-400' : 'font-medium text-zinc-900'}`}>{STEP_LABELS[s] || s}</span>
                            {st === 'done' && states[`${s}:msg`] && <span className="block text-xs text-zinc-500">{states[`${s}:msg`]}</span>}
                            {st === 'error' && error && <span className="mt-1 block text-sm text-red-700">{error}</span>}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

function Checks({ checks }) {
    const failing = checks.filter((c) => !c.ok);
    return (
        <Card title="Server readiness" description={failing.length ? 'Fix these before updating.' : 'Your hosting is ready for one-click updates.'}>
            <ul className="space-y-2.5">
                {checks.map((c) => (
                    <li key={c.label} className="flex items-start gap-2.5 text-sm">
                        {c.ok ? <CheckCircle2 size={18} className="mt-px shrink-0 text-emerald-600" /> : <XCircle size={18} className="mt-px shrink-0 text-red-600" />}
                        <span className="min-w-0">
                            <span className="block text-zinc-800">{c.label}</span>
                            {!c.ok && <span className="block text-xs text-zinc-500">{c.detail}</span>}
                        </span>
                    </li>
                ))}
            </ul>
        </Card>
    );
}

function Source({ source }) {
    const form = useForm({ repository: source.repository || '', token: '', remove_token: false });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put('/admin/updates/source', { preserveScroll: true, onSuccess: () => form.setData((d) => ({ ...d, token: '', remove_token: false })) });
            }}
        >
            <Card title="Update source" description={`Where new versions come from. Usually set up once by ${usePage().props.author?.name || 'your developer'}.`}>
                <div className="space-y-5">
                    <TextInput
                        label="GitHub repository"
                        placeholder={source.env_repository || 'owner/repository'}
                        value={form.data.repository}
                        onChange={(e) => form.setData('repository', e.target.value)}
                        error={form.errors.repository}
                        help={source.env_repository && !form.data.repository ? `Leave blank to use ${source.env_repository} (from the server configuration).` : 'For example my-company/catalog, or the full github.com link.'}
                        optional={!!source.env_repository}
                    />
                    <TextInput
                        label="Access token"
                        type="password"
                        autoComplete="new-password"
                        placeholder={source.has_token ? '•••••••• (saved)' : source.env_token ? '(set in server configuration)' : 'github_pat_…'}
                        value={form.data.token}
                        onChange={(e) => form.setData('token', e.target.value)}
                        error={form.errors.token}
                        help="Needed for private repositories. A fine-grained token with read-only access to the repository's Contents is enough. Stored encrypted."
                        optional
                    />
                    {source.has_token && <Toggle label="Remove the saved token" checked={form.data.remove_token} onChange={(v) => form.setData('remove_token', v)} />}
                </div>
                <FormActions dirty={form.isDirty}>
                    <Button loading={form.processing}>Save</Button>
                </FormActions>
            </Card>
        </form>
    );
}

function Backups({ backups, onRestore }) {
    const [confirm, setConfirm] = useState(null);
    if (!backups.length) return null;
    return (
        <Card title="Previous versions" description="Kept automatically each time you update." padded={false}>
            <ul className="divide-y divide-zinc-100">
                {backups.map((b) => (
                    <li key={b.id} className="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3.5 sm:px-5">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-500">
                            <History size={17} />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm font-medium text-zinc-900">
                                {b.kind && b.kind !== 'core' ? `${b.slug} ${b.version === '0.0.0' ? '(not installed)' : b.version}` : `Version ${b.version}`}
                            </span>
                            <span className="block text-xs text-zinc-500">
                                {b.replaced_by ? `Replaced by ${b.replaced_by}` : 'Backup'}
                                {b.created_at ? ` · ${formatDate(b.created_at)}` : ''}
                            </span>
                        </span>
                        <span className="flex w-full gap-2 sm:w-auto">
                            {b.database_size != null && (
                                <a href={`/admin/updates/backups/${b.id}/database`} className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-zinc-300 px-3 text-sm font-medium text-zinc-800 hover:bg-zinc-50 sm:h-9 sm:flex-none sm:rounded-lg">
                                    <Download size={15} /> Database
                                </a>
                            )}
                            <button type="button" onClick={() => setConfirm(b)} className="inline-flex h-10 flex-1 items-center justify-center gap-1.5 rounded-xl border border-zinc-300 px-3 text-sm font-medium text-zinc-800 hover:bg-zinc-50 sm:h-9 sm:flex-none sm:rounded-lg">
                                <RotateCcw size={15} /> Restore
                            </button>
                        </span>
                    </li>
                ))}
            </ul>
            <ConfirmModal
                open={!!confirm}
                onClose={() => setConfirm(null)}
                onConfirm={() => {
                    onRestore(confirm);
                    setConfirm(null);
                }}
                title={confirm?.kind && confirm.kind !== 'core' ? `Restore ${confirm.slug} ${confirm.version}?` : `Go back to version ${confirm?.version}?`}
                confirmLabel="Restore"
                message="The website's files go back to this version (it is offline for a few seconds). Your products, orders and settings are not changed. Use this if something looks wrong after an update."
            />
        </Card>
    );
}

const LOG_DOT = { info: 'bg-zinc-300', success: 'bg-emerald-500', warning: 'bg-amber-500', error: 'bg-red-500' };

/** Connect form, shown when this site isn't connected to a central update server. */
function ConnectServer() {
    const form = useForm({ server_url: '', code: '' });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/admin/updates/agent/connect', { preserveScroll: true });
            }}
        >
            <Card title="Update server" description={`Connect to ${usePage().props.author?.name || 'your developer'}’s central update server to receive updates managed for you. Your website keeps working whether or not the server is reachable.`}>
                <div className="space-y-5">
                    <TextInput label="Update server address" placeholder="https://updates.example.com" value={form.data.server_url} onChange={(e) => form.setData('server_url', e.target.value)} error={form.errors.server_url} />
                    <TextInput label="Connection code" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} error={form.errors.code} help="A one-time code from the update server's admin." />
                </div>
                <FormActions dirty={form.isDirty}>
                    <Button loading={form.processing}>Connect</Button>
                </FormActions>
            </Card>
        </form>
    );
}

/** Status of the central update server connection, waiting updates and the local log. */
function ServerPanel({ agent }) {
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState(null);
    const [confirm, setConfirm] = useState(false);
    const waiting = agent.pending.find((p) => p.type === 'deploy' || p.type === 'rollback');

    const runNow = async () => {
        setBusy('Installing… keep this page open.');
        setError(null);
        try {
            let res = await post('/admin/updates/agent/run');
            if (res.status === 'needs_finish') {
                setBusy('Finishing…');
                res = await post('/admin/updates/agent/finish');
            }
            if (res.status === 'failed') throw new Error(res.message);
            window.location.reload();
        } catch (e) {
            setError(e.message);
            setBusy(null);
        }
    };

    return (
        <>
            <Card
                title="Update server"
                description={agent.server_url}
                actions={
                    <span className="flex shrink-0 gap-1">
                        <Button type="button" variant="secondary" onClick={() => router.post('/admin/updates/agent/check', {}, { preserveScroll: true })} className="!h-9">
                            <RefreshCw size={15} /> Check now
                        </Button>
                    </span>
                }
            >
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        {agent.status === 'active' ? <Badge color="green">Connected</Badge> : <Badge color="amber">Updates paused by the server</Badge>}
                        <span className="text-zinc-500">Last check-in {agent.last_contact_at ? new Date(agent.last_contact_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'never'} · every {agent.check_interval} min</span>
                    </div>
                    {agent.last_error && <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">Last check-in failed: {agent.last_error} Your website is not affected.</p>}
                    {!agent.cron_ok && (
                        <div className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p className="font-medium">Automatic checks don't seem to be running.</p>
                            <p className="mt-1">In cPanel → Cron Jobs, add this command to run every minute:</p>
                            <code className="mt-2 block rounded-lg bg-white px-3 py-2 font-mono text-xs break-all text-zinc-800 ring-1 ring-amber-200">{agent.cron_command}</code>
                        </div>
                    )}
                    {agent.job && (
                        <p className="flex items-center gap-2 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            <Loader2 size={16} className="animate-spin" /> {agent.job.step === 'waiting' ? `Update ${agent.job.label || ''} is waiting for checkout activity to finish.` : `Update ${agent.job.label || ''} in progress (${agent.job.step}).`}
                        </p>
                    )}
                    {waiting && !agent.job && (
                        <div className="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:flex-row sm:items-center">
                            <p className="min-w-0 flex-1 text-sm text-sky-950">
                                <strong>{waiting.type === 'rollback' ? 'Rollback requested' : 'Update waiting'}:</strong> {waiting.release.type === 'core' ? 'Core' : waiting.release.slug} {waiting.release.version}. It installs automatically within a minute, or now:
                            </p>
                            <Button type="button" onClick={runNow} loading={!!busy} disabled={!!busy}>
                                {waiting.type === 'rollback' ? 'Roll back now' : 'Install now'}
                            </Button>
                        </div>
                    )}
                    {busy && <p className="text-sm text-zinc-600">{busy}</p>}
                    {error && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{error}</p>}
                    <Details>
                        <p className="text-xs text-zinc-500">
                            Installation ID <code className="font-mono break-all text-zinc-700">{agent.installation_id}</code>
                        </p>
                        <p className="mt-1 text-xs text-zinc-500">
                            Signing key <code className="font-mono break-all text-zinc-700">{agent.fingerprint}</code> (should match the update server's dashboard)
                        </p>
                    </Details>
                    <button type="button" onClick={() => setConfirm(true)} className="text-sm font-medium text-red-600 hover:underline">
                        Disconnect
                    </button>
                </div>
            </Card>

            {(agent.modules.length > 0 || agent.module_problems.length > 0) && (
                <Card title="Modules & extensions">
                    <ul className="space-y-2">
                        {agent.modules.map((m) => (
                            <li key={m.slug} className="flex items-center justify-between gap-3 text-sm">
                                <span className="min-w-0">
                                    <span className="block font-medium text-zinc-900">{m.name}</span>
                                    <span className="block text-xs text-zinc-500">{m.type === 'extension' ? 'Made for this website' : 'Module'}{m.enabled ? '' : ' · not loaded'}</span>
                                </span>
                                <span className="shrink-0 tabular-nums">{m.version}</span>
                            </li>
                        ))}
                    </ul>
                    {agent.module_problems.map((p) => (
                        <p key={p} className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">{p}</p>
                    ))}
                </Card>
            )}

            <Card title="Update log" description="Kept on this website.">
                {agent.logs.length ? (
                    <ol className="space-y-2.5">
                        {agent.logs.map((l) => (
                            <li key={l.id} className="flex gap-2.5 text-sm">
                                <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${LOG_DOT[l.level] || LOG_DOT.info}`} aria-hidden />
                                <span className="min-w-0">
                                    <span className="block text-zinc-800">{l.message}</span>
                                    <span className="block text-xs text-zinc-400">{new Date(l.created_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}</span>
                                </span>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-sm text-zinc-400">Nothing yet.</p>
                )}
            </Card>

            <ConfirmModal open={confirm} onClose={() => setConfirm(false)} onConfirm={() => router.post('/admin/updates/agent/disconnect', {}, { onFinish: () => setConfirm(false) })} title="Disconnect from the update server?" confirmLabel="Disconnect" message="This website stops receiving managed updates. You'll need a new connection code to reconnect." />
        </>
    );
}

function Details({ children }) {
    return <div className="border-t border-zinc-100 pt-3">{children}</div>;
}

export default function Updates({ status, checks, pending, down, backups, steps, source, agent }) {
    const ready = checks.every((c) => c.ok);
    const [states, setStates] = useState({});
    const [running, setRunning] = useState(false);
    const [error, setError] = useState(null);
    const [failed, setFailed] = useState(null);
    const [done, setDone] = useState(null);
    const [confirm, setConfirm] = useState(false);
    const [restoring, setRestoring] = useState(null);
    const active = running || failed || done || Object.keys(states).length > 0;

    const run = async (from) => {
        setRunning(true);
        setError(null);
        setFailed(null);
        const start = steps.indexOf(from);
        setStates((s) => {
            const next = { ...s };
            steps.forEach((st, i) => i < start && (next[st] = 'done'));
            return next;
        });
        for (const step of steps.slice(start)) {
            setStates((s) => ({ ...s, [step]: 'running' }));
            try {
                const res = await post(`/admin/updates/step/${step}`);
                setStates((s) => ({ ...s, [step]: 'done', [`${step}:msg`]: res.message }));
                if (step === 'finish') setDone(res);
            } catch (e) {
                setStates((s) => ({ ...s, [step]: 'error' }));
                setError(e.message);
                setFailed(step);
                setRunning(false);
                return;
            }
        }
        setRunning(false);
    };

    // After an error, ask the server where it got to (a step may have finished after a timeout) and carry on from there.
    const retry = () =>
        router.reload({
            only: ['pending', 'down'],
            onSuccess: (page) => {
                const p = page.props.pending;
                if (!p && failed === 'finish') return (window.location.href = '/admin/updates');
                run(p ? nextStep(steps, p.step) : 'download');
            },
        });

    const restore = async (backup) => {
        setRestoring(backup.version);
        try {
            await post(`/admin/updates/backups/${backup.id}/restore`);
            window.location.href = '/admin/updates';
        } catch (e) {
            setRestoring(null);
            setError(e.message);
        }
    };

    return (
        <AdminLayout title="Updates">
            <PageHeader
                title="Updates"
                description={`Your website is on version ${status.current}.`}
                actions={
                    !active && !agent && (
                        <Button variant="secondary" href="/admin/updates?refresh=1" preserveScroll>
                            <RefreshCw size={16} /> Check for updates
                        </Button>
                    )
                }
            />

            <div className="grid max-w-3xl gap-6">
                {down && !running && (
                    <div className="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
                        <PowerOff size={20} className="shrink-0 text-amber-700" />
                        <p className="min-w-0 flex-1 text-sm text-amber-900">Your website is in maintenance mode, so visitors see a "back soon" page.</p>
                        <Button type="button" variant="secondary" onClick={() => router.post('/admin/updates/online', {}, { preserveScroll: true })}>
                            Bring website online
                        </Button>
                    </div>
                )}

                {pending && !active && (
                    <Card title="An update was not finished" description={`Version ${pending.to} stopped after: ${STEP_LABELS[pending.step]?.toLowerCase() || pending.step}.`}>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button type="button" onClick={() => run(nextStep(steps, pending.step))}>
                                Continue the update
                            </Button>
                            {pending.step !== 'install' && (
                                <Button type="button" variant="secondary" onClick={() => router.post('/admin/updates/cancel', {}, { preserveScroll: true })}>
                                    Cancel it
                                </Button>
                            )}
                        </div>
                    </Card>
                )}

                {restoring && (
                    <Card>
                        <p className="flex items-center gap-3 text-sm font-medium text-zinc-900">
                            <Loader2 size={18} className="animate-spin" /> Restoring version {restoring}…
                        </p>
                    </Card>
                )}

                {agent && !active ? (
                    <ServerPanel agent={agent} />
                ) : active ? (
                    <Card title={done ? 'Update complete' : `Updating to version ${status.latest || pending?.to}`} description={done ? null : running ? 'Keep this page open. Your website is offline for about a minute during the install step.' : null}>
                        <Progress steps={steps} states={states} error={error} />
                        {failed && (
                            <div className="mt-5 flex flex-col gap-2 sm:flex-row">
                                <Button type="button" onClick={retry}>
                                    <RotateCcw size={16} /> Try again
                                </Button>
                                {failed !== 'finish' && failed !== 'install' && (
                                    <Button type="button" variant="secondary" onClick={() => router.post('/admin/updates/cancel', {}, { onSuccess: () => (window.location.href = '/admin/updates') })}>
                                        Cancel update
                                    </Button>
                                )}
                            </div>
                        )}
                        {done && (
                            <div className="mt-5 rounded-xl bg-emerald-50 p-4">
                                <p className="flex items-center gap-2 font-semibold text-emerald-900">
                                    <ShieldCheck size={18} /> {done.message}
                                </p>
                                <p className="mt-1 text-sm text-emerald-800">Your website is back online with the new version.</p>
                                <Button type="button" className="mt-3" onClick={() => (window.location.href = '/admin/updates')}>
                                    Done
                                </Button>
                            </div>
                        )}
                    </Card>
                ) : status.available ? (
                    <Card>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0">
                                <Badge color="blue">New version</Badge>
                                <h2 className="mt-2 text-xl font-bold tracking-tight text-zinc-900">Version {status.latest} is available</h2>
                                <p className="mt-0.5 text-sm text-zinc-500">
                                    You have {status.current}
                                    {status.published_at ? ` · released ${formatDate(status.published_at)}` : ''}
                                </p>
                            </div>
                            <Button type="button" onClick={() => setConfirm(true)} disabled={!ready || !!status.error || !!pending} className="shrink-0">
                                <Download size={16} /> Update now
                            </Button>
                        </div>
                        {status.error && <p className="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{status.error}</p>}
                        {status.notes_html && (
                            <div className="mt-5 border-t border-zinc-100 pt-4">
                                <h3 className="mb-2 text-sm font-semibold text-zinc-900">What's new</h3>
                                <div className="prose prose-sm prose-zinc max-w-none" dangerouslySetInnerHTML={{ __html: status.notes_html }} />
                            </div>
                        )}
                        {status.url && (
                            <a href={status.url} target="_blank" rel="noopener noreferrer" className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-900">
                                Release page <ExternalLink size={14} />
                            </a>
                        )}
                    </Card>
                ) : (
                    <Card>
                        {status.error ? (
                            <div className="flex items-start gap-3">
                                <AlertTriangle size={20} className="mt-0.5 shrink-0 text-amber-600" />
                                <div className="min-w-0">
                                    <p className="font-semibold text-zinc-900">Couldn't check for updates</p>
                                    <p className="mt-1 text-sm text-zinc-600">{status.error}</p>
                                </div>
                            </div>
                        ) : (
                            <div className="flex items-start gap-3">
                                <CheckCircle2 size={22} className="mt-0.5 shrink-0 text-emerald-600" />
                                <div className="min-w-0">
                                    <p className="font-semibold text-zinc-900">Your website is up to date</p>
                                    <p className="mt-0.5 text-sm text-zinc-500">
                                        Version {status.current}
                                        {status.checked_at ? ` · checked ${new Date(status.checked_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}` : ''}
                                    </p>
                                </div>
                            </div>
                        )}
                    </Card>
                )}

                {error && !active && <p className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{error}</p>}

                <Checks checks={checks} />
                <Backups backups={backups} onRestore={restore} />
                {!agent && (
                    <>
                        <ConnectServer />
                        <Source source={source} />
                    </>
                )}
            </div>

            <Modal
                open={confirm}
                onClose={() => setConfirm(false)}
                title={`Update to version ${status.latest}?`}
                footer={
                    <>
                        <Button type="button" variant="secondary" onClick={() => setConfirm(false)}>
                            Not now
                        </Button>
                        <Button
                            type="button"
                            onClick={() => {
                                setConfirm(false);
                                run('download');
                            }}
                        >
                            Start update
                        </Button>
                    </>
                }
            >
                <ul className="list-disc space-y-1.5 pl-5">
                    <li>Your database is backed up first, and the current version is kept so you can go back.</li>
                    <li>The website is offline for about a minute while the new version installs.</li>
                    <li>Keep this page open until it says the update is complete.</li>
                </ul>
            </Modal>
        </AdminLayout>
    );
}
