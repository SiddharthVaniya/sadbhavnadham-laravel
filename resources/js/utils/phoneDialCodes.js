/**
 * Common dial codes for admin offline / edit donation forms.
 * iso = ISO 3166-1 alpha-2, dial = E.164 country calling code (digits only).
 */
export const phoneDialCodes = [
    { iso: 'IN', dial: '91', label: 'India (+91)', country: 'INDIA' },
    { iso: 'GB', dial: '44', label: 'United Kingdom (+44)', country: 'UNITED KINGDOM' },
    { iso: 'US', dial: '1', label: 'United States (+1)', country: 'UNITED STATES' },
    { iso: 'CA', dial: '1', label: 'Canada (+1)', country: 'CANADA' },
    { iso: 'AE', dial: '971', label: 'UAE (+971)', country: 'UNITED ARAB EMIRATES' },
    { iso: 'AU', dial: '61', label: 'Australia (+61)', country: 'AUSTRALIA' },
    { iso: 'SG', dial: '65', label: 'Singapore (+65)', country: 'SINGAPORE' },
    { iso: 'NZ', dial: '64', label: 'New Zealand (+64)', country: 'NEW ZEALAND' },
    { iso: 'DE', dial: '49', label: 'Germany (+49)', country: 'GERMANY' },
    { iso: 'FR', dial: '33', label: 'France (+33)', country: 'FRANCE' },
    { iso: 'NL', dial: '31', label: 'Netherlands (+31)', country: 'NETHERLANDS' },
    { iso: 'IE', dial: '353', label: 'Ireland (+353)', country: 'IRELAND' },
    { iso: 'ZA', dial: '27', label: 'South Africa (+27)', country: 'SOUTH AFRICA' },
    { iso: 'KE', dial: '254', label: 'Kenya (+254)', country: 'KENYA' },
    { iso: 'NG', dial: '234', label: 'Nigeria (+234)', country: 'NIGERIA' },
    { iso: 'PK', dial: '92', label: 'Pakistan (+92)', country: 'PAKISTAN' },
    { iso: 'BD', dial: '880', label: 'Bangladesh (+880)', country: 'BANGLADESH' },
    { iso: 'NP', dial: '977', label: 'Nepal (+977)', country: 'NEPAL' },
    { iso: 'LK', dial: '94', label: 'Sri Lanka (+94)', country: 'SRI LANKA' },
    { iso: 'MY', dial: '60', label: 'Malaysia (+60)', country: 'MALAYSIA' },
    { iso: 'TH', dial: '66', label: 'Thailand (+66)', country: 'THAILAND' },
    { iso: 'QA', dial: '974', label: 'Qatar (+974)', country: 'QATAR' },
    { iso: 'SA', dial: '966', label: 'Saudi Arabia (+966)', country: 'SAUDI ARABIA' },
    { iso: 'KW', dial: '965', label: 'Kuwait (+965)', country: 'KUWAIT' },
    { iso: 'OM', dial: '968', label: 'Oman (+968)', country: 'OMAN' },
    { iso: 'BH', dial: '973', label: 'Bahrain (+973)', country: 'BAHRAIN' },
    { iso: 'HK', dial: '852', label: 'Hong Kong (+852)', country: 'HONG KONG' },
    { iso: 'JP', dial: '81', label: 'Japan (+81)', country: 'JAPAN' },
    { iso: 'CN', dial: '86', label: 'China (+86)', country: 'CHINA' },
];

export function findDialOption(isoOrDial) {
    const value = String(isoOrDial || '').trim();

    return phoneDialCodes.find((row) => row.iso === value || row.dial === value)
        || phoneDialCodes[0];
}

/**
 * Split a stored phone into dial code + national number for form editing.
 */
export function splitPhoneNumber(phone, fallbackIso = 'IN') {
    const digits = String(phone || '').replace(/\D/g, '');
    const fallback = findDialOption(fallbackIso);

    if (digits === '') {
        return { dial: fallback.dial, iso: fallback.iso, national: '' };
    }

    const sorted = [...phoneDialCodes].sort((a, b) => b.dial.length - a.dial.length);

    for (const row of sorted) {
        if (digits.startsWith(row.dial) && digits.length > row.dial.length + 5) {
            return {
                dial: row.dial,
                iso: row.iso,
                national: digits.slice(row.dial.length),
            };
        }
    }

    if (digits.length === 10 && fallback.dial === '91') {
        return { dial: '91', iso: 'IN', national: digits };
    }

    return { dial: fallback.dial, iso: fallback.iso, national: digits };
}
