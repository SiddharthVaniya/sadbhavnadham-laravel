(() => {
    const form = document.getElementById('donationForm');
    if (!form) return;

    const readCookie = (name) => {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : '';
    };

    const resolveCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const input = form.querySelector('input[name="_token"]');

        return meta?.content
            || input?.value
            || form.dataset.csrf
            || '';
    };

    const syncCsrfFields = (token) => {
        if (!token) {
            return;
        }

        form.dataset.csrf = token;

        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }

        let input = form.querySelector('input[name="_token"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            form.prepend(input);
        }

        input.value = token;
    };

    /* ===============================
       ELEMENTS
    =============================== */
    const amountInput = document.getElementById('donationAmount');
    const unitAmountInput = document.getElementById('unitAmount');
    const qtyInput = document.getElementById('sevaQty');
    const qtyHidden = document.getElementById('quantityInput');
    const minusButton = document.getElementById('qtyMinus');
    const plusButton = document.getElementById('qtyPlus');
    const titleInput = document.getElementById('donationTitle');
    const metaInput = document.getElementById('donationMeta');
    const packageInput = document.getElementById('donationPackageId');
    const summaryText = document.getElementById('donationSummaryText');
    const sevaImage = document.getElementById('sevaImage');

    const qtyBox = document.getElementById('sevaQuantityBox');
    const honoreeBox = document.getElementById('honoreeNamesBox');
    const honoreeList = document.getElementById('honoreeNamesList');
    const honoreeHeading = document.getElementById('honoreeNamesHeading');
    const customBadge = document.getElementById('customDonationBadge');
    const donationTypeInput = document.getElementById('donationType');

    const pincodeInput = document.getElementById('donorPincode');
    const cityInput = document.getElementById('donorCity');
    const stateInput = document.getElementById('donorState');
    const countryInput = document.getElementById('donorCountryName');
    const pincodeFetchStatus = document.getElementById('pincodeFetchStatus');
    const pincodeFetchSpinner = document.getElementById('pincodeFetchSpinner');
    const pincodeFetchStatusText = document.getElementById('pincodeFetchStatusText');
    const phoneInput = document.getElementById('donorPhone');
    const emailInput = document.getElementById('donorEmail');
    const panInput = form.querySelector('input[name="pan_number"]');
    const panFieldWrap = document.getElementById('panFieldWrap');
    const panRequiredMark = document.getElementById('panRequiredMark');
    const panOptionalMark = document.getElementById('panOptionalMark');
    const panNotice = document.getElementById('panNotice');
    const panRequirementHint = document.getElementById('panRequirementHint');

    const panEnabled = form.dataset.panEnabled === '1';
    const panCheckUrl = form.dataset.panCheckUrl || '';
    const causeInput = form.querySelector('input[name="cause"]');

    const allowCustomAmount = form.dataset.allowCustom === '1';
    const causeLabel = form.dataset.causeLabel || '';
    const customTitle = form.dataset.customTitle || 'Custom Donation';

    /* ===============================
       HELPERS
    =============================== */
    const syncHonoreeFields = () => {
        if (!honoreeBox || !honoreeList) {
            return;
        }

        const quantityVisible = Boolean(qtyBox && qtyBox.style.display !== 'none');
        if (!quantityVisible) {
            honoreeBox.hidden = true;
            honoreeList.replaceChildren();
            return;
        }

        honoreeBox.hidden = false;

        const qty = Math.max(1, Math.min(100, Number(qtyHidden?.value || qtyInput?.value || 1)));
        const previous = Array.from(honoreeList.querySelectorAll('input[name="honoree_names[]"]'))
            .map((input) => input.value);

        if (honoreeHeading) {
            honoreeHeading.textContent = qty === 1 ? 'Name on this tree' : 'Names on these trees';
        }

        honoreeList.replaceChildren();

        for (let index = 0; index < qty; index += 1) {
            const group = document.createElement('div');
            group.className = 'honoree-name-field';

            const inputId = `honoreeName${index}`;
            let label = null;

            if (qty > 1) {
                label = document.createElement('label');
                label.setAttribute('for', inputId);
                label.textContent = `Name on tree ${index + 1}`;
            }

            const input = document.createElement('input');
            input.type = 'text';
            input.id = inputId;
            input.name = 'honoree_names[]';
            input.maxLength = 80;
            input.autocomplete = 'name';
            input.placeholder = 'Name on tree plate';
            input.value = previous[index] || '';

            if (qty === 1 && honoreeHeading) {
                input.setAttribute('aria-labelledby', 'honoreeNamesHeading');
            }

            if (label) {
                group.append(label);
            }
            group.append(input);
            honoreeList.append(group);
        }
    };

    const showQuantity = () => {
        if (qtyBox) qtyBox.style.display = '';
        syncHonoreeFields();
    };

    const hideQuantity = () => {
        if (qtyBox) qtyBox.style.display = 'none';
        syncHonoreeFields();
    };

    const showCustomBadge = () => {
        if (customBadge) customBadge.style.display = 'inline-block';
    };

    const hideCustomBadge = () => {
        if (customBadge) customBadge.style.display = 'none';
    };

    const isRecurringMode = () => donationTypeInput?.value === 'recurring';

    const syncQuantityVisibility = () => {
        if (isRecurringMode()) {
            hideQuantity();
            if (qtyInput) qtyInput.value = '1';
            if (qtyHidden) qtyHidden.value = '1';
        } else {
            showQuantity();
        }
    };

    const updateSummary = (title) => {
        if (!summaryText) return;
        summaryText.textContent = `${causeLabel} – ${title}`;
    };

    /* ===============================
       RECURRING DONATION TOGGLE
    =============================== */
    const showRecurring = form.dataset.showRecurring === '1';
    const forceRecurring = form.dataset.forceRecurring === '1';
    const lockAmount = form.dataset.lockAmount === '1';
    const showFrequency = form.dataset.showFrequency === '1';
    const donationFrequencyInput = document.getElementById('donationFrequency');
    const donationTypeSection = document.getElementById('donationTypeSection');
    const oneTimeHelpText = document.getElementById('oneTimeHelpText');
    const recurringHelpText = document.getElementById('recurringHelpText');
    const frequencyHelpText = document.getElementById('frequencyHelpText');
    const donationAmountHelpText = document.getElementById('donationAmountHelpText');
    const campaignGiftLabel = document.getElementById('campaignGiftLabel');
    const campaignPeriodLabel = document.getElementById('campaignPeriodLabel');
    const recurringConsentWrap = document.getElementById('recurringConsentWrap');
    const recurringConsentInput = document.getElementById('consentRecurring');
    const presetAmountButtons = document.getElementById('presetAmountButtons');
    const donationSubmitButton = document.getElementById('donationSubmitButton');
    const donationTypeButtons = document.querySelectorAll('#donationTypeToggle .donation-type-tab');
    const donationFrequencyButtons = document.querySelectorAll('#donationFrequencyToggle .donation-type-tab');

    const frequencyCopy = {
        monthly: {
            giftLabel: 'Monthly gift',
            periodLabel: 'per month',
            amountHelp: 'This campaign uses a fixed monthly amount.',
            helpText: 'Your chosen amount will be charged every month until you cancel the mandate.',
            cta: 'Start Monthly Donation',
        },
        weekly: {
            giftLabel: 'Weekly gift',
            periodLabel: 'per week',
            amountHelp: 'This campaign uses a fixed weekly amount.',
            helpText: 'Your chosen amount will be charged every week until you cancel the mandate.',
            cta: 'Start Weekly Donation',
        },
    };

    let setActivePackage = () => {};

    const currentFrequency = () => donationFrequencyInput?.value || form.dataset.frequency || 'monthly';

    const setFrequencyMode = (frequency) => {
        const next = frequency === 'weekly' ? 'weekly' : 'monthly';
        const copy = frequencyCopy[next];

        form.dataset.frequency = next;

        if (donationFrequencyInput) {
            donationFrequencyInput.value = next;
            donationFrequencyInput.disabled = false;
        }

        donationFrequencyButtons.forEach((button) => {
            const isActive = button.dataset.frequency === next;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (frequencyHelpText) {
            frequencyHelpText.textContent = copy.helpText;
        }

        if (donationAmountHelpText && lockAmount) {
            donationAmountHelpText.textContent = copy.amountHelp;
        }

        if (campaignGiftLabel) {
            campaignGiftLabel.textContent = copy.giftLabel;
        }

        if (campaignPeriodLabel) {
            campaignPeriodLabel.textContent = copy.periodLabel;
        }

        if (donationSubmitButton && (forceRecurring || isRecurringMode())) {
            donationSubmitButton.textContent = copy.cta;
        }
    };

    const setRecurringMode = (enabled) => {
        if (!donationTypeInput) {
            return;
        }

        donationTypeInput.value = enabled ? 'recurring' : 'one_time';

        if (donationFrequencyInput) {
            donationFrequencyInput.disabled = !enabled;
        }

        if (oneTimeHelpText) {
            oneTimeHelpText.style.display = enabled ? 'none' : 'block';
        }

        if (recurringHelpText) {
            recurringHelpText.style.display = enabled ? 'block' : 'none';
        }

        if (recurringConsentWrap) {
            recurringConsentWrap.style.display = enabled ? 'block' : 'none';
        }

        if (recurringConsentInput) {
            recurringConsentInput.required = enabled;
            if (!enabled) {
                recurringConsentInput.checked = false;
            }
        }

        if (presetAmountButtons) {
            presetAmountButtons.style.display = enabled ? 'none' : '';
        }

        if (amountInput) {
            amountInput.readOnly = !allowCustomAmount;
        }

        if (enabled) {
            if (!allowCustomAmount) {
                const activePill = document.querySelector('.seva-pill.active');
                if (activePill) {
                    setActivePackage(activePill, { skipRecurringSync: true });
                } else {
                    const firstPill = document.querySelector('.seva-pill');
                    if (firstPill) {
                        setActivePackage(firstPill, { skipRecurringSync: true });
                    }
                }
            }

            hideQuantity();
            if (!allowCustomAmount) {
                hideCustomBadge();
            }
        } else if (allowCustomAmount) {
            syncQuantityVisibility();
        }

        if (donationSubmitButton) {
            const defaultText = donationSubmitButton.dataset.defaultText || donationSubmitButton.textContent;
            if (!donationSubmitButton.dataset.defaultText) {
                donationSubmitButton.dataset.defaultText = defaultText;
            }

            if (enabled) {
                const copy = frequencyCopy[currentFrequency()] || frequencyCopy.monthly;
                donationSubmitButton.textContent = copy.cta;
            } else {
                donationSubmitButton.textContent = defaultText;
            }
        }
    };

    const syncRecurringForActivePackage = () => {
        if (!showRecurring || !donationTypeSection) {
            return;
        }

        const activePill = document.querySelector('.seva-pill.active');
        const allows = (activePill?.dataset.allowRecurring === '1') || allowCustomAmount;

        donationTypeSection.style.display = allows ? '' : 'none';

        if (!allows && isRecurringMode()) {
            const oneTimeButton = document.querySelector('.donation-type-tab[data-donation-type="one_time"]');
            if (oneTimeButton) {
                donationTypeButtons.forEach((item) => {
                    item.classList.remove('active');
                    item.setAttribute('aria-selected', 'false');
                });
                oneTimeButton.classList.add('active');
                oneTimeButton.setAttribute('aria-selected', 'true');
            }

            setRecurringMode(false);
        }
    };

    if (showRecurring && donationTypeButtons.length && !forceRecurring) {
        donationTypeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                donationTypeButtons.forEach((item) => {
                    item.classList.remove('active');
                    item.setAttribute('aria-selected', 'false');
                });
                button.classList.add('active');
                button.setAttribute('aria-selected', 'true');

                const isRecurring = button.dataset.donationType === 'recurring';
                if (isRecurring && button.dataset.frequency) {
                    setFrequencyMode(button.dataset.frequency);
                }
                setRecurringMode(isRecurring);
                if (isRecurring && recurringHelpText) {
                    const copy = frequencyCopy[currentFrequency()] || frequencyCopy.monthly;
                    recurringHelpText.textContent = copy.helpText.replace('campaign amount', 'chosen amount');
                }
            });
        });
    }

    if (showFrequency && donationFrequencyButtons.length) {
        donationFrequencyButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setFrequencyMode(button.dataset.frequency);
            });
        });
    }

    /* ===============================
       PACKAGE SELECTION
    =============================== */
    setActivePackage = (pill, options = {}) => {
        document.querySelectorAll('.seva-pill').forEach(p => p.classList.remove('active'));
        if (!pill) return;

        pill.classList.add('active');

        const title = pill.dataset.title || customTitle;
        const amount = Number(pill.dataset.amount || 0);
        const image = pill.dataset.image || '';
        const meta = pill.dataset.meta || '';
        const packageId = pill.dataset.packageId || '';

        if (titleInput) titleInput.value = title;
        if (amountInput) amountInput.value = amount;
        if (unitAmountInput) unitAmountInput.value = amount;
        if (qtyInput) qtyInput.value = '1';
        if (qtyHidden) qtyHidden.value = '1';
        if (packageInput) packageInput.value = packageId;
        if (metaInput) metaInput.value = meta;
        if (sevaImage && image) sevaImage.src = image;

        syncQuantityVisibility();
        hideCustomBadge();
        updateSummary(title);

        if (!options.skipRecurringSync) {
            syncRecurringForActivePackage();
        }
    };

    document.querySelectorAll('.seva-pill').forEach(pill => {
        pill.addEventListener('click', () => setActivePackage(pill));
    });

    /* ===============================
       CUSTOM AMOUNT INPUT
    =============================== */
    if (amountInput && allowCustomAmount) {
        amountInput.addEventListener('input', () => {
            const rawValue = amountInput.value;
            const sanitizedValue = rawValue === '' ? '' : String(Math.trunc(Number(rawValue) || 0));
            if (amountInput.value !== sanitizedValue) {
                amountInput.value = sanitizedValue;
            }

            if (!isRecurringMode()) {
                document.querySelectorAll('.seva-pill').forEach(p => p.classList.remove('active'));
                hideQuantity();
                showCustomBadge();
            }

            if (packageInput) packageInput.value = '';
            if (titleInput) titleInput.value = isRecurringMode() ? (customTitle || 'Monthly Donation') : customTitle;
            if (metaInput) metaInput.value = '';
            if (unitAmountInput) unitAmountInput.value = amountInput.value || '';
            if (qtyInput) qtyInput.value = '1';
            if (qtyHidden) qtyHidden.value = '1';

            updateSummary(isRecurringMode() ? 'Monthly Donation' : 'Donation');
            syncRecurringForActivePackage();
        });
    }

    /* ===============================
       QUANTITY HANDLING
    =============================== */
    const getBaseAmount = () => {
        const unit = Number(unitAmountInput?.value || 0);
        if (unit > 0) return unit;

        const qty = Number(qtyInput?.value || 1);
        const amount = Number(amountInput?.value || 0);
        return qty > 0 ? amount / qty : 0;
    };

    const updateQuantity = (nextQty) => {
        const qty = Math.max(1, Math.min(100, nextQty));
        qtyInput.value = String(qty);
        if (qtyHidden) qtyHidden.value = String(qty);

        const base = getBaseAmount();
        if (base > 0) {
            amountInput.value = String(Math.round(base * qty));
        }

        syncHonoreeFields();
    };

    if (minusButton && plusButton) {
        minusButton.addEventListener('click', () => {
            updateQuantity((Number(qtyInput.value) || 1) - 1);
        });

        plusButton.addEventListener('click', () => {
            updateQuantity((Number(qtyInput.value) || 1) + 1);
        });
    }

    /* ===============================
       PRESET AMOUNT BUTTONS
    =============================== */
    document.querySelectorAll('.amount-buttons button').forEach(button => {
        button.addEventListener('click', () => {
            const amountValue = Number(button.dataset.amount || 0);
            if (!amountValue) return;

            amountInput.value = amountValue;
            if (unitAmountInput) unitAmountInput.value = amountValue;
            if (qtyInput) qtyInput.value = '1';
            if (qtyHidden) qtyHidden.value = '1';

            document.querySelectorAll('.seva-pill').forEach(p => p.classList.remove('active'));
            hideQuantity();
            showCustomBadge();

            if (packageInput) packageInput.value = '';
            if (titleInput) titleInput.value = customTitle;
            if (metaInput) metaInput.value = '';

            updateSummary('Donation');
            syncRecurringForActivePackage();

        });
    });

    /* ===============================
       INDIA PINCODE AUTO-FILL
    =============================== */
    let pincodeDebounceTimer = null;
    let pincodeAbortController = null;

    const unlockLocationFields = () => {
        if (cityInput) {
            cityInput.readOnly = false;
            cityInput.value = '';
        }
        if (stateInput) {
            stateInput.readOnly = false;
            stateInput.value = '';
        }
    };

    const setPincodeStatus = (message, tone = 'muted') => {
        if (!pincodeFetchStatus) {
            return;
        }

        if (pincodeFetchStatusText) {
            pincodeFetchStatusText.textContent = message;
        } else {
            pincodeFetchStatus.textContent = message;
        }

        pincodeFetchStatus.style.display = message ? 'block' : 'none';
        pincodeFetchStatus.classList.remove('text-muted', 'text-success', 'text-danger');

        if (pincodeFetchSpinner) {
            pincodeFetchSpinner.style.display = tone === 'loading' && message ? 'inline-block' : 'none';
        }

        if (tone === 'loading') {
            pincodeFetchStatus.classList.add('text-muted');
            return;
        }

        if (tone === 'success') {
            pincodeFetchStatus.classList.add('text-success');
            return;
        }

        if (tone === 'danger') {
            pincodeFetchStatus.classList.add('text-danger');
            return;
        }

        pincodeFetchStatus.classList.add('text-muted');
    };

    const hydrateLocationByPincode = async (pincode) => {
        if (!cityInput || !stateInput || pincode.length !== 6) {
            return;
        }

        // Cancel any in-flight request for a previous pincode.
        if (pincodeAbortController) {
            pincodeAbortController.abort();
        }
        pincodeAbortController = new AbortController();

        cityInput.readOnly = true;
        stateInput.readOnly = true;
        setPincodeStatus('Fetching city and state for your pincode...', 'loading');

        try {
            const response = await fetch(`https://api.postalpincode.in/pincode/${pincode}`, {
                signal: pincodeAbortController.signal,
            });
            if (!response.ok) {
                unlockLocationFields();
                setPincodeStatus('Could not fetch details right now. Please enter city and state manually.', 'danger');
                return;
            }

            const data = await response.json();
            const postOffice = data?.[0]?.PostOffice?.[0];

            if (!postOffice) {
                unlockLocationFields();
                setPincodeStatus('Pincode not found. Please enter city and state manually.', 'danger');
                return;
            }

            cityInput.value = postOffice.District || '';
            stateInput.value = postOffice.State || '';
            if (countryInput) {
                countryInput.value = 'INDIA';
            }

            cityInput.readOnly = true;
            stateInput.readOnly = true;
            setPincodeStatus('City and state auto-filled successfully.', 'success');
        } catch (error) {
            if (error.name !== 'AbortError') {
                unlockLocationFields();
                setPincodeStatus('Unable to fetch pincode details. Please enter city and state manually.', 'danger');
            }
        }
    };

    if (pincodeInput) {
        pincodeInput.addEventListener('input', () => {
            pincodeInput.value = pincodeInput.value.replace(/\D/g, '').slice(0, 6);

            // Always clear location fields immediately on any pincode change.
            unlockLocationFields();
            setPincodeStatus('');

            if (pincodeDebounceTimer) {
                clearTimeout(pincodeDebounceTimer);
                pincodeDebounceTimer = null;
            }

            if (pincodeAbortController) {
                pincodeAbortController.abort();
                pincodeAbortController = null;
            }

            if (pincodeInput.value.length === 6) {
                pincodeDebounceTimer = setTimeout(() => {
                    pincodeDebounceTimer = null;
                    hydrateLocationByPincode(pincodeInput.value);
                }, 250);
            }
        });
    }

    if (panInput) {
        panInput.addEventListener('input', () => {
            panInput.value = panInput.value.toUpperCase();
        });
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
            phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
        });
    }

    /* ===============================
       PAN REQUIREMENT
    =============================== */
    let panCheckTimer = null;
    let panCheckAbortController = null;
    let panManuallyEdited = false;

    const getDonationTotalAmount = () => {
        const quantity = Math.max(1, Number(qtyHidden?.value || qtyInput?.value || 1));
        const packageId = packageInput?.value || '';
        const unit = Number(unitAmountInput?.value || 0);
        const amount = Number(amountInput?.value || 0);

        if (packageId) {
            const base = unit > 0 ? unit : amount;
            return Math.round(base * quantity);
        }

        return Math.round(amount > 0 ? amount : unit * quantity);
    };

    const setPanRequirementState = (required, data = {}) => {
        if (!panFieldWrap) {
            return;
        }

        if (!panEnabled) {
            panFieldWrap.style.display = 'none';
            if (panNotice) panNotice.style.display = 'none';
            if (panRequiredMark) panRequiredMark.style.display = 'none';
            if (panOptionalMark) panOptionalMark.style.display = 'none';
            if (panRequirementHint) {
                panRequirementHint.style.display = 'none';
                panRequirementHint.textContent = '';
            }
            if (panInput) {
                panInput.required = false;
            }
            return;
        }

        panFieldWrap.style.display = '';
        if (panNotice) panNotice.style.display = '';

        if (required) {
            if (panRequiredMark) panRequiredMark.style.display = '';
            if (panOptionalMark) panOptionalMark.style.display = 'none';
            if (panInput) {
                panInput.required = true;
                if (!panManuallyEdited && data.known_pan_number && !panInput.value) {
                    panInput.value = data.known_pan_number;
                }
            }
            if (panRequirementHint && Number(data.fy_paid_total || 0) > 0 && Number(data.current_amount || 0) < Number(data.threshold || 100000)) {
                const paid = Number(data.fy_paid_total || 0).toLocaleString('en-IN');
                panRequirementHint.textContent = `You have already donated ₹${paid} this financial year. PAN is required to continue.`;
                panRequirementHint.style.display = 'block';
            } else if (panRequirementHint) {
                panRequirementHint.textContent = 'PAN is required because this donation reaches ₹1,00,000 (single or financial-year total).';
                panRequirementHint.style.display = 'block';
            }
        } else {
            if (panRequiredMark) panRequiredMark.style.display = 'none';
            if (panOptionalMark) panOptionalMark.style.display = '';
            if (panInput) {
                panInput.required = false;
                if (!panManuallyEdited && data.known_pan_number && !panInput.value) {
                    panInput.value = data.known_pan_number;
                }
            }
            if (panRequirementHint) {
                panRequirementHint.style.display = 'none';
                panRequirementHint.textContent = '';
            }
        }
    };

    const syncPanRequirement = () => {
        if (!panEnabled || !panCheckUrl) {
            setPanRequirementState(false);
            return;
        }

        setPanRequirementState(false);

        const currentAmount = getDonationTotalAmount();
        const threshold = 100000;
        const email = emailInput?.value?.trim() || '';
        const phone = phoneInput?.value?.trim() || '';

        if (phone.length !== 10) {
            setPanRequirementState(currentAmount >= threshold);
            return;
        }

        if (panCheckAbortController) {
            panCheckAbortController.abort();
        }

        panCheckAbortController = new AbortController();

        const payload = new FormData();
        payload.append('cause', causeInput?.value || '');
        payload.append('amount', String(currentAmount));
        payload.append('quantity', String(qtyHidden?.value || qtyInput?.value || '1'));
        if (packageInput?.value) {
            payload.append('package_id', packageInput.value);
        }
        if (email) {
            payload.append('donor_email', email);
        }
        payload.append('donor_phone', phone);

        const csrfToken = resolveCsrfToken();
        const xsrfToken = readCookie('XSRF-TOKEN');
        syncCsrfFields(csrfToken);
        if (csrfToken) {
            payload.set('_token', csrfToken);
        }

        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        if (xsrfToken) {
            headers['X-XSRF-TOKEN'] = xsrfToken;
        }

        fetch(panCheckUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: payload,
            signal: panCheckAbortController.signal,
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => {
                if (!data) {
                    return;
                }

                setPanRequirementState(Boolean(data.required), data);
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    setPanRequirementState(currentAmount >= threshold);
                }
            });
    };

    const schedulePanRequirementCheck = () => {
        if (panCheckTimer) {
            clearTimeout(panCheckTimer);
        }

        panCheckTimer = setTimeout(() => {
            panCheckTimer = null;
            syncPanRequirement();
        }, 350);
    };

    if (panEnabled) {
        if (panInput) {
            panInput.addEventListener('input', () => {
                panManuallyEdited = true;
                panInput.value = panInput.value.toUpperCase();
            });
        }

        [amountInput, qtyInput, emailInput, phoneInput].forEach((element) => {
            if (element) {
                element.addEventListener('input', schedulePanRequirementCheck);
            }
        });

        if (minusButton) {
            minusButton.addEventListener('click', schedulePanRequirementCheck);
        }

        if (plusButton) {
            plusButton.addEventListener('click', schedulePanRequirementCheck);
        }

        document.querySelectorAll('.amount-buttons button').forEach((button) => {
            button.addEventListener('click', schedulePanRequirementCheck);
        });

        document.querySelectorAll('.seva-pill').forEach((pill) => {
            pill.addEventListener('click', schedulePanRequirementCheck);
        });
    }

    /* ===============================
       INIT
    =============================== */
    if (unitAmountInput && amountInput && !unitAmountInput.value) {
        unitAmountInput.value = amountInput.value || '';
    }

    const urlParams = new URLSearchParams(window.location.search);
    const packageIdFromUrl = urlParams.get('package_id');
    if (packageIdFromUrl) {
        const escapedPackageId = (window.CSS && typeof window.CSS.escape === 'function')
            ? window.CSS.escape(packageIdFromUrl)
            : String(packageIdFromUrl).replace(/["\\]/g, '\\$&');
        const matchedPill = document.querySelector(`.seva-pill[data-package-id="${escapedPackageId}"]`);
        if (matchedPill && typeof setActivePackage === 'function') {
            setActivePackage(matchedPill);
        }
    }

    if (lockAmount && amountInput) {
        amountInput.readOnly = true;
        if (presetAmountButtons) {
            presetAmountButtons.style.display = 'none';
        }
    }

    if (forceRecurring) {
        setRecurringMode(true);
        if (donationTypeSection) {
            donationTypeSection.style.display = 'none';
        }
        if (showFrequency) {
            setFrequencyMode(currentFrequency());
        }
    } else {
        syncRecurringForActivePackage();
    }

    schedulePanRequirementCheck();
    syncHonoreeFields();

    /* ===============================
       RETURNING DONOR SESSION AUTOFILL
    =============================== */
    const dobInput = document.getElementById('donorDateOfBirth');
    const nameInput = document.getElementById('donorName');
    const addressInput = document.getElementById('donorAddress');

    const setDobValue = (value) => {
        if (! dobInput || ! value) {
            return;
        }

        dobInput.value = value;
    };

    const clearDobValue = () => {
        if (dobInput) {
            dobInput.value = '';
        }
    };

    const fillDonorProfile = (profile) => {
        if (! profile || typeof profile !== 'object') {
            return;
        }

        if (nameInput && profile.donor_name) {
            nameInput.value = profile.donor_name;
        }

        if (emailInput && profile.donor_email) {
            emailInput.value = profile.donor_email;
        }

        if (phoneInput && profile.donor_phone) {
            const digits = String(profile.donor_phone).replace(/\D/g, '');
            phoneInput.maxLength = Math.max(10, Math.min(15, digits.length || 10));
            phoneInput.value = digits.slice(0, phoneInput.maxLength);
        }

        if (profile.date_of_birth) {
            setDobValue(profile.date_of_birth);
        }

        if (addressInput && profile.address) {
            addressInput.value = profile.address;
        }

        if (pincodeInput && profile.pincode) {
            pincodeInput.value = profile.pincode;
        }

        if (cityInput && profile.city) {
            cityInput.value = profile.city;
        }

        if (stateInput && profile.state) {
            stateInput.value = profile.state;
        }

        if (countryInput && profile.country) {
            countryInput.value = profile.country;
        }

        if (panInput && profile.pan_number) {
            panInput.value = String(profile.pan_number).toUpperCase();
            panManuallyEdited = false;
        }

        schedulePanRequirementCheck();
    };

    const clearDonorProfileFields = () => {
        [nameInput, emailInput, phoneInput, addressInput, pincodeInput, cityInput, stateInput, panInput]
            .filter(Boolean)
            .forEach((input) => {
                input.value = '';
            });

        clearDobValue();

        if (countryInput) {
            countryInput.value = 'INDIA';
        }

        schedulePanRequirementCheck();
    };

    window.addEventListener('donor:signed-in', (event) => {
        fillDonorProfile(event.detail?.profile || null);
    });

    window.addEventListener('donor:signed-out', () => {
        clearDonorProfileFields();
    });

    if (window.__DONOR_PORTAL__?.signed_in && window.__DONOR_PORTAL__?.profile) {
        fillDonorProfile(window.__DONOR_PORTAL__.profile);
    }
})();
