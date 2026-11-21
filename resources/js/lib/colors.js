/**
 * Colour names → swatch colours, for the variation editor: typing "Yellow"
 * (or a near miss like "yello" or "burgandy") picks the colour automatically.
 * Covers the CSS colour names plus the names shops actually use (wine, nude,
 * mustard, rose gold, ankara-friendly brights…).
 */
const NAMES = {
    // Shop & fashion names first (they win over CSS names with the same spelling).
    black: '#111111',
    white: '#FFFFFF',
    offwhite: '#F5F3EE',
    cream: '#F3E9D2',
    ivory: '#FFFFF0',
    beige: '#E8DCC4',
    nude: '#E3BC9A',
    champagne: '#F1DDBF',
    sand: '#D8C3A0',
    camel: '#C19A6B',
    tan: '#D2B48C',
    khaki: '#BDB07A',
    brown: '#7B4B2A',
    chocolate: '#5C3317',
    coffee: '#6F4E37',
    cognac: '#9A463D',
    rust: '#B7410E',
    caramel: '#AF6E4D',
    grey: '#9CA3AF',
    gray: '#9CA3AF',
    lightgrey: '#D1D5DB',
    lightgray: '#D1D5DB',
    darkgrey: '#4B5563',
    darkgray: '#4B5563',
    charcoal: '#36454F',
    ash: '#B2BEB5',
    silver: '#C0C0C0',
    gold: '#D4A017',
    rosegold: '#B76E79',
    bronze: '#CD7F32',
    copper: '#B87333',
    red: '#DC2626',
    wine: '#722F37',
    burgundy: '#800020',
    maroon: '#800000',
    cherry: '#990F02',
    crimson: '#DC143C',
    scarlet: '#FF2400',
    orange: '#F97316',
    peach: '#FFCBA4',
    coral: '#FF7F50',
    salmon: '#FA8072',
    apricot: '#FBCEB1',
    yellow: '#FACC15',
    mustard: '#E1AD01',
    lemon: '#FFF44F',
    green: '#16A34A',
    darkgreen: '#14532D',
    lightgreen: '#86EFAC',
    lime: '#84CC16',
    olive: '#808000',
    armygreen: '#4B5320',
    emerald: '#10B981',
    mint: '#98FF98',
    mintgreen: '#98FF98',
    sage: '#9CAF88',
    teal: '#0D9488',
    turquoise: '#40E0D0',
    aqua: '#00FFFF',
    blue: '#2563EB',
    lightblue: '#93C5FD',
    skyblue: '#87CEEB',
    babyblue: '#89CFF0',
    royalblue: '#4169E1',
    navy: '#1E2A5A',
    navyblue: '#1E2A5A',
    denim: '#1560BD',
    cobalt: '#0047AB',
    indigo: '#4B0082',
    purple: '#7E22CE',
    violet: '#8F00FF',
    lilac: '#C8A2C8',
    lavender: '#B57EDC',
    plum: '#8E4585',
    mauve: '#E0B0FF',
    magenta: '#FF00FF',
    fuchsia: '#D946EF',
    pink: '#EC4899',
    hotpink: '#FF69B4',
    babypink: '#F4C2C2',
    lightpink: '#FFB6C1',
    blush: '#DE5D83',
    rose: '#E11D48',
    clear: '#F5F5F4',
    transparent: '#F5F5F4',
    // Hair colours
    blonde: '#E8C872',
    honeyblonde: '#D6A85C',
    platinum: '#E5E4E2',
    jetblack: '#0A0A0A',
    naturalblack: '#1C1C1C',
    darkbrown: '#3B2416',
    lightbrown: '#A67B5B',
    auburn: '#A52A2A',
    ginger: '#B06500',
    // Remaining CSS colour names.
    aliceblue: '#F0F8FF',
    antiquewhite: '#FAEBD7',
    aquamarine: '#7FFFD4',
    azure: '#F0FFFF',
    bisque: '#FFE4C4',
    blanchedalmond: '#FFEBCD',
    blueviolet: '#8A2BE2',
    burlywood: '#DEB887',
    cadetblue: '#5F9EA0',
    chartreuse: '#7FFF00',
    cornflowerblue: '#6495ED',
    cornsilk: '#FFF8DC',
    cyan: '#00FFFF',
    darkblue: '#00008B',
    darkcyan: '#008B8B',
    darkgoldenrod: '#B8860B',
    darkkhaki: '#BDB76B',
    darkmagenta: '#8B008B',
    darkolivegreen: '#556B2F',
    darkorange: '#FF8C00',
    darkorchid: '#9932CC',
    darkred: '#8B0000',
    darksalmon: '#E9967A',
    darkseagreen: '#8FBC8F',
    darkslateblue: '#483D8B',
    darkslategray: '#2F4F4F',
    darkturquoise: '#00CED1',
    darkviolet: '#9400D3',
    deeppink: '#FF1493',
    deepskyblue: '#00BFFF',
    dimgray: '#696969',
    dodgerblue: '#1E90FF',
    firebrick: '#B22222',
    floralwhite: '#FFFAF0',
    forestgreen: '#228B22',
    gainsboro: '#DCDCDC',
    ghostwhite: '#F8F8FF',
    goldenrod: '#DAA520',
    greenyellow: '#ADFF2F',
    honeydew: '#F0FFF0',
    indianred: '#CD5C5C',
    lavenderblush: '#FFF0F5',
    lawngreen: '#7CFC00',
    lemonchiffon: '#FFFACD',
    lightcoral: '#F08080',
    lightcyan: '#E0FFFF',
    lightgoldenrodyellow: '#FAFAD2',
    lightsalmon: '#FFA07A',
    lightseagreen: '#20B2AA',
    lightskyblue: '#87CEFA',
    lightslategray: '#778899',
    lightsteelblue: '#B0C4DE',
    lightyellow: '#FFFFE0',
    limegreen: '#32CD32',
    linen: '#FAF0E6',
    mediumaquamarine: '#66CDAA',
    mediumblue: '#0000CD',
    mediumorchid: '#BA55D3',
    mediumpurple: '#9370DB',
    mediumseagreen: '#3CB371',
    mediumslateblue: '#7B68EE',
    mediumspringgreen: '#00FA9A',
    mediumturquoise: '#48D1CC',
    mediumvioletred: '#C71585',
    midnightblue: '#191970',
    mintcream: '#F5FFFA',
    mistyrose: '#FFE4E1',
    moccasin: '#FFE4B5',
    navajowhite: '#FFDEAD',
    oldlace: '#FDF5E6',
    olivedrab: '#6B8E23',
    orangered: '#FF4500',
    orchid: '#DA70D6',
    palegoldenrod: '#EEE8AA',
    palegreen: '#98FB98',
    paleturquoise: '#AFEEEE',
    palevioletred: '#DB7093',
    papayawhip: '#FFEFD5',
    peachpuff: '#FFDAB9',
    peru: '#CD853F',
    powderblue: '#B0E0E6',
    rebeccapurple: '#663399',
    rosybrown: '#BC8F8F',
    saddlebrown: '#8B4513',
    seagreen: '#2E8B57',
    seashell: '#FFF5EE',
    sienna: '#A0522D',
    slateblue: '#6A5ACD',
    slategray: '#708090',
    snow: '#FFFAFA',
    springgreen: '#00FF7F',
    steelblue: '#4682B4',
    thistle: '#D8BFD8',
    tomato: '#FF6347',
    wheat: '#F5DEB3',
    whitesmoke: '#F5F5F5',
    yellowgreen: '#9ACD32',
};

