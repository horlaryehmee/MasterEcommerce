import { router } from '@inertiajs/react';
import { ChevronUp, Download, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActionMenu, Button, Modal } from '@/Components/Admin/ui';

/**
 * Ticked rows of an admin list. Selection is per page: it is cleared whenever
 * the rows change (new page, filter, or after a bulk action).
 *
 *   const sel = useSelection(products.data);           // rows with an `id`
 *   const sel = useSelection(customers.data, 'key');
 */
export function useSelection(rows, key = 'id') {
    const ids = useMemo(() => rows.map((r) => r[key]), [rows, key]);
    const [selected, setSelected] = useState([]);
    const signature = ids.join(',');

    useEffect(() => setSelected([]), [signature]);

    const has = (id) => selected.includes(id);
    const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const all = ids.length > 0 && ids.every((id) => selected.includes(id));
    const some = selected.length > 0 && !all;
    const toggleAll = () => setSelected(all ? [] : ids);
    const clear = () => setSelected([]);
    const rowsSelected = rows.filter((r) => selected.includes(r[key]));

    return { selected, count: selected.length, has, toggle, all, some, toggleAll, clear, rows: rowsSelected };
}

/** A checkbox that can show "some selected". Large tap target on phones. */
export function Checkbox({ checked, indeterminate = false, onChange, label, className = '' }) {
    const ref = useRef(null);
    useEffect(() => {
        if (ref.current) ref.current.indeterminate = indeterminate;
    }, [indeterminate]);
    return (
        <label
            className={`relative z-10 -m-2 flex shrink-0 cursor-pointer items-center justify-center p-2 ${className}`}
            onClick={(e) => e.stopPropagation()}
        >
            <input ref={ref} type="checkbox" checked={checked} onChange={onChange} aria-label={label} className="h-[18px] w-[18px] cursor-pointer rounded border-zinc-300 accent-zinc-900" />
        </label>
    );
}

/** Header-row checkbox: tick every row on this page. */
export function SelectAll({ selection, label = 'Select all on this page', className = '' }) {
    return <Checkbox checked={selection.all} indeterminate={selection.some} onChange={selection.toggleAll} label={label} className={className} />;
}

/** Row checkbox. */
export function SelectRow({ selection, id, label, className = '' }) {
    return <Checkbox checked={selection.has(id)} onChange={() => selection.toggle(id)} label={label} className={className} />;
}

/**
 * Posts a bulk action to `${url}`: { action, ids, value }. Clears the selection
 * when it succeeds (the list reloads with the flash message).
 */
export function useBulkAction(url, selection) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const run = (action, value = null, { onSuccess } = {}) =>
        router.post(
            url,
            { action, ids: selection.selected, value },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setErrors({});
                    selection.clear();
                    onSuccess?.();
                },
            },
        );
    return { run, processing, errors, setErrors };
}

/**
 * Bar shown while rows are ticked: "3 selected", the actions, Export selected,
 * and Clear. Sticks to the bottom of the screen so it stays reachable on phones.
 *
 * actions: [{ label, icon, onClick, danger? }] shown as buttons; more: the same
 * shape, in a "More" menu; exportUrl: the list's CSV export
 * (the selected ids are added to `exportQuery`).
 */
export function BulkBar({ selection, actions = [], more = [], exportUrl, exportQuery = '', noun = 'item', processing = false }) {
    if (selection.count === 0) return null;
    const ids = selection.selected.join(',');
    const href = exportUrl ? `${exportUrl}?${[exportQuery, `ids=${encodeURIComponent(ids)}`].filter(Boolean).join('&')}` : null;
    const btn = 'inline-flex h-10 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-semibold transition active:scale-95 disabled:opacity-50';

    return (
        <div className="sticky bottom-[calc(env(safe-area-inset-bottom)+4.5rem)] z-30 mx-2 mb-2 lg:bottom-4" role="region" aria-label="Bulk actions">
            <div className="flex items-center gap-2 rounded-2xl bg-zinc-900 p-2 pl-3 text-white shadow-2xl shadow-zinc-900/30">
                <button type="button" onClick={selection.clear} className="rounded-full p-1.5 text-zinc-300 hover:bg-white/10 hover:text-white" aria-label="Clear selection" title="Clear selection">
                    <X size={18} />
                </button>
                <span className="shrink-0 text-sm font-semibold tabular-nums">
                    {selection.count} <span className="hidden sm:inline">{selection.count === 1 ? noun : `${noun}s`}</span> selected
                </span>
                <div className="no-scrollbar ml-auto flex min-w-0 items-center gap-1 overflow-x-auto">
                    {actions.filter(Boolean).map((a) => {
                        const Icon = a.icon;
                        return (
                            <button
                                key={a.label}
                                type="button"
                                disabled={processing}
                                onClick={a.onClick}
                                className={`${btn} ${a.danger ? 'text-red-300 hover:bg-red-500/20 hover:text-red-200' : 'hover:bg-white/10'}`}
                                title={a.label}
                            >
                                {Icon && <Icon size={16} />}
                                <span className={Icon ? 'hidden sm:inline' : ''}>{a.label}</span>
                            </button>
                        );
                    })}
                    {href && (
                        <a href={href} className={`${btn} hover:bg-white/10`} title="Export the selected rows as a spreadsheet (CSV)">
                            <Download size={16} />
                            <span className="hidden sm:inline">Export</span>
                        </a>
                    )}
                </div>
                {more.filter(Boolean).length > 0 && (
                    <ActionMenu
                        up
                        label="More bulk actions"
                        items={more}
                        triggerClassName="text-white hover:bg-white/10"
                        trigger={
                            <>
                                More <ChevronUp size={15} />
                            </>
                        }
                    />
                )}
            </div>
        </div>
    );
}

/**
 * Confirmation dialog for a bulk action, optionally with a <select> for its
 * value (e.g. which status, which category).
 */
export function BulkDialog({ open, onClose, onConfirm, title, message, confirmLabel = 'Apply', danger = false, processing = false, choose, error }) {
    const [value, setValue] = useState('');
    useEffect(() => {
        if (open) setValue(choose?.initial ?? '');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);
    const needsValue = !!choose && value === '';

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            footer={
                <>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant={danger ? 'danger' : 'primary'} onClick={() => onConfirm(choose ? value : null)} loading={processing} disabled={needsValue}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <div className="space-y-3">
                {message && <div>{message}</div>}
                {choose && (
                    <label className="block">
                        <span className="mb-1.5 block text-xs font-semibold text-zinc-600">{choose.label}</span>
                        <select value={value} onChange={(e) => setValue(e.target.value)} className="input">
                            <option value="">{choose.placeholder ?? 'Choose…'}</option>
                            {choose.options.map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {error && <p className="text-sm font-medium text-red-600">{error}</p>}
            </div>
        </Modal>
    );
}

/** "3 products" */
export const plural = (n, noun) => `${n} ${n === 1 ? noun : `${noun}s`}`;
