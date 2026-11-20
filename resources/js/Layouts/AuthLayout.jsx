import { Head, usePage } from '@inertiajs/react';

export default function AuthLayout({ title, subtitle, children }) {
    const { brand } = usePage().props;
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-zinc-50 px-4 py-12">
            <Head title={`${title} · ${brand.name}`} />
            <div className="mb-8">
                {brand.logo ? <img src={brand.logo} alt={brand.name} className="h-10 w-auto" /> : <span className="text-2xl font-extrabold">{brand.name}</span>}
            </div>
            <div className="w-full max-w-sm rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
                <h1 className="text-xl font-bold tracking-tight text-zinc-900">{title}</h1>
                {subtitle && <p className="mt-1 text-sm text-zinc-500">{subtitle}</p>}
                <div className="mt-6">{children}</div>
            </div>
            <a href="/" className="mt-6 text-sm text-zinc-500 hover:text-zinc-900">
                ← Back to website
            </a>
        </div>
    );
}
