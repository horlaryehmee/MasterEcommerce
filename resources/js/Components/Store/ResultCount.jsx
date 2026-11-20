/** Drop empty/default values so catalog URLs stay short and clean. */
export function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([k, v]) => v !== '' && v != null && !(k === 'sort' && v === 'latest')));
}

export default function ResultCount({ products }) {
    if (!products.total) return null;
    return (
        <p className="text-sm text-stone-600">
            Showing <span className="font-medium text-stone-900">{products.from}</span>–<span className="font-medium text-stone-900">{products.to}</span> of{' '}
            <span className="font-medium text-stone-900">{products.total}</span> {products.total === 1 ? 'product' : 'products'}
        </p>
    );
}
