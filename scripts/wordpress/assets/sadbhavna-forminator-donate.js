/**
 * Sadbhavna Forminator → Laravel Razorpay checkout
 *
 * Requires:
 * - Razorpay checkout.js loaded on the page
 * - MU plugin sadbhavna-donate-api.php (exposes window.SadbhavnaDonate)
 * - window.SadbhavnaDonateConfig on each page (formId, cause, fields)
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function config() {
        return window.SadbhavnaDonateConfig || null;
    }

    function bridge() {
        return window.SadbhavnaDonate || null;
    }

    function findForm(formId) {
        if (formId) {
            var byId = document.getElementById('forminator-module-' + formId);
            if (byId) {
                return byId;
            }

            var byData = document.querySelector('form[data-form-id="' + formId + '"]');
            if (byData) {
                return byData;
            }
        }

        return document.querySelector('form.forminator-custom-form');
    }

    function fieldValue(form, fieldId) {
        if (!fieldId) {
            return '';
        }

        var radioChecked = form.querySelector('input[type="radio"][name="' + fieldId + '"]:checked');
        if (radioChecked) {
            return (radioChecked.value || '').trim();
        }

        var selectors = [
            '[name="' + fieldId + '"]',
            '[id="' + fieldId + '"]',
            '[name="' + fieldId + '[]"]',
            'input[name="' + fieldId + '"]',
            'select[name="' + fieldId + '"]',
            'textarea[name="' + fieldId + '"]',
            'input[id^="forminator-field-' + fieldId + '"]',
            'input[id*="-' + fieldId + '_"]',
            'input[id*="-' + fieldId + '-"]',
            'select[id^="forminator-field-' + fieldId + '"]',
            'select[id*="-' + fieldId + '_"]',
        ];

        for (var s = 0; s < selectors.length; s++) {
            var el = form.querySelector(selectors[s]);
            if (!el) {
                continue;
            }

            if (el.type === 'checkbox') {
                return el.checked ? '1' : '';
            }

            if (el.type === 'radio') {
                var checked = form.querySelector('input[name="' + el.name + '"]:checked');
                return checked ? (checked.value || '').trim() : '';
            }

            return (el.value || '').trim();
        }

        // Last resort: prefix match, but prefer exact-looking controls.
        var prefixed = form.querySelectorAll(
            'input[name^="' + fieldId + '"], select[name^="' + fieldId + '"], textarea[name^="' + fieldId + '"]'
        );
        for (var i = 0; i < prefixed.length; i++) {
            var node = prefixed[i];
            if (node.type === 'hidden' && prefixed.length > 1) {
                continue;
            }
            if (node.type === 'radio') {
                continue;
            }
            if (node.type === 'checkbox') {
                return node.checked ? '1' : '';
            }
            var val = (node.value || '').trim();
            if (val) {
                return val;
            }
        }

        return '';
    }

    function addressPart(form, addressId, part) {
        if (!addressId) {
            return '';
        }

        var candidates = [
            addressId + '-' + part,
            addressId + '[' + part + ']',
            addressId + '-' + part.replace(/_/g, '-'),
        ];

        // Forminator DOM ids look like: forminator-field-street_address-address-1_xxxxx
        var byIdPrefix = form.querySelector(
            'input[id^="forminator-field-' + part + '-' + addressId + '"], select[id^="forminator-field-' + part + '-' + addressId + '"]'
        );
        if (byIdPrefix && (byIdPrefix.value || '').trim()) {
            return (byIdPrefix.value || '').trim();
        }

        for (var i = 0; i < candidates.length; i++) {
            var value = fieldValue(form, candidates[i]);
            if (value) {
                return value;
            }
        }

        return '';
    }

    function parseMoney(value) {
        if (value === null || value === undefined) {
            return null;
        }

        var normalized = String(value).replace(/,/g, '').replace(/[^\d.]/g, '');
        if (!normalized) {
            return null;
        }

        var number = parseFloat(normalized);
        if (isNaN(number) || number <= 0) {
            return null;
        }

        return number;
    }

    function resolveAddress(form, fields) {
        var addressId = fields.address || 'address-1';
        var street =
            fieldValue(form, fields.address_street) ||
            addressPart(form, addressId, 'street_address') ||
            addressPart(form, addressId, 'address') ||
            fieldValue(form, addressId);
        var apartment =
            fieldValue(form, fields.address_line) ||
            addressPart(form, addressId, 'address_line') ||
            addressPart(form, addressId, 'address_line_2');

        var parts = [street, apartment].filter(function (part) {
            return !!part;
        });

        var country =
            fieldValue(form, fields.country) ||
            addressPart(form, addressId, 'country') ||
            'INDIA';

        if (/^india$/i.test(country)) {
            country = 'INDIA';
        }

        return {
            address: parts.join(', '),
            city:
                fieldValue(form, fields.city) ||
                addressPart(form, addressId, 'city'),
            state:
                fieldValue(form, fields.state) ||
                addressPart(form, addressId, 'state'),
            pincode:
                fieldValue(form, fields.pincode) ||
                addressPart(form, addressId, 'zip') ||
                addressPart(form, addressId, 'zip_code'),
            country: country,
        };
    }

    function resolveAmount(form, fields) {
        var selected = fieldValue(form, fields.amount || fields.amount_radio);
        var other = fieldValue(form, fields.amount_other || fields.currency);
        var calculated = fieldValue(form, fields.calculation || fields.amount_calculation);

        // Prefer explicit radio values (500 / 1000).
        if (selected && !/^other$/i.test(selected)) {
            var selectedNumber = parseMoney(selected);
            if (selectedNumber !== null) {
                return selectedNumber;
            }
        }

        // "Other" → currency field.
        if (!selected || /^other$/i.test(selected)) {
            var otherNumber = parseMoney(other);
            if (otherNumber !== null) {
                return otherNumber;
            }
        }

        // Calculation field last (can be 0/stale while Other is selected).
        var calculatedNumber = parseMoney(calculated);
        if (calculatedNumber !== null) {
            return calculatedNumber;
        }

        return null;
    }

    function formatErrors(data) {
        if (!data) {
            return 'Donation could not be started.';
        }

        if (data.errors && typeof data.errors === 'object') {
            var messages = [];
            Object.keys(data.errors).forEach(function (key) {
                var list = data.errors[key];
                if (Array.isArray(list)) {
                    list.forEach(function (item) {
                        if (item) {
                            messages.push(String(item));
                        }
                    });
                } else if (list) {
                    messages.push(String(list));
                }
            });
            if (messages.length) {
                return messages.join('\n');
            }
        }

        return data.message || 'Donation could not be started.';
    }

    var ATTRIBUTION_STORAGE_KEY = 'sadbhavna_donation_attribution_v1';
    var ATTRIBUTION_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'sid',
        'aid',
        'pid',
        'platform',
        'placement',
    ];

    function readStoredAttribution() {
        try {
            var stored = JSON.parse(window.sessionStorage.getItem(ATTRIBUTION_STORAGE_KEY) || 'null');

            return stored && typeof stored === 'object' ? stored : null;
        } catch (error) {
            return null;
        }
    }

    function writeStoredAttribution(payload) {
        try {
            window.sessionStorage.setItem(ATTRIBUTION_STORAGE_KEY, JSON.stringify(payload));
        } catch (error) {
            // Ignore quota / private mode failures.
        }
    }

    function captureAttribution() {
        var params = new URLSearchParams(window.location.search);
        var fromUrl = {};

        ATTRIBUTION_KEYS.forEach(function (key) {
            var value = (params.get(key) || '').trim();
            if (value) {
                fromUrl[key] = value.slice(0, 120);
            }
        });

        if (!fromUrl.utm_source) {
            if (params.get('fbclid')) {
                fromUrl.utm_source = 'meta';
                fromUrl.utm_medium = fromUrl.utm_medium || 'paid_social';
                fromUrl.platform = fromUrl.platform || 'fb';
            } else if (params.get('igshid') || /instagram\.com/i.test(document.referrer || '')) {
                fromUrl.utm_source = 'meta';
                fromUrl.utm_medium = fromUrl.utm_medium || 'paid_social';
                fromUrl.platform = fromUrl.platform || 'ig';
            } else if (params.get('gclid') || params.get('wbraid') || params.get('gbraid')) {
                fromUrl.utm_source = 'google';
                fromUrl.utm_medium = fromUrl.utm_medium || 'cpc';
            } else if (/facebook\.com|fb\.com/i.test(document.referrer || '')) {
                fromUrl.utm_source = 'meta';
                fromUrl.utm_medium = fromUrl.utm_medium || 'referral';
                fromUrl.platform = fromUrl.platform || 'fb';
            }
        }

        var stored = readStoredAttribution() || {};
        var merged = Object.assign({}, stored);

        Object.keys(fromUrl).forEach(function (key) {
            if (key === 'sid' || key === 'pid') {
                return;
            }

            if (!merged[key] && fromUrl[key]) {
                merged[key] = fromUrl[key];
            }
        });

        if (merged.sid && /^[0-9]+$/.test(String(merged.sid)) && !merged.utm_term) {
            merged.utm_term = merged.sid;
        }

        var partner = '';
        if (merged.sid && !/^[0-9]+$/.test(String(merged.sid))) {
            partner = String(merged.sid).trim().toLowerCase();
        } else if (fromUrl.sid && !/^[0-9]+$/.test(String(fromUrl.sid))) {
            partner = String(fromUrl.sid).trim().toLowerCase();
        } else if ((stored.pid || fromUrl.pid || '').trim()) {
            partner = String(stored.pid || fromUrl.pid).trim().toLowerCase();
        }

        if (!partner && merged.utm_source === 'staff' && merged.utm_content) {
            partner = String(merged.utm_content).trim().toLowerCase();
        }

        if (partner) {
            merged.sid = partner;
        }

        delete merged.pid;

        writeStoredAttribution(merged);

        return merged;
    }

    function buildPayload(form, cfg) {
        var fields = cfg.fields || {};
        var causeFromField = fieldValue(form, fields.cause);
        var address = resolveAddress(form, fields);
        var amount = resolveAmount(form, fields);
        var attribution = captureAttribution();

        var payload = {
            cause: causeFromField || cfg.cause || '',
            amount: amount,
            package_id: fieldValue(form, fields.package_id) || null,
            donor_name: fieldValue(form, fields.donor_name),
            donor_email: fieldValue(form, fields.donor_email),
            donor_phone: fieldValue(form, fields.donor_phone),
            address: address.address,
            pincode: address.pincode,
            city: address.city,
            state: address.state,
            country: address.country || 'INDIA',
            donor_country: fieldValue(form, fields.donor_country) || 'IN',
            consent_indian_citizen: fieldValue(form, fields.consent_indian_citizen) ? true : false,
            pan_number: fieldValue(form, fields.pan_number) || null,
            date_of_birth: fieldValue(form, fields.date_of_birth) || null,
            title: fieldValue(form, fields.title) || null,
            campaign_slug: cfg.campaignSlug || fieldValue(form, fields.campaign_slug) || null,
            source_channel: 'wordpress',
            utm_source: attribution.utm_source || cfg.utmSource || 'wordpress',
            utm_medium: attribution.utm_medium || cfg.utmMedium || 'website',
            utm_campaign: attribution.utm_campaign || cfg.utmCampaign || null,
            utm_content: attribution.utm_content || cfg.utmContent || null,
            utm_term: attribution.utm_term || null,
            utm_id: attribution.utm_id || null,
            sid: attribution.sid || null,
            aid: attribution.aid || null,
            platform: attribution.platform || null,
            placement: attribution.placement || null,
            landing_path: window.location.pathname + window.location.search,
        };

        return payload;
    }

    function setBusy(form, busy) {
        var button =
            form.querySelector('.forminator-button-submit') ||
            form.querySelector('button[type="submit"]');

        if (!button) {
            return;
        }

        button.disabled = !!busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
        if (busy) {
            button.dataset.sadbhavnaOriginalText = button.dataset.sadbhavnaOriginalText || button.textContent;
            button.textContent = 'Processing…';
        } else if (button.dataset.sadbhavnaOriginalText) {
            button.textContent = button.dataset.sadbhavnaOriginalText;
        }
    }

    function showError(message) {
        window.alert(message || 'Unable to start payment. Please try again.');
    }

    function openRazorpay(order, brandName) {
        if (typeof window.Razorpay !== 'function') {
            showError('Razorpay checkout failed to load. Please refresh the page.');
            return;
        }

        var options = {
            key: order.key,
            amount: order.amount,
            currency: 'INR',
            name: brandName || 'Sadbhavna',
            description: order.description || 'Donation',
            order_id: order.order_id,
            prefill: {
                name: order.name || '',
                email: order.email || '',
                contact: order.contact || '',
            },
            handler: function () {
                window.location.href = order.thank_you_url || '/';
            },
            modal: {
                ondismiss: function () {
                    // Donor closed checkout; form can be submitted again.
                },
            },
            theme: {
                color: '#2f855a',
            },
        };

        var rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function () {
            showError('Payment failed. Please try again.');
        });
        rzp.open();
    }

    function isValidPan(value) {
        return /^[A-Z]{5}[0-9]{4}[A-Z]$/.test(String(value || '').trim().toUpperCase());
    }

    function panThreshold(api) {
        var value = api && api.panThreshold ? Number(api.panThreshold) : 100000;
        return isNaN(value) || value < 0 ? 100000 : value;
    }

    function checkPanRequirement(payload, api) {
        var body = new FormData();
        body.append('action', 'sadbhavna_pan_requirement');
        body.append('nonce', api.nonce);
        body.append(
            'payload',
            JSON.stringify({
                cause: payload.cause,
                amount: payload.amount,
                quantity: payload.quantity || 1,
                package_id: payload.package_id || null,
                donor_email: payload.donor_email,
                donor_phone: payload.donor_phone,
            })
        );

        return fetch(api.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        });
    }

    function ensurePanBeforeCheckout(payload, api) {
        var pan = String(payload.pan_number || '').trim().toUpperCase();
        payload.pan_number = pan || null;

        if (pan && !isValidPan(pan)) {
            return Promise.resolve({
                ok: false,
                message: 'Please enter a valid PAN number (e.g. ABCDE1234F).',
            });
        }

        var amount = Number(payload.amount || 0);
        if (pan && amount > 0 && amount < panThreshold(api)) {
            // Still ask Laravel — FY total may push them over the threshold,
            // but format is already valid so we can proceed after server says optional/required.
        }

        return checkPanRequirement(payload, api)
            .then(function (result) {
                if (!result.ok || !result.data) {
                    // Fallback: local threshold only if API fails.
                    if (amount >= panThreshold(api) && !pan) {
                        return {
                            ok: false,
                            message:
                                'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
                        };
                    }

                    return { ok: true, payload: payload };
                }

                if (result.data.required && !pan) {
                    if (result.data.known_pan_number) {
                        payload.pan_number = result.data.known_pan_number;
                        return { ok: true, payload: payload };
                    }

                    return {
                        ok: false,
                        message:
                            'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
                    };
                }

                if (pan && !isValidPan(pan)) {
                    return {
                        ok: false,
                        message: 'Please enter a valid PAN number (e.g. ABCDE1234F).',
                    };
                }

                return { ok: true, payload: payload };
            })
            .catch(function () {
                if (amount >= panThreshold(api) && !pan) {
                    return {
                        ok: false,
                        message:
                            'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
                    };
                }

                return { ok: true, payload: payload };
            });
    }

    function createDonation(payload, api, onDone) {
        var body = new FormData();
        body.append('action', 'sadbhavna_create_donation');
        body.append('nonce', api.nonce);
        body.append('payload', JSON.stringify(payload));

        fetch(api.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                onDone();

                if (!result.ok || !result.data || !result.data.order) {
                    showError(formatErrors(result.data));
                    return;
                }

                openRazorpay(result.data.order, api.brandName);
            })
            .catch(function () {
                onDone();
                showError('Network error. Please try again.');
            });
    }

    function bind() {
        var cfg = config();
        var api = bridge();

        if (!cfg) {
            return;
        }

        if (!api || !api.ajaxUrl || !api.nonce) {
            console.error('SadbhavnaDonate bridge missing. Is the MU plugin active?');
            return;
        }

        var form = findForm(cfg.formId);
        if (!form || form.dataset.sadbhavnaBound === '1') {
            return;
        }

        form.dataset.sadbhavnaBound = '1';

        form.addEventListener(
            'submit',
            function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                if (form.dataset.sadbhavnaSubmitting === '1') {
                    return;
                }

                var payload = buildPayload(form, cfg);

                if (!payload.cause) {
                    showError('Donation cause is missing. Please contact the site admin.');
                    return;
                }

                if (!payload.donor_name || !payload.donor_email || !payload.donor_phone) {
                    showError('Please fill name, email, and phone.');
                    return;
                }

                if (!payload.amount && !payload.package_id) {
                    showError('Please enter a donation amount (500 / 1000 / Other).');
                    return;
                }

                if (!payload.address || !payload.city || !payload.state || !payload.pincode) {
                    showError('Please complete street, city, state, and PIN code.');
                    return;
                }

                if (!payload.consent_indian_citizen) {
                    showError('Please accept the declaration to continue.');
                    return;
                }

                form.dataset.sadbhavnaSubmitting = '1';
                setBusy(form, true);

                ensurePanBeforeCheckout(payload, api).then(function (panResult) {
                    if (!panResult.ok) {
                        form.dataset.sadbhavnaSubmitting = '0';
                        setBusy(form, false);
                        showError(panResult.message);
                        return;
                    }

                    createDonation(panResult.payload || payload, api, function () {
                        form.dataset.sadbhavnaSubmitting = '0';
                        setBusy(form, false);
                    });
                });
            },
            true
        );
    }

    ready(function () {
        bind();
        // Forminator may re-render; retry shortly.
        window.setTimeout(bind, 800);
    });
})();
