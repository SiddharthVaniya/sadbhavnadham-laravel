document.addEventListener('DOMContentLoaded', function () {

    const donationForm = document.getElementById('donationForm');
    if (!donationForm) return;

    const submitButton = donationForm.querySelector('button[type="submit"]');
    let isSubmitting = false;

    const readCookie = function (name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : '';
    };

    const captureFirstTouchAttribution = function () {
        if (window.SadbhavnaAttribution && typeof window.SadbhavnaAttribution.capture === 'function') {
            return window.SadbhavnaAttribution.capture();
        }

        return null;
    };

    const appendAttributionToFormData = function (formData) {
        if (window.SadbhavnaAttribution && typeof window.SadbhavnaAttribution.appendToFormData === 'function') {
            window.SadbhavnaAttribution.appendToFormData(formData);

            return;
        }

        const attribution = captureFirstTouchAttribution();

        if (!attribution) {
            return;
        }

        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id', 'sid', 'aid', 'platform', 'placement'].forEach(function (key) {
            if (!formData.get(key) && attribution[key]) {
                formData.set(key, attribution[key]);
            }
        });
    };

    captureFirstTouchAttribution();

    const resolveCsrfToken = function () {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = donationForm.querySelector('input[name="_token"]');

        return meta?.content
            || input?.value
            || donationForm.dataset.csrf
            || '';
    };

    const syncCsrfFields = function (token) {
        if (!token) {
            return;
        }

        donationForm.dataset.csrf = token;

        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }

        let input = donationForm.querySelector('input[name="_token"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            donationForm.prepend(input);
        }

        input.value = token;
    };

    const isRecurringSelected = function () {
        const donationTypeInput = document.getElementById('donationType');
        return donationTypeInput && donationTypeInput.value === 'recurring';
    };

    const setSubmitLoading = function (loading) {
        if (!submitButton) {
            return;
        }

        if (loading) {
            if (!submitButton.dataset.originalText) {
                submitButton.dataset.originalText = submitButton.innerHTML;
            }

            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.innerHTML = 'Please wait...';

            return;
        }

        submitButton.disabled = false;
        submitButton.removeAttribute('aria-busy');

        if (submitButton.dataset.originalText) {
            submitButton.innerHTML = submitButton.dataset.originalText;
        }
    };

    const resolveActionUrl = function () {
        if (isRecurringSelected()) {
            return donationForm.dataset.subscriptionAction || '';
        }

        return donationForm.dataset.action || '';
    };

    const razorpayName = donationForm.dataset.razorpayName || 'Donation';

    const openOneTimeCheckout = function (data) {
        const rzp = new Razorpay({
            key: data.order.key,
            amount: data.order.amount,
            currency: 'INR',
            name: razorpayName,
            description: data.order.description || 'Donation',
            order_id: data.order.order_id,
            prefill: {
                name: data.order.name,
                email: data.order.email,
                contact: data.order.contact
            },
            handler: function () {
                window.location.href = data.order.thank_you_url || ('/?donation=success');
            },
            modal: {
                ondismiss: function () {
                    isSubmitting = false;
                    setSubmitLoading(false);
                }
            },
            theme: {
                color: '#2f855a'
            }
        });

        bindRazorpayFailureHandler(rzp);
        rzp.open();
    };

    const openSubscriptionCheckout = function (data) {
        const rzp = new Razorpay({
            key: data.subscription.key,
            name: razorpayName,
            description: data.subscription.description || 'Monthly Donation',
            subscription_id: data.subscription.subscription_id,
            prefill: {
                name: data.subscription.name,
                email: data.subscription.email,
                contact: data.subscription.contact
            },
            handler: function () {
                window.location.href = data.subscription.thank_you_url || ('/?donation=recurring-success');
            },
            modal: {
                ondismiss: function () {
                    isSubmitting = false;
                    setSubmitLoading(false);
                }
            },
            theme: {
                color: '#2f855a'
            }
        });

        bindRazorpayFailureHandler(rzp);
        rzp.open();
    };

    const bindRazorpayFailureHandler = function (rzp) {
        rzp.on('payment.failed', function () {
            isSubmitting = false;
            setSubmitLoading(false);
        });
    };

    donationForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const recurring = isRecurringSelected();
        const actionUrl = resolveActionUrl();

        if (!actionUrl) {
            alert(recurring ? 'Monthly donations are not available right now.' : 'Configuration error');
            return;
        }

        if (recurring) {
            const packageInput = document.getElementById('donationPackageId');
            const consentRecurring = document.getElementById('consentRecurring');
            const frequencyInput = document.getElementById('donationFrequency');
            const allowCustom = donationForm.dataset.allowCustom === '1';
            const lockAmount = donationForm.dataset.lockAmount === '1';
            const amountInput = document.getElementById('donationAmount');
            const hasPackage = packageInput && packageInput.value;
            const hasCustomAmount = (allowCustom || lockAmount) && amountInput && Number(amountInput.value) > 0;
            const frequency = frequencyInput?.value || donationForm.dataset.frequency || 'monthly';

            if (!hasPackage && !hasCustomAmount) {
                alert(frequency === 'weekly'
                    ? 'Please select a donation option or enter a custom weekly amount.'
                    : 'Please select a donation option or enter a custom monthly amount.');
                return;
            }

            if (!consentRecurring || !consentRecurring.checked) {
                alert(frequency === 'weekly'
                    ? 'Please authorize the weekly donation mandate to continue.'
                    : 'Please authorize the monthly donation mandate to continue.');
                return;
            }
        }

        isSubmitting = true;
        setSubmitLoading(true);

        const amountInput = document.getElementById('donationAmount');
        const unitAmountInput = document.getElementById('unitAmount');
        const quantityInput = document.getElementById('quantityInput');
        const frequencyInput = document.getElementById('donationFrequency');

        if (!unitAmountInput.value) {
            unitAmountInput.value = amountInput.value;
            quantityInput.value = '1';
        }

        if (frequencyInput) {
            frequencyInput.disabled = !recurring;
        }

        const csrfToken = resolveCsrfToken();
        const xsrfToken = readCookie('XSRF-TOKEN');
        syncCsrfFields(csrfToken);

        const formData = new FormData(donationForm);
        const allowCustom = donationForm.dataset.allowCustom === '1';
        const lockAmount = donationForm.dataset.lockAmount === '1';
        const packageInput = document.getElementById('donationPackageId');

        if (csrfToken) {
            formData.set('_token', csrfToken);
        }

        appendAttributionToFormData(formData);

        if (recurring) {
            if (packageInput?.value) {
                formData.delete('amount');
            } else if (!allowCustom && !lockAmount) {
                formData.delete('amount');
            } else {
                formData.delete('package_id');
            }
        } else {
            formData.delete('consent_recurring');
            formData.delete('frequency');
        }

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken;
        }

        fetch(actionUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: formData
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            }).catch(function () {
                return { ok: res.ok, status: res.status, data: null };
            });
        })
        .then(function (result) {
            const data = result.data;

            if (!result.ok) {
                if (result.status === 419) {
                    alert('Your session expired. Please refresh the page and try again.');
                    isSubmitting = false;
                    setSubmitLoading(false);
                    return;
                }

                const message = data?.message
                    || Object.values(data?.errors || {})[0]?.[0]
                    || 'Unable to initiate payment';
                alert(message);
                isSubmitting = false;
                setSubmitLoading(false);
                return;
            }

            if (!data || data.provider !== 'razorpay') {
                alert('Unable to initiate payment');
                isSubmitting = false;
                setSubmitLoading(false);
                return;
            }

            try {
                if (data.mode === 'subscription' && data.subscription) {
                    openSubscriptionCheckout(data);
                    return;
                }

                if (data.order) {
                    openOneTimeCheckout(data);
                    return;
                }

                alert('Unable to initiate payment');
                isSubmitting = false;
                setSubmitLoading(false);
            } catch (error) {
                console.error(error);
                isSubmitting = false;
                setSubmitLoading(false);
                alert('Payment failed to start');
            }
        })
        .catch(function (err) {
            console.error(err);
            isSubmitting = false;
            setSubmitLoading(false);
            alert('Payment failed to start');
        });
    });

});
