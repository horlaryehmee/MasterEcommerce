import { ImageOff } from 'lucide-react';

/**
 * Responsive, lazy-loaded image. `image` is the payload built by
 * App\Support\Media::image() ({ src, sm, srcset, alt }).
 */
export default function Img({ image, sizes = '100vw', className = '', eager = false, alt }) {
    if (!image?.src) {
        return (
            <div className={`flex h-full w-full items-center justify-center bg-stone-100 text-stone-300 ${className}`}>
                <ImageOff size={32} strokeWidth={1.5} aria-hidden />
                <span className="sr-only">{alt || 'No image available'}</span>
            </div>
        );
    }

    return (
        <img
            src={image.src}
            srcSet={image.srcset || undefined}
            sizes={image.srcset ? sizes : undefined}
            alt={alt ?? image.alt ?? ''}
            loading={eager ? 'eager' : 'lazy'}
            fetchPriority={eager ? 'high' : undefined}
            decoding="async"
            className={className}
        />
    );
}
