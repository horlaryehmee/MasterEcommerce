import { Check, Link2, Share2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FacebookIcon, WhatsAppIcon, XIcon } from '@/Components/Icons';

export default function ShareButtons({ url, title }) {
    const [copied, setCopied] = useState(false);
    const [canShare, setCanShare] = useState(false);

    useEffect(() => setCanShare(typeof navigator !== 'undefined' && !!navigator.share), []);

    const enc = encodeURIComponent;
    const links = [
        { label: 'Share on WhatsApp', icon: WhatsAppIcon, href: `https://wa.me/?text=${enc(`${title} ${url}`)}` },
        { label: 'Share on Facebook', icon: FacebookIcon, href: `https://www.facebook.com/sharer/sharer.php?u=${enc(url)}` },
        { label: 'Share on X', icon: XIcon, href: `https://x.com/intent/post?url=${enc(url)}&text=${enc(title)}` },
    ];

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // Fallback for older browsers / insecure origins.
            const el = document.createElement('textarea');
            el.value = url;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            el.remove();
        }
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const nativeShare = () => navigator.share({ title, url }).catch(() => {});

    const btn = 'flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 text-stone-700 transition-colors hover:border-stone-900 hover:text-stone-900';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <span className="mr-1 text-sm font-medium text-stone-600">Share:</span>
            {canShare && (
                <button type="button" onClick={nativeShare} className={btn} aria-label="Share">
                    <Share2 size={17} />
                </button>
            )}
            {links.map(({ label, icon: Icon, href }) => (
                <a key={label} href={href} target="_blank" rel="noopener noreferrer" className={btn} aria-label={label}>
                    <Icon size={17} />
                </a>
            ))}
            <button type="button" onClick={copy} className={btn} aria-label="Copy link">
                {copied ? <Check size={17} className="text-emerald-600" /> : <Link2 size={17} />}
            </button>
            <span className="sr-only" aria-live="polite">
                {copied ? 'Link copied' : ''}
            </span>
        </div>
    );
}
