import Breadcrumbs from '@/Components/Store/Breadcrumbs';
import StoreLayout from '@/Layouts/StoreLayout';

export default function Page({ page }) {
    return (
        <StoreLayout>
            <div className="border-b border-stone-100 bg-stone-50/70">
                <div className="container-page py-8 sm:py-12">
                    <Breadcrumbs items={[{ label: 'Home', href: '/' }, { label: page.title }]} />
                    <h1 className="mt-4 text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{page.title}</h1>
                </div>
            </div>

            <div className="container-page py-10 sm:py-14">
                <div className="max-w-3xl">
                    {page.content_html && <div className="rich-text" dangerouslySetInnerHTML={{ __html: page.content_html }} />}
                </div>
            </div>
        </StoreLayout>
    );
}
