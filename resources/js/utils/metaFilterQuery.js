const ADMIN_FILTER_KEYS = [
    'q',
    'user_id',
    'meta_ad_account_id',
    'app_id',
    'from_date',
    'to_date',
    'campaign',
    'adset',
    'theme',
    'cause',
    'match',
];

const MARKETER_FILTER_KEYS = [
    'q',
    'meta_ad_account_id',
    'app_id',
    'from_date',
    'to_date',
    'campaign',
    'adset',
    'theme',
    'cause',
];

export function metaFilterQueryString(filters, keys = ADMIN_FILTER_KEYS) {
    const params = new URLSearchParams();

    keys.forEach((key) => {
        const value = filters?.[key];
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();

    return query ? `?${query}` : '';
}

export function metaAdminTabHref(path, filters) {
    return `${path}${metaFilterQueryString(filters, ADMIN_FILTER_KEYS)}`;
}

export function metaMarketerTabHref(path, filters) {
    return `${path}${metaFilterQueryString(filters, MARKETER_FILTER_KEYS)}`;
}
