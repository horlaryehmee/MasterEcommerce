import { BadgeCheck, Clock, Headset, Heart, MessageCircle, ShieldCheck, Sparkles, Star, Tag, Truck } from 'lucide-react';
import useTheme from '@/hooks/useTheme';
import SectionHeading from '../SectionHeading';

// Keys match App\Models\HomepageSetting::WHY_ICONS.
export const WHY_ICONS = {
    'badge-check': BadgeCheck,
    tag: Tag,
    headset: Headset,
    'message-circle': MessageCircle,
    truck: Truck,
    'shield-check': ShieldCheck,
    sparkles: Sparkles,
    clock: Clock,
    heart: Heart,
    star: Star,
};

/** Volt and Atelier: a compact row of promises (delivery, warranty, returns…) instead of big cards. */
function Strip({ why }) {
    return (
        <section className="border-y border-stone-200 bg-white" aria-label={why.heading || 'Why shop with us'}>
            <div className={`container-page grid grid-cols-2 divide-stone-200 lg:divide-x ${why.items.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
                {why.items.map((item, i) => {
                    const Icon = WHY_ICONS[item.icon] || BadgeCheck;
                    return (
                        <div key={i} className="flex items-start gap-3 px-1 py-4 sm:gap-4 sm:py-6 lg:px-6 lg:first:pl-0">
                            <Icon size={26} strokeWidth={1.6} className="mt-0.5 shrink-0 text-accent" />
                            <div className="min-w-0">
                                <h3 className="text-[13px] font-semibold text-stone-900 sm:text-sm">{item.title}</h3>
                                {item.text && <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-stone-500 sm:text-[13px]">{item.text}</p>}
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

export default function WhyChooseUs({ why }) {
    const theme = useTheme();
    if (!why) return null;
    if (theme.why === 'strip') return <Strip why={why} />;
    return (
        <section className="bg-surface py-14 text-on-surface sm:py-20">
            <div className="container-page">
                <SectionHeading title={why.heading} subtitle={why.subheading} align="center" />
                <div className={`grid gap-4 sm:grid-cols-2 sm:gap-6 ${why.items.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}`}>
                    {why.items.map((item, i) => {
                        const Icon = WHY_ICONS[item.icon] || BadgeCheck;
                        return (
                            <div key={i} className="rounded-3xl bg-white p-6 sm:p-7">
                                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent/10 text-accent">
                                    <Icon size={24} strokeWidth={1.8} />
                                </span>
                                <h3 className="mt-5 text-base font-semibold text-stone-900">{item.title}</h3>
                                {item.text && <p className="mt-2 text-sm leading-relaxed text-stone-600">{item.text}</p>}
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
