import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * Phone-style sheet that rises from the bottom of the screen. Close it by
 * dragging the handle down, tapping outside, the close button or Escape.
 * Rendered into <body>, so blurred/sticky parents can't trap its fixed position.
 */
export default function BottomSheet({ open, onClose, title, children }) {
    const [drag, setDrag] = useState(0);
    const start = useRef(null);
    const panel = useRef(null);

    useEffect(() => {
        if (!open) return;
        setDrag(0);
        const esc = (e) => e.key === 'Escape' && onClose();
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', esc);
        panel.current?.focus();
        return () => {
            document.body.style.overflow = previous;
            window.removeEventListener('keydown', esc);
        };
    }, [open, onClose]);

    if (!open) return null;

    const onPointerDown = (e) => {
        start.current = e.clientY;
        e.currentTarget.setPointerCapture(e.pointerId);
    };
    const onPointerMove = (e) => start.current !== null && setDrag(Math.max(0, e.clientY - start.current));
    const onPointerUp = () => {
        if (start.current === null) return;
        start.current = null;
        if (drag > 90) onClose();
        else setDrag(0);
    };

    return createPortal(
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label={title}>
            <div className="absolute inset-0 bg-stone-950/40 backdrop-blur-[2px] transition-opacity duration-300 starting:opacity-0" style={{ opacity: drag ? Math.max(0.2, 1 - drag / 400) : undefined }} onClick={onClose} />
            <div
                ref={panel}
                tabIndex={-1}
                className={`absolute inset-x-0 bottom-0 mx-auto max-h-[85svh] max-w-lg overflow-y-auto rounded-t-3xl bg-white pb-[calc(env(safe-area-inset-bottom)+1rem)] shadow-2xl outline-none starting:translate-y-full ${start.current === null ? 'transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)]' : ''}`}
                style={{ transform: `translateY(${drag}px)` }}
            >
                <div className="sticky top-0 z-10 bg-white/95 backdrop-blur">
                    <div className="flex cursor-grab touch-none justify-center pt-3 pb-1 active:cursor-grabbing" onPointerDown={onPointerDown} onPointerMove={onPointerMove} onPointerUp={onPointerUp} onPointerCancel={onPointerUp}>
                        <span className="h-1.5 w-10 rounded-full bg-stone-300" aria-hidden />
                    </div>
                    <div className="flex items-center justify-between px-5 pt-1 pb-3">
                        <p className="text-base font-semibold text-stone-900">{title}</p>
                        <button type="button" onClick={onClose} className="-mr-2 rounded-full p-2 text-stone-500 transition hover:bg-stone-100 active:scale-90" aria-label="Close">
                            <X size={20} />
                        </button>
                    </div>
                </div>
                <div className="px-3">{children}</div>
            </div>
        </div>,
        document.body,
    );
}
