import { BadgeCheck, Clock, Headset, Heart, MessageCircle, ShieldCheck, Sparkles, Star, Tag, Truck } from 'lucide-react';
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

export default function WhyChooseUs({ why }) {
    if (!why) return null;
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
