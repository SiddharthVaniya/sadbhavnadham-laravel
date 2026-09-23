/**
 * Helpers for the "Next day" control on admin/marketer period filters.
 * Show when the active range is exactly one calendar day before today.
 */

/**
 * @param {Date} [date]
 * @returns {string} YYYY-MM-DD in local time
 */
export function todayYmd(date = new Date()) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {string} ymd YYYY-MM-DD
 * @param {number} deltaDays
 * @returns {string|null}
 */
export function addDaysYmd(ymd, deltaDays) {
    if (! ymd || ! /^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
        return null;
    }

    const [year, month, day] = ymd.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    date.setDate(date.getDate() + deltaDays);

    return todayYmd(date);
}

/**
 * @param {string} ymd YYYY-MM-DD
 * @returns {string|null}
 */
export function nextDayYmd(ymd) {
    return addDaysYmd(ymd, 1);
}

/**
 * Resolve the single calendar day represented by the current filter, if any.
 *
 * @param {{ duration?: string, fromDate?: string, toDate?: string, todayYmd?: string }} opts
 * @returns {string|null} YYYY-MM-DD or null when not a single-day range
 */
export function resolveSingleDayYmd({ duration, fromDate, toDate, todayYmd: today = todayYmd() }) {
    const from = (fromDate || '').trim();
    const to = (toDate || '').trim();

    if (duration === 'custom') {
        if (from && to && from === to) {
            return from;
        }

        return null;
    }

    if (duration === 'today') {
        return today;
    }

    if (duration === 'yesterday') {
        return addDaysYmd(today, -1);
    }

    return null;
}

/**
 * @param {{ duration?: string, fromDate?: string, toDate?: string, todayYmd?: string }} opts
 * @returns {boolean}
 */
export function isSingleDayBeforeToday(opts) {
    const today = opts.todayYmd || todayYmd();
    const day = resolveSingleDayYmd({ ...opts, todayYmd: today });

    return Boolean(day && day < today);
}

/**
 * Build query overrides that advance a single-day filter by +1 day.
 *
 * @param {Record<string, unknown>} query Current filter query (any shape)
 * @param {{ duration?: string, fromDate?: string, toDate?: string, todayYmd?: string }} opts
 * @returns {Record<string, unknown>|null}
 */
export function shiftToNextDayQuery(query, opts) {
    const today = opts.todayYmd || todayYmd();
    const day = resolveSingleDayYmd({ ...opts, todayYmd: today });

    if (! day || day >= today) {
        return null;
    }

    const next = nextDayYmd(day);

    if (! next) {
        return null;
    }

    return {
        ...query,
        duration: 'custom',
        from_date: next,
        to_date: next,
    };
}
