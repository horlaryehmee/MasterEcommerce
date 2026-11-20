/** Dims the product grid while a filter, sort or page change is loading. */
export default function GridLoading({ loading, children }) {
    return (
        <div aria-busy={loading || undefined} className={`transition-opacity duration-200 ${loading ? 'pointer-events-none opacity-45 motion-safe:animate-shimmer' : ''}`}>
            {children}
        </div>
    );
}
