/**
 * Calendar period keys shared with backend PeriodRange / admin duration maps.
 * Server labels win; these fill gaps if an older payload omits a key.
 */
export const CALENDAR_PERIOD_LABELS = {
    yesterday: 'Yesterday',
    this_week: 'This week',
    last_week: 'Previous week',
    this_month: 'This month',
    last_month: 'Previous month',
};

export const CALENDAR_PERIOD_KEYS = Object.keys(CALENDAR_PERIOD_LABELS);

/**
 * Merge server durationOptions with calendar periods, keeping server order/labels
 * and inserting any missing calendar keys after `today` (or before `this_month`).
 *
 * @param {Record<string, string>|null|undefined} serverOptions
 * @returns {Record<string, string>}
 */
export function mergeDurationOptions(serverOptions = {}) {
    const server = serverOptions && typeof serverOptions === 'object' ? serverOptions : {};
    const merged = { ...CALENDAR_PERIOD_LABELS, ...server };
    const serverKeys = Object.keys(server);
    const missing = CALENDAR_PERIOD_KEYS.filter((key) => ! serverKeys.includes(key));

    if (missing.length === 0) {
        return merged;
    }

    const order = [...serverKeys];
    const todayIndex = order.indexOf('today');
    const thisMonthIndex = order.indexOf('this_month');

    if (todayIndex !== -1) {
        order.splice(todayIndex + 1, 0, ...missing);
    } else if (thisMonthIndex !== -1) {
        order.splice(thisMonthIndex, 0, ...missing);
    } else {
        order.unshift(...missing);
    }

    const seen = new Set();
    const result = {};

    for (const key of order) {
        if (seen.has(key) || merged[key] === undefined) {
            continue;
        }

        seen.add(key);
        result[key] = merged[key];
    }

    for (const [key, label] of Object.entries(merged)) {
        if (! seen.has(key)) {
            result[key] = label;
        }
    }

    return result;
}
