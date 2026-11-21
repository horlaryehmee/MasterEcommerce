import '../css/app.css';
import '@fontsource-variable/plus-jakarta-sans';

import { createInertiaApp, router } from '@inertiajs/react';
import axios from 'axios';
import { createRoot } from 'react-dom/client';
import { xsrfCookieName } from './lib/csrf';
import { trackPageView } from './lib/analytics';
import { statsPageview } from './lib/stats';

// Inertia sends the CSRF token from this app's own cookie (see App\Http\Middleware\ValidateCsrfToken).
axios.defaults.xsrfCookieName = xsrfCookieName();

createInertiaApp({
    // The server already renders the full <title> (including the business name).
    title: (title) => title,
    // Each page is a separate chunk, so storefront visitors never download admin code.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }
        return page().then((module) => module.default);
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);

        // Analytics page views for in-app navigation (the first page view is sent by the pixel snippets).
        let initial = true;
        let lastUrl = null;
        router.on('navigate', (event) => {
            const { props, url } = event.detail.page;
            // Inertia can fire twice for the same address (e.g. when the server reorders
            // query parameters), and form posts return to the same page: neither is a new page view.
            if (url === lastUrl) return;
            lastUrl = url;
            // Let the new page set its <title> first.
            setTimeout(() => {
                // Built-in visitor stats: every page, including the first.
                // The server's page title (document.title may not be updated yet).
                const title = props.seo?.title;
                statsPageview(props.stats, title);
                if (!initial) trackPageView(props.tracking, title);
                initial = false;
            }, 0);
        });
    },
    progress: {
        color: '#c2410c',
        delay: 150,
    },
});
