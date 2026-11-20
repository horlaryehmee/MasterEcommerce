/** Client-side price formatting; mirrors App\Support\Money::format(). */
export function formatMoney(amount, f) {
    const decimals = Number(f.decimals) || 0;
    const [int, frac] = Math.abs(Number(amount) || 0)
        .toFixed(decimals)
        .split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, f.thousands ?? '');
    const number = (amount < 0 ? '-' : '') + (frac ? `${grouped}${f.decimal || '.'}${frac}` : grouped);

    if (!f.symbol) return `${number} ${f.code}`;
    if (f.position === 'after') return `${number} ${f.symbol}`;
    return `${f.symbol}${f.symbol.length > 1 ? ' ' : ''}${number}`;
}
