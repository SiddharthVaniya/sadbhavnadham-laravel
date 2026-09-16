(() => {
    const modal = document.querySelector('[data-donor-login-modal]');
    if (!modal) {
        return;
    }

    const sendUrl = modal.dataset.otpSendUrl || '';
    const verifyUrl = modal.dataset.otpVerifyUrl || '';
    const logoutUrl = modal.dataset.otpLogoutUrl || '';
    const phoneInput = modal.querySelector('[data-donor-login-phone]');
    const emailInput = modal.querySelector('[data-donor-login-email]');
    const phoneFields = modal.querySelector('[data-donor-login-phone-fields]');
    const emailFields = modal.querySelector('[data-donor-login-email-fields]');
    const switchBtn = modal.querySelector('[data-donor-login-switch]');
    const switchLabel = modal.querySelector('[data-donor-login-switch-label]');
    const leadEl = modal.querySelector('[data-donor-login-lead]');
    const otpInput = modal.querySelector('[data-donor-login-otp]');
    const sendBtn = modal.querySelector('[data-donor-login-send]');
    const verifyBtn = modal.querySelector('[data-donor-login-verify]');
    const resendBtn = modal.querySelector('[data-donor-login-resend]');
    const changePhoneBtn = modal.querySelector('[data-donor-login-change-phone]');
    const statusEl = modal.querySelector('[data-donor-login-status]');
    const sentEl = modal.querySelector('[data-donor-login-sent]');
    const stepPhone = modal.querySelector('[data-donor-login-step="phone"]');
    const stepOtp = modal.querySelector('[data-donor-login-step="otp"]');
    const formBanner = document.querySelector('[data-donor-form-banner]');
    const guestHint = document.querySelector('[data-donor-guest-hint]');
    const ccRoot = modal.querySelector('[data-donor-cc]');
    const ccToggle = modal.querySelector('[data-donor-cc-toggle]');
    const ccMenu = modal.querySelector('[data-donor-cc-menu]');
    const ccList = modal.querySelector('[data-donor-cc-list]');
    const ccSearch = modal.querySelector('[data-donor-cc-search]');
    const ccFlag = modal.querySelector('[data-donor-cc-flag]');
    const ccDial = modal.querySelector('[data-donor-cc-dial]');

    let dialCodes = [];
    try {
        dialCodes = JSON.parse(modal.dataset.dialCodes || '[]');
    } catch (error) {
        dialCodes = [{ iso: 'IN', dial: '91', label: 'India (+91)', country: 'INDIA' }];
    }

    let selectedCountry = dialCodes.find((row) => row.iso === 'IN') || dialCodes[0] || {
        iso: 'IN',
        dial: '91',
        label: 'India (+91)',
        country: 'INDIA',
    };

    let loginMethod = 'phone';
    let activePhone = '';
    let activeEmail = '';
    let activeDial = selectedCountry.dial;
    let activeIso = selectedCountry.iso;
    let cooldownTimer = null;
    let cooldownEndsAt = 0;
    let lastFocused = null;

    const readCookie = (name) => {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : '';
    };

    const resolveCsrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    };

    const postJson = async (url, payload = null) => {
        const csrfToken = resolveCsrfToken();
        const xsrfToken = readCookie('XSRF-TOKEN');
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrfToken);
        }

        const options = {
            method: payload === null ? 'GET' : 'POST',
            credentials: 'same-origin',
            headers,
        };

        if (payload !== null) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));

        return { response, data };
    };

    const setStatus = (message, type = '') => {
        if (!statusEl) {
            return;
        }

        statusEl.textContent = message || '';
        statusEl.classList.remove('is-ok', 'is-error');

        if (type === 'ok') {
            statusEl.classList.add('is-ok');
        }

        if (type === 'error') {
            statusEl.classList.add('is-error');
        }
    };

    const showStep = (step) => {
        if (stepPhone) {
            stepPhone.hidden = step !== 'phone';
        }

        if (stepOtp) {
            stepOtp.hidden = step !== 'otp';
        }
    };

    const maxPhoneLength = () => (selectedCountry.dial === '91' ? 10 : 15);

    const applyCountry = (country) => {
        selectedCountry = country;
        activeDial = country.dial;
        activeIso = country.iso;

        if (ccFlag) {
            ccFlag.src = `https://flagcdn.com/w40/${String(country.iso).toLowerCase()}.png`;
            ccFlag.alt = country.iso;
        }

        if (ccDial) {
            ccDial.textContent = `+${country.dial}`;
        }

        if (phoneInput) {
            phoneInput.maxLength = maxPhoneLength();
            phoneInput.placeholder = country.dial === '91' ? '10-digit mobile' : 'Mobile number';
            phoneInput.value = String(phoneInput.value || '').replace(/\D/g, '').slice(0, maxPhoneLength());
        }
    };

    const closeCountryMenu = () => {
        if (!ccMenu || !ccToggle) {
            return;
        }

        ccMenu.hidden = true;
        ccToggle.setAttribute('aria-expanded', 'false');
        ccRoot?.classList.remove('is-open');
    };

    const openCountryMenu = () => {
        if (!ccMenu || !ccToggle) {
            return;
        }

        ccMenu.hidden = false;
        ccToggle.setAttribute('aria-expanded', 'true');
        ccRoot?.classList.add('is-open');
        if (ccSearch) {
            ccSearch.value = '';
            renderCountryList('');
            ccSearch.focus();
        }
    };

    const renderCountryList = (query = '') => {
        if (!ccList) {
            return;
        }

        const needle = String(query || '').trim().toLowerCase();
        const rows = dialCodes.filter((row) => {
            if (!needle) {
                return true;
            }

            return `${row.label} ${row.country} ${row.iso} +${row.dial}`
                .toLowerCase()
                .includes(needle);
        });

        ccList.innerHTML = '';

        rows.forEach((row) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'donor-login-cc__option';
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', row.iso === selectedCountry.iso ? 'true' : 'false');
            item.innerHTML = `
                <img src="https://flagcdn.com/w40/${String(row.iso).toLowerCase()}.png" alt="" width="20" height="15">
                <span class="donor-login-cc__name">${row.country}</span>
                <span class="donor-login-cc__code">+${row.dial}</span>
            `;
            item.addEventListener('click', () => {
                applyCountry(row);
                closeCountryMenu();
                phoneInput?.focus();
            });
            ccList.appendChild(item);
        });

        if (rows.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'donor-login-cc__empty';
            empty.textContent = 'No countries found';
            ccList.appendChild(empty);
        }
    };

    const setAuthUi = (state) => {
        const signedIn = Boolean(state?.signed_in);
        const name = state?.display_name || 'Donor';

        document.querySelectorAll('[data-donor-signin]').forEach((el) => {
            el.hidden = signedIn;
        });

        document.querySelectorAll('[data-donor-signed]').forEach((el) => {
            el.hidden = !signedIn;
        });

        document.querySelectorAll('[data-donor-hello]').forEach((el) => {
            el.textContent = signedIn ? name : '';
        });

        document.querySelectorAll('[data-donor-initial]').forEach((el) => {
            el.textContent = signedIn ? String(name).charAt(0).toUpperCase() : '';
        });

        if (formBanner) {
            formBanner.hidden = !signedIn;
            const bannerName = formBanner.querySelector('[data-donor-banner-name]');
            if (bannerName && signedIn) {
                bannerName.textContent = `Signed in as ${name}`;
            }
        }

        if (guestHint) {
            guestHint.hidden = signedIn;
        }
    };

    const emitSignedIn = (state) => {
        window.dispatchEvent(new CustomEvent('donor:signed-in', {
            detail: state,
        }));
    };

    const emitSignedOut = () => {
        window.dispatchEvent(new CustomEvent('donor:signed-out'));
    };

    const openModal = () => {
        lastFocused = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('donor-login-open');
        showStep('phone');
        setStatus('');
        closeCountryMenu();
        applyLoginMethod(loginMethod);
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.classList.remove('donor-login-open');
        closeCountryMenu();
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    };

    const applyLoginMethod = (method) => {
        loginMethod = method === 'email' ? 'email' : 'phone';

        if (phoneFields) {
            phoneFields.hidden = loginMethod !== 'phone';
        }

        if (emailFields) {
            emailFields.hidden = loginMethod !== 'email';
        }

        if (switchLabel) {
            switchLabel.textContent = loginMethod === 'email'
                ? 'Continue with mobile'
                : 'Continue with email';
        }

        if (leadEl) {
            leadEl.textContent = loginMethod === 'email'
                ? 'Confirm the email linked to your previous donation. We’ll send a one-time code to verify it’s you and restore your saved details.'
                : 'Confirm the mobile number linked to your previous donation. We’ll send a one-time code to verify it’s you and restore your saved details.';
        }

        if (loginMethod === 'email') {
            emailInput?.focus();
        } else {
            phoneInput?.focus();
        }
    };

    const clearCooldown = () => {
        if (cooldownTimer) {
            window.clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
        cooldownEndsAt = 0;
        if (resendBtn) {
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend code';
        }
    };

    const startCooldown = (seconds) => {
        clearCooldown();
        const total = Math.max(1, Number(seconds) || 60);
        cooldownEndsAt = Date.now() + (total * 1000);

        const tick = () => {
            const remaining = Math.max(0, Math.ceil((cooldownEndsAt - Date.now()) / 1000));

            if (!resendBtn) {
                return;
            }

            if (remaining <= 0) {
                clearCooldown();
                return;
            }

            resendBtn.disabled = true;
            resendBtn.textContent = `Resend in ${remaining}s`;
        };

        tick();
        cooldownTimer = window.setInterval(tick, 250);
    };

    const isValidPhone = (phone) => {
        if (selectedCountry.dial === '91') {
            return /^[0-9]{10}$/.test(phone);
        }

        return /^[0-9]{6,15}$/.test(phone);
    };

    const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    const identityPayload = () => {
        if (loginMethod === 'email') {
            return {
                login_method: 'email',
                donor_email: activeEmail || String(emailInput?.value || '').trim().toLowerCase(),
            };
        }

        return {
            login_method: 'phone',
            donor_phone: activePhone || String(phoneInput?.value || '').replace(/\D/g, ''),
            phone_dial_code: activeDial || selectedCountry.dial,
            donor_country_code: activeIso || selectedCountry.iso,
        };
    };

    const sendCode = async () => {
        let payload;

        if (loginMethod === 'email') {
            const email = String(emailInput?.value || '').trim().toLowerCase();

            if (!isValidEmail(email)) {
                setStatus('Enter a valid email address.', 'error');
                emailInput?.focus();
                return;
            }

            payload = {
                login_method: 'email',
                donor_email: email,
            };
            activeEmail = email;
            activePhone = '';
        } else {
            const phone = String(phoneInput?.value || '').replace(/\D/g, '');

            if (!isValidPhone(phone)) {
                setStatus(
                    selectedCountry.dial === '91'
                        ? 'Enter a valid 10-digit Indian mobile number.'
                        : 'Enter a valid mobile number for the selected country.',
                    'error',
                );
                phoneInput?.focus();
                return;
            }

            payload = {
                login_method: 'phone',
                donor_phone: phone,
                phone_dial_code: selectedCountry.dial,
                donor_country_code: selectedCountry.iso,
            };
            activePhone = phone;
            activeDial = selectedCountry.dial;
            activeIso = selectedCountry.iso;
            activeEmail = '';
        }

        if (!sendUrl) {
            return;
        }

        if (sendBtn) {
            sendBtn.disabled = true;
        }

        setStatus('Sending verification code…');

        try {
            const { response, data } = await postJson(sendUrl, payload);

            if (response.status === 429) {
                setStatus(data.message || 'Too many requests. Please wait a minute and try again.', 'error');
                return;
            }

            if (data.found === false) {
                setStatus(data.message || 'No saved profile found.', 'error');
                return;
            }

            if (!response.ok || data.sent === false) {
                setStatus(
                    data.message
                        || data.errors?.donor_phone?.[0]
                        || data.errors?.donor_email?.[0]
                        || 'Could not send code.',
                    'error',
                );
                if (data.cooldown_seconds) {
                    startCooldown(data.cooldown_seconds);
                }
                return;
            }

            showStep('otp');
            setStatus('');
            if (sentEl) {
                sentEl.textContent = data.message || 'Verification code sent.';
            }
            if (otpInput) {
                otpInput.value = '';
                otpInput.focus();
            }
            if (verifyBtn) {
                verifyBtn.disabled = true;
            }
            startCooldown(data.cooldown_seconds || 60);
        } catch (error) {
            setStatus('Could not send code. Please try again.', 'error');
        } finally {
            if (sendBtn) {
                sendBtn.disabled = false;
            }
        }
    };

    const verifyCode = async () => {
        const otp = String(otpInput?.value || '').replace(/\D/g, '');

        if (loginMethod === 'email') {
            if (!isValidEmail(activeEmail)) {
                showStep('phone');
                setStatus('Enter your email again.', 'error');
                return;
            }
        } else if (!isValidPhone(activePhone)) {
            showStep('phone');
            setStatus('Enter your mobile number again.', 'error');
            return;
        }

        if (otp.length < 4) {
            setStatus('Enter the verification code.', 'error');
            otpInput?.focus();
            return;
        }

        if (verifyBtn) {
            verifyBtn.disabled = true;
        }

        setStatus('Verifying…');

        try {
            const { response, data } = await postJson(verifyUrl, {
                ...identityPayload(),
                otp,
            });

            if (response.status === 429) {
                setStatus(data.message || 'Too many requests. Please wait a minute and try again.', 'error');
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                }
                return;
            }

            if (!response.ok || data.verified !== true) {
                setStatus(
                    data.message
                        || data.errors?.otp?.[0]
                        || data.errors?.donor_phone?.[0]
                        || data.errors?.donor_email?.[0]
                        || 'Verification failed.',
                    'error',
                );
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                }
                return;
            }

            const state = {
                signed_in: true,
                display_name: data.display_name || 'Donor',
                profile: data.profile || null,
            };

            setAuthUi(state);
            emitSignedIn(state);
            setStatus(data.message || 'Verified.', 'ok');
            window.setTimeout(closeModal, 450);
        } catch (error) {
            setStatus('Verification failed. Please try again.', 'error');
            if (verifyBtn) {
                verifyBtn.disabled = false;
            }
        }
    };

    const signOut = async () => {
        if (!logoutUrl) {
            return;
        }

        try {
            await postJson(logoutUrl, {});
        } catch (error) {
            // Still clear local UI if network fails mid-session.
        }

        setAuthUi({ signed_in: false, display_name: null, profile: null });
        emitSignedOut();
        closeModal();

        if (window.location.pathname.startsWith('/my-donations')) {
            window.location.href = '/';
        }
    };

    document.querySelectorAll('[data-donor-signin]').forEach((btn) => {
        btn.addEventListener('click', () => {
            openModal();
            const mobilePanel = document.querySelector('[data-header-mobile]');
            const toggle = document.querySelector('[data-header-toggle]');
            if (mobilePanel && !mobilePanel.hidden) {
                mobilePanel.hidden = true;
                document.body.classList.remove('site-header-open');
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.querySelectorAll('[data-donor-signout]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            signOut();
        });
    });

    modal.querySelectorAll('[data-donor-login-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            if (ccMenu && !ccMenu.hidden) {
                closeCountryMenu();
                return;
            }
            closeModal();
        }
    });

    document.addEventListener('click', (event) => {
        if (!ccRoot || ccMenu?.hidden) {
            return;
        }

        if (!ccRoot.contains(event.target)) {
            closeCountryMenu();
        }
    });

    ccToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        if (ccMenu?.hidden) {
            openCountryMenu();
        } else {
            closeCountryMenu();
        }
    });

    ccSearch?.addEventListener('input', () => {
        renderCountryList(ccSearch.value);
    });

    sendBtn?.addEventListener('click', sendCode);
    resendBtn?.addEventListener('click', sendCode);
    verifyBtn?.addEventListener('click', verifyCode);

    changePhoneBtn?.addEventListener('click', () => {
        clearCooldown();
        activePhone = '';
        activeEmail = '';
        showStep('phone');
        setStatus('');
        if (otpInput) {
            otpInput.value = '';
        }
        applyLoginMethod(loginMethod);
    });

    switchBtn?.addEventListener('click', () => {
        clearCooldown();
        setStatus('');
        applyLoginMethod(loginMethod === 'email' ? 'phone' : 'email');
    });

    emailInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendCode();
        }
    });

    phoneInput?.addEventListener('input', () => {
        phoneInput.value = String(phoneInput.value || '').replace(/\D/g, '').slice(0, maxPhoneLength());
    });

    phoneInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendCode();
        }
    });

    otpInput?.addEventListener('input', () => {
        otpInput.value = String(otpInput.value || '').replace(/\D/g, '').slice(0, 6);
        if (verifyBtn) {
            verifyBtn.disabled = otpInput.value.length < 4;
        }
    });

    otpInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            verifyCode();
        }
    });

    applyCountry(selectedCountry);
    renderCountryList();
    applyLoginMethod('phone');

    let initialState = { signed_in: false, display_name: null, profile: null };

    try {
        initialState = JSON.parse(modal.dataset.donorSession || '{}');
    } catch (error) {
        initialState = { signed_in: false, display_name: null, profile: null };
    }

    setAuthUi(initialState);

    if (initialState.signed_in && initialState.profile) {
        window.__DONOR_PORTAL__ = initialState;
        emitSignedIn(initialState);
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('signin') === '1' && !initialState.signed_in) {
        openModal();
    }
})();
