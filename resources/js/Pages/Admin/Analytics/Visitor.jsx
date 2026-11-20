import { Link } from '@inertiajs/react';
import { Eye, Laptop, MousePointerClick, Search, ShoppingBag, ShoppingCart, Smartphone, Tablet, Tag, CreditCard } from 'lucide-react';
import { Flag, fmt, fmtDuration } from '@/Components/Admin/Analytics';
import { Badge, Card, PageHeader } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

const DEVICE_ICONS = { mobile: Smartphone, tablet: Tablet, desktop: Laptop };
const EVENTS = {
    view_item: [Tag, 'Viewed product'],
    add_to_cart: [ShoppingCart, 'Added to cart'],
    begin_checkout: [CreditCard, 'Started checkout'],
    purchase: [ShoppingBag, 'Placed order'],
    search: [Search, 'Searched'],
    outbound_click: [MousePointerClick, 'Clicked'],
};

function Stat({ label, value }) {
    return (
        <div>
            <p className="text-xs text-zinc-500">{label}</p>
            <p className="mt-0.5 text-lg font-semibold text-zinc-900 tabular-nums">{value}</p>
        </div>
    );
}

export default function Visitor({ visitor: v, sessions, orders }) {
    const Icon = DEVICE_ICONS[v.device] || Laptop;

    return (
        <AdminLayout title={v.name}>
            <PageHeader back={{ href: '/admin/analytics/visitors', label: 'Visitors' }} title={v.name} description={`First seen ${v.first_seen} · last seen ${v.last_seen}`} actions={v.online ? <Badge color="green">Online now</Badge> : null} />

            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="space-y-4">
                    {sessions.map((s, i) => (
                        <Card
                            key={s.id}
                            title={
                                <span className="flex flex-wrap items-center gap-2">
                                    Visit {sessions.length - i} · {s.started}
                                    {s.converted && <Badge color="green">Ordered</Badge>}
                                </span>
                            }
                            description={`${s.pageviews} ${s.pageviews === 1 ? 'page' : 'pages'} · ${fmtDuration(s.duration)} on site · ${fmtDuration(s.engaged_seconds)} engaged · from ${s.source || 'Direct'}${s.campaign ? ` (${s.campaign})` : ''}`}
                        >
                            <ol className="relative space-y-3 border-l border-zinc-200 pl-5">
                                {s.timeline.map((item, j) => {
                                    const [EvIcon, evLabel] = item.type === 'event' ? EVENTS[item.name] || [Tag, item.name] : [Eye, null];
                                    return (
                                        <li key={j} className="relative text-sm">
                                            <span className={`absolute top-0.5 -left-[29px] flex h-[18px] w-[18px] items-center justify-center rounded-full ring-4 ring-white ${item.type === 'event' ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'}`}>
                                                <EvIcon size={11} />
                                            </span>
                                            <div className="flex items-baseline justify-between gap-3">
                                                <span className="min-w-0">
                                                    {item.type === 'view' ? (
                                                        <>
                                                            <span className="block truncate text-zinc-800">{item.title || item.path}</span>
                                                            <span className="block truncate text-xs text-zinc-400">
                                                                {item.path}
                                                                {item.engaged > 0 && ` · ${fmtDuration(item.engaged)} on page`}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <span className="text-emerald-800">
                                                            <strong className="font-semibold">{evLabel}</strong>
                                                            {item.label && `: ${item.label}`}
                                                            {item.value && <span className="text-emerald-700"> · {item.value}</span>}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="shrink-0 text-xs text-zinc-400 tabular-nums">{item.time}</span>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ol>
                            <p className="mt-4 flex flex-wrap gap-x-3 gap-y-1 border-t border-zinc-100 pt-3 text-xs text-zinc-500">
                                <span className="flex items-center gap-1.5">
                                    <Flag code={s.country} /> {[s.city, s.region, s.country_name].filter(Boolean).join(', ') || 'Unknown location'}
                                </span>
                                <span>
                                    {s.browser} on {s.os} ({s.device})
                                </span>
                                {s.referrer && (
                                    <span className="max-w-full truncate" title={s.referrer}>
                                        Referrer: {s.referrer}
                                    </span>
                                )}
                            </p>
                        </Card>
                    ))}
                </div>

                <div className="space-y-6">
                    <Card title="About this visitor">
                        <div className="space-y-3 text-sm">
                            <p className="flex items-center gap-2 text-zinc-800">
                                <Flag code={v.country} className="h-4 w-6" /> {[v.city, v.region, v.country_name].filter(Boolean).join(', ')}
                            </p>
                            <p className="flex items-center gap-2 text-zinc-700">
                                <Icon size={16} className="text-zinc-400" /> {v.browser} on {v.os}
                            </p>
                            <p className="text-zinc-700">
                                First came from <strong className="font-semibold text-zinc-900">{v.first_source || 'Direct'}</strong>
                                {v.first_channel && v.first_channel !== v.first_source && <span className="text-zinc-500"> ({v.first_channel})</span>}
                            </p>
                        </div>
                        <div className="mt-5 grid grid-cols-3 gap-3 border-t border-zinc-100 pt-4">
                            <Stat label="Visits" value={fmt(v.sessions)} />
                            <Stat label="Views" value={fmt(v.pageviews)} />
                            <Stat label="Orders" value={fmt(v.orders)} />
                        </div>
                    </Card>

                    {orders.length > 0 && (
                        <Card title="Orders" padded={false}>
                            <ul className="divide-y divide-zinc-100">
                                {orders.map((o) => (
                                    <li key={o.id}>
                                        <Link href={`/admin/orders/${o.id}`} className="flex items-center justify-between px-5 py-3 text-sm hover:bg-zinc-50">
                                            <span>
                                                <span className="font-semibold text-zinc-900">#{o.number}</span>
                                                <span className="block text-xs text-zinc-500">{o.date}</span>
                                            </span>
                                            <span className="font-medium text-zinc-900">{o.total}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    <p className="text-xs text-zinc-400">Visitors are anonymous: an ID stored in their browser, with no name, email or IP address. Orders show who they are when they buy.</p>
                </div>
            </div>
        </AdminLayout>
    );
}
