import { useEffect, useId } from 'react';
import { Field, TextInput } from './ui';

const OTHER = '__other__';

/** Google Fonts stylesheet for one family (all its known weights, or just regular for a typed name). */
export function fontUrl(name, families) {
    if (!name) return null;
    const known = families.find((f) => f.value === name);
    if (known) return known.url;
    return /^[A-Za-z0-9][A-Za-z0-9 ]{0,59}$/.test(name) ? `https://fonts.googleapis.com/css2?family=${name.trim().replace(/ /g, '+')}&display=swap` : null;
}

/** Loads a font into the admin page so previews show it. */
export function useGoogleFont(url) {
    useEffect(() => {
        if (!url || document.querySelector(`link[data-font-preview="${CSS.escape(url)}"]`)) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        link.dataset.fontPreview = url;
        document.head.appendChild(link);
    }, [url]);
}

/** CSS font-family for previews. */
export function fontStack(name, families, builtIn) {
    if (!name || name === builtIn) return "'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, sans-serif";
    const category = families.find((f) => f.value === name)?.category;
    return `'${name}', ${category === 'Serif' ? 'Georgia, serif' : category === 'Script' ? 'cursive' : 'ui-sans-serif, system-ui, sans-serif'}`;
}

/**
 * Font choice: the built-in font, a curated Google Font, or any other Google
 * Fonts family typed by name. `value` is '' for the built-in font.
 */
export default function FontPicker({ label, help, value, onChange, error, families, builtIn, custom, onCustom }) {
    const id = useId();
    const known = !value || families.some((f) => f.value === value);
    const selected = custom || !known ? OTHER : value;
    const groups = families.reduce((acc, f) => ({ ...acc, [f.category]: [...(acc[f.category] || []), f] }), {});

    useGoogleFont(fontUrl(value, families));

    return (
        <div className="space-y-3">
            <Field label={label} help={help} htmlFor={id} error={selected !== OTHER ? error : undefined}>
                <select
                    id={id}
                    className={`input ${error && selected !== OTHER ? 'input-error' : ''}`}
                    value={selected}
                    onChange={(e) => {
                        const v = e.target.value;
                        if (v === OTHER) {
                            onCustom(true);
                            onChange('');
                        } else {
                            onCustom(false);
                            onChange(v);
                        }
                    }}
                >
                    <option value="">{builtIn} (built in)</option>
                    {Object.entries(groups).map(([category, list]) => (
                        <optgroup key={category} label={category}>
                            {list.map((f) => (
                                <option key={f.value} value={f.value}>
                                    {f.value}
                                </option>
                            ))}
                        </optgroup>
                    ))}
                    <option value={OTHER}>Other Google Font…</option>
                </select>
            </Field>
            {selected === OTHER && (
                <TextInput
                    label="Google Font name"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    error={error}
                    maxLength={60}
                    placeholder="e.g. Space Grotesk"
                    help={
                        <>
                            Exactly as written on{' '}
                            <a href="https://fonts.google.com" target="_blank" rel="noopener noreferrer" className="font-medium underline">
                                fonts.google.com
                            </a>
                            . Only its regular weight is loaded.
                        </>
                    }
                />
            )}
        </div>
    );
}
