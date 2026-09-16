(function (window, document) {
    const STORAGE_KEY = 'sadbhavna_donation_attribution_v1';
    const STORAGE_TTL_MS = 30 * 24 * 60 * 60 * 1000;
    const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'platform',
        'placement',
        // Meta fills utm_id/aid dynamically; sid is the marketer's referral code.
        'utm_id',
        'sid',
        'aid',
        'pid',
    ];

    const STORAGE_KEYS = UTM_KEYS.concat(['landing_path']);

    const hasAnyUtm = function (payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        return STORAGE_KEYS.some(function (key) {
            return typeof payload[key] === 'string' && payload[key].trim() !== '';
        });
    };

    const trackingFieldsOf = function (payload) {
        const fields = {};

        if (!payload || typeof payload !== 'object') {
            return fields;
        }

        STORAGE_KEYS.forEach(function (key) {
            if (typeof payload[key] === 'string' && payload[key].trim() !== '') {
                fields[key] = payload[key].trim();
            }
        });

        return fields;
    };

    const readJsonStore = function (storage) {
        try {
            const raw = storage.getItem(STORAGE_KEY);

            if (!raw) {
                return null;
            }

            const stored = JSON.parse(raw);

            if (!hasAnyUtm(stored)) {
                return null;
            }

            const storedAt = Number(stored._ts);

            if (Number.isFinite(storedAt) && (Date.now() - storedAt) > STORAGE_TTL_MS) {
                storage.removeItem(STORAGE_KEY);

                return null;
            }

            return trackingFieldsOf(stored);
        } catch (error) {
            return null;
        }
    };

    const readStored = function () {
        // Tab storage first, then 30-day origin storage. Both belong to the page
        // origin (sadbhavnadham.org on Next.js, donate.* on Laravel pages).
        // They are never sent to another subdomain automatically.
        return readJsonStore(window.sessionStorage) || readJsonStore(window.localStorage);
    };

    const writeStored = function (payload) {
        const fields = trackingFieldsOf(payload);

        if (!hasAnyUtm(fields)) {
            return;
        }

        const record = Object.assign({ _ts: Date.now() }, fields);

        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(record));
        } catch (error) {
            // Ignore quota / private mode failures.
        }

        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(record));
        } catch (error) {
            // Ignore quota / private mode failures.
        }
    };

    const staffCodeFromAttribution = function (payload) {
        if (!payload) {
            return '';
        }

        if (payload.utm_source === 'staff' && payload.utm_content) {
            return String(payload.utm_content).trim().toLowerCase();
        }

        return '';
    };

    const isNumericId = function (value) {
        return /^[0-9]+$/.test(String(value || '').trim());
    };

    const partnerCodeOf = function (payload) {
        if (!payload) {
            return '';
        }

        if (payload.sid && !isNumericId(payload.sid)) {
            return String(payload.sid).trim().toLowerCase();
        }

        if (payload.pid) {
            return String(payload.pid).trim().toLowerCase();
        }

        return staffCodeFromAttribution(payload);
    };

    const captureFromLocation = function () {
        const stored = readStored();
        const fromUrl = readFromLocation();

        // Marketing values are first-touch for the tab. Empty keys may still be
        // filled from a later URL. Partner credit is a separate sticky slot.
        if (stored) {
            if (fromUrl) {
                Object.keys(fromUrl).forEach(function (key) {
                    if (key === 'sid' || key === 'pid') {
                        return;
                    }

                    if (!stored[key] && fromUrl[key]) {
                        stored[key] = fromUrl[key];
                    }
                });
            }

            if (stored.sid && isNumericId(stored.sid) && !stored.utm_term) {
                stored.utm_term = stored.sid;
            }

            const partner = partnerCodeOf(stored) || partnerCodeOf(fromUrl);

            if (partner && stored.sid !== partner) {
                stored.sid = partner;
            }

            delete stored.pid;

            if (!stored.landing_path) {
                stored.landing_path = (window.location.pathname + window.location.search).slice(0, 255);
            }

            writeStored(stored);

            return stored;
        }

        if (!fromUrl) {
            return null;
        }

        if (fromUrl.sid && isNumericId(fromUrl.sid) && !fromUrl.utm_term) {
            fromUrl.utm_term = fromUrl.sid;
        }

        const partner = partnerCodeOf(fromUrl);

        if (partner) {
            fromUrl.sid = partner;
        }

        delete fromUrl.pid;

        if (!fromUrl.landing_path) {
            fromUrl.landing_path = (window.location.pathname + window.location.search).slice(0, 255);
        }

        writeStored(fromUrl);

        return fromUrl;
    };

    const readFromLocation = function () {
        const params = new URLSearchParams(window.location.search);
        const payload = {};

        UTM_KEYS.forEach(function (key) {
            const value = (params.get(key) || '').trim();
            if (value) {
                payload[key] = value.slice(0, 120);
            }
        });

        if (!payload.utm_source) {
            if (params.get('fbclid')) {
                payload.utm_source = 'meta';
                payload.utm_medium = payload.utm_medium || 'paid_social';
                payload.platform = payload.platform || 'fb';
            } else if (params.get('igshid') || /instagram\.com/i.test(document.referrer || '')) {
                payload.utm_source = 'meta';
                payload.utm_medium = payload.utm_medium || 'paid_social';
                payload.platform = payload.platform || 'ig';
            } else if (params.get('gclid') || params.get('wbraid') || params.get('gbraid')) {
                payload.utm_source = 'google';
                payload.utm_medium = payload.utm_medium || 'cpc';
            } else if (/facebook\.com|fb\.com/i.test(document.referrer || '')) {
                payload.utm_source = 'meta';
                payload.utm_medium = payload.utm_medium || 'referral';
                payload.platform = payload.platform || 'fb';
            } else if (/google\./i.test(document.referrer || '')) {
                payload.utm_source = 'google';
                payload.utm_medium = payload.utm_medium || 'organic';
            }
        }

        return hasAnyUtm(payload) ? payload : null;
    };

    const appendToFormData = function (formData) {
        const attribution = captureFromLocation();

        if (!attribution) {
            return;
        }

        UTM_KEYS.forEach(function (key) {
            if (!formData.get(key) && attribution[key]) {
                formData.set(key, attribution[key]);
            }
        });

        if (!formData.get('landing_path') && attribution.landing_path) {
            formData.set('landing_path', attribution.landing_path);
        }
    };

    const isTrackablePath = function (pathname) {
        return pathname === '/'
            || pathname === ''
            || pathname.indexOf('/donate/') === 0
            || pathname.indexOf('/give/') === 0;
    };

    const withAttribution = function (href, attribution) {
        if (!href || !hasAnyUtm(attribution)) {
            return href;
        }

        try {
            const url = new URL(href, window.location.origin);

            if (url.origin !== window.location.origin || !isTrackablePath(url.pathname)) {
                return href;
            }

            const staffCode = staffCodeFromAttribution(attribution);

            if (staffCode) {
                url.searchParams.set('utm_source', 'staff');
                url.searchParams.set('utm_medium', 'referral');
                url.searchParams.set('utm_content', staffCode);

                if (attribution.sid && !isNumericId(attribution.sid)) {
                    url.searchParams.set('sid', attribution.sid);
                }
            } else {
                UTM_KEYS.forEach(function (key) {
                    if (attribution[key] && !url.searchParams.get(key)) {
                        url.searchParams.set(key, attribution[key]);
                    }
                });
            }

            return url.pathname + url.search + url.hash;
        } catch (error) {
            return href;
        }
    };

    const rewriteInternalLinks = function (attribution) {
        if (!hasAnyUtm(attribution)) {
            return;
        }

        document.querySelectorAll('a[href]').forEach(function (anchor) {
            const nextHref = withAttribution(anchor.getAttribute('href'), attribution);

            if (nextHref && nextHref !== anchor.getAttribute('href')) {
                anchor.setAttribute('href', nextHref);
            }
        });
    };

    const attribution = captureFromLocation();

    const boot = function () {
        rewriteInternalLinks(attribution || readStored());
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.SadbhavnaAttribution = {
        storageKey: STORAGE_KEY,
        utmKeys: UTM_KEYS,
        capture: captureFromLocation,
        appendToFormData: appendToFormData,
        withAttribution: withAttribution,
        get: function () {
            return readStored() || captureFromLocation();
        },
        checkoutFields: function () {
            return trackingFieldsOf(this.get());
        },
    };
})(window, document);
