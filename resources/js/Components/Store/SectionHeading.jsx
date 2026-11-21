import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import useTheme from '@/hooks/useTheme';
import Reveal from './Reveal';

/**
 * Section title with an optional "View all" link (a compact "See all" on phones).
 * Left-aligned or centred as the template prefers, unless `align` is given.
 */
export default function SectionHeading({ title, subtitle, link, linkLabel = 'View all', align }) {
    const theme = useTheme();
    const centered = (align || theme.headings) === 'center';
    return (
        <Reveal className={`mb-6 flex gap-4 sm:mb-10 ${centered ? 'flex-col items-center text-center' : 'items-end justify-between'}`}>
            <div className="max-w-2xl min-w-0">
                {centered && <span className="mx-auto mb-3 block h-px w-10 bg-accent" aria-hidden />}
                <h2 className="text-[1.6rem] leading-tight font-bold tracking-tight text-balance text-stone-900 sm:text-3xl">{title}</h2>
                {subtitle && <p className="mt-1.5 text-sm text-stone-600 sm:mt-2 sm:text-[15px]">{subtitle}</p>}
            </div>
            {link && (
                <Link
                    href={link}
                    className="group -mr-2 inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1.5 text-sm font-semibold text-stone-900 transition active:scale-95 active:bg-stone-100 sm:mr-0 sm:gap-1.5 sm:px-0"
                    aria-label={linkLabel}
                >
                    <span className="sm:hidden">See all</span>
                    <span className="hidden sm:inline">{linkLabel}</span>
                    <ArrowRight size={16} className="transition-transform group-hover:translate-x-0.5" />
                </Link>
            )}
        </Reveal>
    );
}