const KEYS = Object.keys(NAMES);

/** Edit distance, for near misses ("yelow", "burgandy"). */
function distance(a, b) {
    if (Math.abs(a.length - b.length) > 2) return 99;
    let prev = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const row = [i];
        for (let j = 1; j <= b.length; j++) row[j] = Math.min(prev[j] + 1, row[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        prev = row;
    }
    return prev[b.length];
}

function lookup(word) {
    if (!word) return null;
    if (NAMES[word]) return NAMES[word];
    // Unfinished word: "yello" → yellow, "burg" → burgundy (shortest name that starts with it).
    if (word.length >= 3) {
        const starts = KEYS.filter((k) => k.startsWith(word)).sort((a, b) => a.length - b.length)[0];
        if (starts) return NAMES[starts];
    }
    // Typo: one wrong letter in short names, two in longer ones.
    if (word.length >= 4) {
        const allowed = word.length >= 7 ? 2 : 1;
        let best = null;
        let bestD = allowed + 1;
        for (const k of KEYS) {
            const d = distance(word, k);
            if (d < bestD) [best, bestD] = [k, d];
        }
        if (best) return NAMES[best];
    }
    return null;
}

/**
 * Best swatch colour for a colour name, or null when nothing matches
 * (e.g. "Ankara print"). Tries the whole name ("Navy Blue"), then each word
 * from the last ("Wine Red" → red is a fallback only if "winered" is unknown).
 */
export function guessColor(name) {
    const clean = String(name || '')
        .toLowerCase()
        .replace(/colou?r|shade/g, '')
        .replace(/[^a-z ]/g, ' ')
        .trim();
    if (!clean) return null;
    const joined = clean.replace(/\s+/g, '');
    if (NAMES[joined]) return NAMES[joined];
    const words = clean.split(/\s+/);
    // "Light Pink", "Dark Brown": modifier + colour.
    for (let i = 0; i < words.length - 1; i++) {
        const pair = words[i] + words[i + 1];
        if (NAMES[pair]) return NAMES[pair];
    }
    for (const word of [...words].reverse()) {
        const hit = lookup(word);
        if (hit) return hit;
    }
    return lookup(joined);
}
