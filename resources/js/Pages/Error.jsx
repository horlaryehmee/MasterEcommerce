import { Head, Link, usePage } from '@inertiajs/react';

const MESSAGES = {
    403: ['Access denied', "You don't have permission to view this page."],
    404: ['Page not found', "Sorry, we couldn't find the page you were looking for. It may have been moved or removed."],
    405: ["That didn't work", 'This page can’t be opened that way. Go back, reload the page and try again.'],
    429: ['Too many requests', 'Please wait a moment and try again.'],
    500: ['Something went wrong', 'An unexpected error occurred on our side. Please try again shortly.'],
    503: ['Be right back', "We're making some improvements. Please check back in a few minutes."],
};

/** Standalone error page (it may render before shared props are available). */
export default function Error({ status }) {
    const brand = usePage().props.brand;
    const [title, message] = MESSAGES[status] || MESSAGES[500];

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-stone-50 px-6 text-center">
            <Head title={`${title}${brand?.name ? ' | ' + brand.name : ''}`} />
            {brand?.logo ? <img src={brand.logo} alt={brand.name} className="mb-10 h-10 w-auto" /> : brand?.name && <p className="mb-10 text-xl font-extrabold">{brand.name}</p>}
            <p className="text-sm font-semibold tracking-widest text-stone-400">ERROR {status}</p>
            <h1 className="mt-3 text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{title}</h1>
            <p className="mt-4 max-w-md text-stone-600">{message}</p>
            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link href="/" className="btn-primary">
                    Go to homepage
                </Link>
                <Link href="/products" className="btn-outline">
                    Browse products
                </Link>
            </div>
        </div>
    );
}
