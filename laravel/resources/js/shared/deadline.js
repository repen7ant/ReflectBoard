// Parse activity.deadline ISO string → form fields {date, time}
// Time "00:00" treated as empty (no time specified).
export function parseDeadline(dt) {
    if (!dt) return { date: '', time: '' };
    const [datePart, timePart = ''] = dt.split('T');
    const timeRaw = timePart.slice(0, 5);
    return {
        date: datePart || '',
        time: timeRaw === '00:00' ? '' : timeRaw,
    };
}

// Build deadline string from form fields. Empty date → null.
export function buildDeadline(date, time) {
    if (!date) return null;
    return `${date}T${time || '00:00'}:00`;
}

// Classify deadline urgency by calendar-date proximity to today.
export function deadlineStatus(dt) {
    if (!dt) return '';
    const now = new Date();
    const todayMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const d = new Date(dt);
    const dMidnight = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    if (dMidnight < todayMidnight) return 'overdue';
    if (dMidnight.getTime() === todayMidnight.getTime()) return 'today';
    const soonMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 4);
    if (dMidnight < soonMidnight) return 'soon';
    return '';
}
