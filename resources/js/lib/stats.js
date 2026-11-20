/**
 * First-party visitor statistics beacon (Admin → Analytics).
 *
 * - Visitor: a random ID in a first-party cookie + localStorage (no personal data).
 * - Session: ends after 30 minutes without activity, as in Google Analytics.
 * - Page views: one per real navigation (never for prefetches).
 * - Engagement: seconds the page is actually visible; a heartbeat keeps the
 *   real-time view accurate while someone is reading.
 * - Events: shop events from analytics.js, and clicks on outside links (WhatsApp, phone, email…).
 */

const ENDPOINT = '/hello';
const SESSION_TIMEOUT = 30 * 60 * 1000;
const HEARTBEAT = 60 * 1000;

let enabled = false;
let started = false; // the first page view has been processed
const queued = []; // events fired before that (e.g. a search on the landing page)
let current = null; // { pid, visibleSince, engagedMs }
let heartbeat = null;
let listening = false;

const uuid = () =>
    crypto?.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });

function storage(key, value) {
    try {
        if (value === undefined) return localStorage.getItem(key);
        localStorage.setItem(key, value);
    } catch {
        /* storage blocked: fall back to the cookie only */
    }
    return value;
}

function visitorId() {
    const fromCookie = document.cookie.match(/(?:^|;\s*)nx_vid=([0-9a-f-]{36})/i)?.[1];
    const id = fromCookie || storage('nx_vid') || uuid();
    storage('nx_vid', id);
    // Readable by the server so orders can be linked to the visitor.
    document.cookie = `nx_vid=${id}; path=/; max-age=${60 * 60 * 24 * 400}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
    return id;
}

/** Current session ID; `isNew` when the previous one timed out. */
function session(touch = true) {
    let s = null;
    try {
        s = JSON.parse(storage('nx_session') || 'null');
    } catch {
        s = null;
    }
    const now = Date.now();
    const isNew = !s || now - s.last > SESSION_TIMEOUT;
    if (isNew) s = { sid: uuid(), last: now };
    if (touch) s.last = now;
    storage('nx_session', JSON.stringify(s));
    return { sid: s.sid, isNew };
}

function send(payload) {
    if (!enabled) return;
    const body = JSON.stringify({ vid: visitorId(), ...payload });
    try {
        // text/plain keeps it a "simple" request; the server accepts it.
        if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'text/plain' }))) return;
    } catch {
        /* fall through to fetch */
    }
    fetch(ENDPOINT, { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'text/plain' }, credentials: 'same-origin' }).catch(() => {});
}

function utm() {
    const q = new URLSearchParams(location.search);
    const out = {};
    for (const k of ['source', 'medium', 'campaign']) {
        const v = q.get(`utm_${k}`);
        if (v) out[k] = v;
    }
    return out;
}

/** Context so the server can start a session even if an event arrives before the page view. */
function context(sess) {
    return { sid: sess.sid, path: location.pathname, ...(sess.isNew ? { ref: document.referrer || null, utm: utm(), w: screen.width } : {}) };
}

function engagedSeconds() {
    if (!current) return 0;
    const extra = current.visibleSince ? Date.now() - current.visibleSince : 0;
    return Math.round((current.engagedMs + extra) / 1000);
}

function flushEngagement() {
    if (!current) return;
    const seconds = engagedSeconds();
    if (seconds > 0) send({ t: 'engage', sid: session().sid, pid: current.pid, engaged: seconds });
}

function onVisibility() {
    if (!current) return;
    if (document.visibilityState === 'hidden') {
        if (current.visibleSince) current.engagedMs += Date.now() - current.visibleSince;
        current.visibleSince = null;
        flushEngagement();
    } else {
        current.visibleSince = Date.now();
    }
}

function outboundLabel(a) {
    const href = a.getAttribute('href') || '';
    if (/^https?:\/\/(wa\.me|api\.whatsapp\.com|web\.whatsapp\.com)/i.test(href)) return 'WhatsApp';
    if (href.startsWith('tel:')) return 'Phone call';
    if (href.startsWith('mailto:')) return 'Email';
    try {
        const url = new URL(href, location.href);
        if (url.host !== location.host && /^https?:$/.test(url.protocol)) return url.host.replace(/^www\./, '');
    } catch {
        return null;
    }
    return null;
}

function listen() {
    if (listening) return;
    listening = true;
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', () => {
        if (current?.visibleSince) {
            current.engagedMs += Date.now() - current.visibleSince;
            current.visibleSince = null;
        }
        flushEngagement();
    });
    document.addEventListener(
        'click',
        (e) => {
            const a = e.target.closest?.('a[href]');
            const label = a && outboundLabel(a);
            if (label) trackStat('outbound_click', label);
        },
        true,
    );
}

/** Call on every Inertia navigation (including the first page). */
export function statsPageview(isEnabled, title) {
    // Automated browsers aren't visitors.
    enabled = !!isEnabled && !navigator.webdriver;
    started = true;
    if (!enabled) {
        queued.length = 0;
        return;
    }
    listen();

    // Finish the previous page's engagement (flushEngagement counts the visible stretch once).
    flushEngagement();

    const sess = session();
    current = { pid: uuid(), visibleSince: document.visibilityState === 'visible' ? Date.now() : null, engagedMs: 0 };
    send({ t: 'pageview', ...context(sess), pid: current.pid, title: title || document.title });
    flushQueue();

    clearInterval(heartbeat);
    heartbeat = setInterval(() => {
        if (document.visibilityState === 'visible') flushEngagement();
    }, HEARTBEAT);
}

function flushQueue() {
    while (queued.length) trackStat(...queued.shift());
}

export function trackStat(name, label, value) {
    if (!started) {
        queued.push([name, label, value]);
        return;
    }
    if (!enabled) return;
    const sess = session();
    send({ t: 'event', ...context(sess), name, label: label ?? null, value: value ?? null });
}
