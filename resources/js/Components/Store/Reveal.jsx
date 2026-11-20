import useReveal from '@/hooks/useReveal';

/** Fades and rises its content into view on scroll. `delay` (ms) staggers neighbours. */
export default function Reveal({ as: Component = 'div', delay = 0, className = '', style, children, ...props }) {
    const ref = useReveal();
    return (
        <Component ref={ref} data-reveal="" className={className} style={delay ? { ...style, '--reveal-delay': `${delay}ms` } : style} {...props}>
            {children}
        </Component>
    );
}
