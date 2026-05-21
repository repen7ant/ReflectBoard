// ─── Date ─────────────────────────────────────────────
export function formatDate(dt, { withTime = false, showYear = true } = {}) {
    if (!dt) return '';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt;

    const dateOpts = { month: 'short', day: 'numeric', ...(showYear ? { year: 'numeric' } : {}) };
    const isDateOnly = dt.length === 10 || (d.getHours() === 0 && d.getMinutes() === 0 && d.getSeconds() === 0);

    if (withTime && !isDateOnly) {
        const datePart = d.toLocaleDateString('en-US', dateOpts);
        const timePart = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        return `${datePart} at ${timePart}`;
    }

    if (isDateOnly) return d.toLocaleDateString('en-US', dateOpts);
    return d.toLocaleString('en-US', { ...dateOpts, hour: '2-digit', minute: '2-digit' });
}

// ─── Duration ─────────────────────────────────────────
// null/undefined/'' → '' (no value)
// 0 → '0m' (explicit zero)
export function formatMinutes(minutes) {
    if (minutes == null || minutes === '') return '';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h > 0 && m > 0) return `${h}h ${m}m`;
    if (h > 0)          return `${h}h`;
    return `${m}m`;
}
