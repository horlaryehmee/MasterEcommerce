// Brand icons (Lucide no longer ships these). Outline style to match Lucide.
// Paths adapted from Tabler Icons (MIT licence).
const base = {
    xmlns: 'http://www.w3.org/2000/svg',
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.8,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    'aria-hidden': true,
};

const make = (paths) =>
    function Icon({ size = 20, className = '' }) {
        return (
            <svg {...base} width={size} height={size} className={className}>
                {paths}
            </svg>
        );
    };

export const WhatsAppIcon = make(
    <>
        <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21" />
        <path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1" />
    </>,
);

export const InstagramIcon = make(
    <>
        <rect x="3" y="3" width="18" height="18" rx="5" />
        <circle cx="12" cy="12" r="4" />
        <path d="M17.5 6.5h.01" />
    </>,
);

export const FacebookIcon = make(<path d="M7 10v4h3v7h4v-7h3l1-4h-4V8a1 1 0 0 1 1-1h3V3h-3a5 5 0 0 0-5 5v2H7" />);

export const TikTokIcon = make(
    <path d="M21 7.917v4.034a9.948 9.948 0 0 1-5-1.951v4.5a6.5 6.5 0 1 1-8-6.326v4.326a2.5 2.5 0 1 0 4 2V3h4.083A6.005 6.005 0 0 0 21 7.917z" />,
);

export const XIcon = make(
    <>
        <path d="M4 4l11.733 16H20L8.267 4z" />
        <path d="M4 20l6.768-6.768M13.228 10.772L20 4" />
    </>,
);

export const YouTubeIcon = make(
    <>
        <path d="M2 8a4 4 0 0 1 4-4h12a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
        <path d="M10 9l5 3-5 3z" />
    </>,
);

export const LinkedInIcon = make(
    <>
        <path d="M8 11v5M8 8v.01M12 16v-5M16 16v-3a2 2 0 0 0-4 0" />
        <rect x="3" y="3" width="18" height="18" rx="2" />
    </>,
);

export const SOCIAL_ICONS = {
    instagram: InstagramIcon,
    facebook: FacebookIcon,
    tiktok: TikTokIcon,
    x: XIcon,
    youtube: YouTubeIcon,
    linkedin: LinkedInIcon,
};
