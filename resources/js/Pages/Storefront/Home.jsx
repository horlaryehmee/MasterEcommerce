import AboutSection from '@/Components/Store/Home/AboutSection';
import FeaturedCategories from '@/Components/Store/Home/FeaturedCategories';
import Hero from '@/Components/Store/Home/Hero';
import ProductSection from '@/Components/Store/Home/ProductSection';
import PromoBanner from '@/Components/Store/Home/PromoBanner';
import WhyChooseUs from '@/Components/Store/Home/WhyChooseUs';
import EmptyState from '@/Components/Store/EmptyState';
import StoreLayout from '@/Layouts/StoreLayout';
import { PackageOpen } from 'lucide-react';

export default function Home({ hero, categories, featured, newArrivals, promo, about, why }) {
    const isEmpty = !hero && !categories && !featured && !newArrivals && !promo && !about && !why;

    return (
        <StoreLayout>
            <Hero hero={hero} />
            <FeaturedCategories section={categories} />
            <ProductSection section={featured} tinted />
            <PromoBanner promo={promo} />
            <ProductSection section={newArrivals} link="/products?sort=latest" />
            <WhyChooseUs why={why} />
            <AboutSection about={about} />
            {isEmpty && (
                <div className="container-page py-20">
                    <EmptyState icon={PackageOpen} title="Our store is coming soon" message="We're adding our products right now. In the meantime, chat with us on WhatsApp — we're happy to help." />
                </div>
            )}
        </StoreLayout>
    );
}
