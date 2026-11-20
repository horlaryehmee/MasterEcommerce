import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Circle, FileEdit, FolderPlus, FolderTree, Package, PackageCheck, PanelsTopLeft, Plus, Settings, Star } from 'lucide-react';
import { Card, PageHeader, StatusBadge } from '@/Components/Admin/ui';
import AdminLayout from '@/Layouts/AdminLayout';

function Stat({ label, value, icon: Icon, href }) {
    return (
        <Link href={href} className="w-[40%] shrink-0 snap-start rounded-2xl border border-zinc-200/80 bg-white p-4 shadow-xs transition hover:border-zinc-300 active:scale-[0.97] sm:w-auto sm:p-5">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-zinc-500">{label}</p>
                <Icon size={18} className="text-zinc-400" />
            </div>
            <p className="mt-2 text-[1.75rem] leading-none font-bold tracking-tight text-zinc-900 sm:mt-3 sm:text-3xl">{value}</p>
        </Link>
    );
}

function ProductList({ items, timeKey, empty }) {
    if (!items.length) return <p className="py-6 text-center text-sm text-zinc-500">{empty}</p>;
    return (
        <ul className="-my-2 divide-y divide-zinc-100">
            {items.map((p) => (
                <li key={p.id}>
                    <Link href={`/admin/products/${p.id}/edit`} className="-mx-2 flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-zinc-50 active:scale-[0.99] active:bg-zinc-50">
                        <span className="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-zinc-100">{p.thumb && <img src={p.thumb} alt="" className="h-full w-full object-cover" loading="lazy" />}</span>
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium text-zinc-900">{p.name}</span>
                            <span className="block truncate text-xs text-zinc-500">
                                {p.price}
                                {p.category ? ` · ${p.category}` : ''} · {p[timeKey]}
                            </span>
                        </span>
                        <StatusBadge status={p.status} />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/** Phones: big, thumb-friendly shortcuts to the most common jobs. */
function QuickActions() {
    const actions = [
        { href: '/admin/products/create', label: 'Add product', icon: Plus, tone: 'border-zinc-900 bg-zinc-900 text-white' },
        { href: '/admin/categories/create', label: 'Add category', icon: FolderPlus, tone: 'border-zinc-200/80 bg-white text-zinc-900' },
        { href: '/admin/content', label: 'Edit homepage', icon: PanelsTopLeft, tone: 'border-zinc-200/80 bg-white text-zinc-900' },
        { href: '/admin/settings', label: 'Settings', icon: Settings, tone: 'border-zinc-200/80 bg-white text-zinc-900' },
    ];
    return (
        <div className="mb-6 grid grid-cols-2 gap-3 sm:hidden">
            {actions.map(({ href, label, icon: Icon, tone }) => (
                <Link key={href} href={href} className={`flex h-[4.75rem] flex-col justify-between rounded-2xl border p-3.5 shadow-xs transition active:scale-[0.97] ${tone}`}>
                    <Icon size={20} />
                    <span className="text-sm font-semibold">{label}</span>
                </Link>
            ))}
        </div>
    );
}

function greeting() {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

export default function Dashboard({ stats, recentlyAdded, recentlyUpdated, setup }) {
    const pending = setup.filter((s) => !s.done);
    const { auth } = usePage().props;
    const firstName = (auth.user?.name || '').split(' ')[0];
    const done = setup.length - pending.length;

    return (
        <AdminLayout title="Overview">
            <div className="sm:hidden">
                <p className="text-sm font-medium text-zinc-500">
                    {greeting()}
                    {firstName ? `, ${firstName}` : ''}
                </p>
                <h1 className="mt-0.5 mb-5 text-[1.75rem] leading-tight font-bold tracking-tight text-zinc-900">Your store today</h1>
                <QuickActions />
            </div>
            <div className="hidden sm:block">
                <PageHeader
                    title="Overview"
                    description="A quick summary of your catalogue."
                    actions={
                        <>
                            <Link href="/admin/categories/create" className="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                                <FolderPlus size={16} /> Add category
                            </Link>
                            <Link href="/admin/products/create" className="inline-flex h-10 items-center gap-2 rounded-lg bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-700">
                                <Plus size={16} /> Add product
                            </Link>
                        </>
                    }
                />
            </div>

            {pending.length > 0 && (
                <Card title="Finish setting up your website" description="Complete these steps so customers can find and contact you." className="mb-6">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100">
                            <div className="h-full rounded-full bg-emerald-500 transition-[width] duration-700" style={{ width: `${(done / setup.length) * 100}%` }} />
                        </div>
                        <span className="shrink-0 text-xs font-semibold text-zinc-600">
                            {done} of {setup.length} done
                        </span>
                    </div>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {setup.map((s) => (
                            <li key={s.label}>
                                <Link href={s.href} className="flex items-center gap-3 rounded-xl border border-zinc-100 px-3 py-3 text-[15px] transition hover:bg-zinc-50 active:scale-[0.99] active:bg-zinc-50 sm:rounded-lg sm:py-2.5 sm:text-sm">
                                    {s.done ? <CheckCircle2 size={20} className="shrink-0 text-emerald-600" /> : <Circle size={20} className="shrink-0 text-zinc-300" />}
                                    <span className={`flex-1 ${s.done ? 'text-zinc-400 line-through' : 'font-medium text-zinc-800'}`}>{s.label}</span>
                                    {!s.done && <ChevronRight size={16} className="shrink-0 text-zinc-300" />}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {/* Phones: a swipeable row of stats; larger screens: a grid. */}
            <div className="no-scrollbar -mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-5">
                <Stat label="Total products" value={stats.products} icon={Package} href="/admin/products" />
                <Stat label="Published" value={stats.published} icon={PackageCheck} href="/admin/products?status=published" />
                <Stat label="Drafts" value={stats.drafts} icon={FileEdit} href="/admin/products?status=draft" />
                <Stat label="Featured" value={stats.featured} icon={Star} href="/admin/products?featured=1" />
                <Stat label="Categories" value={stats.categories} icon={FolderTree} href="/admin/categories" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card title="Recently added" actions={<Link href="/admin/products" className="text-sm font-medium text-zinc-600 hover:text-zinc-900">View all</Link>}>
                    <ProductList items={recentlyAdded} timeKey="created_at" empty="No products yet. Add your first product to get started." />
                </Card>
                <Card title="Recently updated">
                    <ProductList items={recentlyUpdated} timeKey="updated_at" empty="Nothing updated yet." />
                </Card>
            </div>
        </AdminLayout>
    );
}
