@php
    $allowCustomAmount = $allowCustomAmount ?? true;
    $panCollectionEnabled = $panCollectionEnabled ?? ($panRequired ?? true);
    $defaultPackageId = $defaultPackageId ?? null;
    $causeLabel = $causeLabel ?? null;
    $forceRecurring = (bool) ($forceRecurring ?? false);
    $lockAmount = (bool) ($lockAmount ?? false);
    $showRecurringOption = (bool) ($showRecurringOption ?? false) || $forceRecurring;
    $allowedFrequencies = collect($allowedFrequencies ?? ['monthly'])
        ->filter(fn ($frequency) => in_array($frequency, ['monthly', 'weekly'], true))
        ->values()
        ->all();
    if ($allowedFrequencies === []) {
        $allowedFrequencies = ['monthly'];
    }
    $defaultFrequency = in_array(($defaultFrequency ?? null), $allowedFrequencies, true)
        ? $defaultFrequency
        : $allowedFrequencies[0];
    $allowMonthlyFrequency = in_array('monthly', $allowedFrequencies, true);
    $allowWeeklyFrequency = in_array('weekly', $allowedFrequencies, true);
    $frequencyTabCount = 1 + (int) $allowMonthlyFrequency + (int) $allowWeeklyFrequency;
    $frequencyAdjective = \App\Support\SubscriptionFrequency::adjective($defaultFrequency);
    $frequencyGiftLabel = \App\Support\SubscriptionFrequency::giftLabel($defaultFrequency);
    $frequencyPeriodLabel = \App\Support\SubscriptionFrequency::periodLabel($defaultFrequency);
    $frequencyCadence = \App\Support\SubscriptionFrequency::cadenceDescription($defaultFrequency);
    $campaignSlug = $campaignSlug ?? null;
    $subscriptionMinAmount = (int) config('payments.razorpay.subscription_min_amount', 100);
    $amountMin = $showRecurringOption ? $subscriptionMinAmount : 1;
@endphp

<h5>Complete Your Donation</h5>
<style>
    .custom-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 3px 10px;
    font-size: 12px;
   
    color: #b45309;
    border-radius: 999px;
    font-weight: 600;
}

</style>
<div class="selected-cause">
    You are donating for:
    <strong id="donationSummaryText">
        {{ $causeLabel ?? ucwords($cause) }} – {{ ucwords($defaultTitle) }}
    </strong>

    <span id="customDonationBadge"
          class="custom-badge"
          style="display:none;">
        General Donation
    </span>
</div>

<form id="donationForm"
    data-action="{{ route('donate.razorpay') }}"
    @if ($showRecurringOption)
        data-subscription-action="{{ route('donate.razorpay.subscription') }}"
    @endif
    data-csrf="{{ csrf_token() }}"
    data-allow-custom="{{ $allowCustomAmount ? '1' : '0' }}"
    data-show-recurring="{{ $showRecurringOption ? '1' : '0' }}"
    data-force-recurring="{{ $forceRecurring ? '1' : '0' }}"
    data-lock-amount="{{ $lockAmount ? '1' : '0' }}"
    data-show-frequency="0"
    data-frequency="{{ $defaultFrequency }}"
    data-cause-label="{{ $causeLabel ?? ucfirst($cause) }}"
    data-razorpay-name="{{ $branding['razorpayName'] }}"
    data-pan-enabled="{{ $panCollectionEnabled ? '1' : '0' }}"
    data-pan-check-url="{{ route('donate.pan-requirement') }}"
>
    @csrf

    <div
        class="donor-signed-banner"
        data-donor-form-banner
        @unless (($donorPortal['signed_in'] ?? false)) hidden @endunless
    >
        <div>
            <strong data-donor-banner-name>Signed in as {{ $donorPortal['display_name'] ?? 'Donor' }}</strong>
            <span>
                Your saved details are filled below.
                <a href="{{ route('donate.portal.index') }}" class="donor-signed-banner__link">View donation history</a>
            </span>
        </div>
        <button type="button" class="donor-signed-banner__out" data-donor-signout>Sign out</button>
    </div>

    {{-- CORE DATA --}}
    <input type="hidden" name="cause" value="{{ $cause }}">
    @if ($campaignSlug)
        <input type="hidden" name="campaign_slug" value="{{ $campaignSlug }}">
    @endif
    @if ($lockAmount)
        <input type="hidden" name="amount_locked" value="1">
    @endif
    <input type="hidden" id="donationType" name="donation_type" value="{{ $forceRecurring ? 'recurring' : 'one_time' }}">
    <input type="hidden" id="donationFrequency" name="frequency" value="{{ $defaultFrequency }}" @if (! $forceRecurring) disabled @endif>
    <input type="hidden" id="donationTitle" name="title" value="{{ $defaultTitle }}">
    <input type="hidden" id="donationMeta" name="meta">
    <input type="hidden" id="donationPackageId" name="package_id" value="{{ $defaultPackageId }}">
    <input type="hidden" id="donorCountry" name="donor_country" value="IN">
    {{-- SEVA CALCULATION --}}
    <input type="hidden" id="unitAmount" name="unit_amount">
    <input type="hidden" id="quantityInput" name="quantity" value="1">

    @if ($forceRecurring)
        <div class="donation-cadence-card mb-3" id="donationCadenceCard">
            <div class="donation-cadence-card__badge">{{ $frequencyGiftLabel }}</div>
            <div class="donation-cadence-card__body">
                <strong>Billed {{ $frequencyPeriodLabel }}</strong>
                <p>{{ $frequencyCadence }}</p>
            </div>
        </div>
    @endif

    @if ($showRecurringOption && ! $forceRecurring)
        <div class="donation-type-section mb-3" id="donationTypeSection">
            <p class="donation-type-heading mb-2">How would you like to give?</p>
            <div
                class="donation-type-tabs"
                id="donationTypeToggle"
                role="tablist"
                aria-label="Donation type"
                data-tab-count="{{ $frequencyTabCount }}"
            >
                <button
                    type="button"
                    class="donation-type-tab active"
                    role="tab"
                    aria-selected="true"
                    data-donation-type="one_time"
                >
                    <span class="donation-type-tab-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <span class="donation-type-tab-copy">
                        <strong>One-time</strong>
                        <small>Pay once today</small>
                    </span>
                </button>
                @if ($allowMonthlyFrequency)
                    <button
                        type="button"
                        class="donation-type-tab"
                        role="tab"
                        aria-selected="false"
                        data-donation-type="recurring"
                        data-frequency="monthly"
                    >
                        <span class="donation-type-tab-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        </span>
                        <span class="donation-type-tab-copy">
                            <strong>Every month</strong>
                            <small>Automatic monthly giving</small>
                        </span>
                    </button>
                @endif
                @if ($allowWeeklyFrequency)
                    <button
                        type="button"
                        class="donation-type-tab"
                        role="tab"
                        aria-selected="false"
                        data-donation-type="recurring"
                        data-frequency="weekly"
                    >
                        <span class="donation-type-tab-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </span>
                        <span class="donation-type-tab-copy">
                            <strong>Every week</strong>
                            <small>Automatic weekly giving</small>
                        </span>
                    </button>
                @endif
            </div>
            <p class="donation-type-note" id="oneTimeHelpText">
                You pay once — no future charges unless you donate again.
            </p>
            <p class="donation-type-note donation-type-note--recurring" id="recurringHelpText" style="display: none;">
                Your chosen amount will be charged every month until you cancel the mandate.
            </p>
        </div>
    @endif

    {{-- AMOUNT --}}
    <div class="d-flex gap-2 flex-wrap flex-sm-nowrap">
        <div class="form-group mb-1">
            <label>Donation Amount (₹)</label>
                <input type="number" step="1" id="donationAmount" name="amount" class="amount-input" value="{{ (int) $defaultAmount }}"
                    min="{{ $amountMin }}" required @if (! $allowCustomAmount) readonly @endif>
            <small class="help-text" id="donationAmountHelpText">
                @if ($lockAmount)
                    This campaign uses a fixed {{ $frequencyAdjective }} amount.
                @else
                    You can enter a custom amount or select an option from the left.
                @endif
            </small>
        </div>
        <div id="sevaQuantityBox" class="form-group mb-1" @if ($forceRecurring) style="display: none;" @endif>
            <label>Quantity</label>
            <div class="input-group flex-nowrap" style="max-width: 200px;">
                <button class="btn btnQty btn-sm" type="button" id="qtyMinus">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                    </span>
                </button>
                <input type="text" id="sevaQty" class="amount-input text-center" value="1" readonly>
                <button class="btn btnQty btn-sm" type="button" id="qtyPlus">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                    </span>
                </button>
            </div>
            <small class="help-text text-muted text-nowrap">
                Total = Base Amount × Quantity
            </small>
        </div>
    </div>
    {{-- QUICK ADD --}}
    @if ($allowCustomAmount)
        <div class="amount-buttons" id="presetAmountButtons">
            <button type="button" data-amount="500">₹500</button>
            <button type="button" data-amount="1000">₹1,000</button>
            <button type="button" data-amount="2500">₹2,500</button>
            <button type="button" data-amount="5000">₹5,000</button>
        </div>
    @endif
    @if (\App\Support\TreeDedication::collectsForSlug($cause))
        <div id="honoreeNamesBox" class="honoree-names-box">
            <p class="honoree-names-heading" id="honoreeNamesHeading">Name on this tree</p>
            <p class="help-text">
                Write the name you want on the tree nameplate. Leave blank if you do not want a name.
            </p>
            <div id="honoreeNamesList">
                <div class="honoree-name-field">
                    <input
                        type="text"
                        id="honoreeName0"
                        name="honoree_names[]"
                        maxlength="80"
                        autocomplete="name"
                        aria-labelledby="honoreeNamesHeading"
                        placeholder="Name on tree plate"
                    >
                </div>
            </div>
        </div>
    @endif
    
    {{-- DONOR DETAILS --}}
    <div class="row g-3 donation-details-card compact-gutter mt-2">
    
        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>Full Name <span class="text-danger">*</span></label>
                <input type="text" id="donorName" name="donor_name" title="Letters, spaces, and common punctuation (like - ' . ()) are allowed" required>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label for="donorDateOfBirth">Date of Birth</label>
                <input type="date" id="donorDateOfBirth" name="date_of_birth" max="{{ now()->toDateString() }}">
            </div>
        </div>

        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input type="email" id="donorEmail" name="donor_email" required>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>Mobile Number <span class="text-danger">*</span></label>
                <div class="phone-field-wrap">
                    <span class="phone-prefix" aria-hidden="true">
                        <span class="phone-flag">🇮🇳</span>
                        <span class="phone-code">+91</span>
                    </span>
                    <input
                        type="tel"
                        id="donorPhone"
                        name="donor_phone"
                        maxlength="10"
                        minlength="10"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        pattern="[0-9]{10}"
                        title="Please enter a valid 10-digit mobile number"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="form-group">
                <label>Address <span class="text-danger">*</span></label>
                <textarea id="donorAddress" name="address" rows="2" required></textarea>
            </div>
        </div>

        <div class="col-12" data-donor-guest-hint @if ($donorPortal['signed_in'] ?? false) hidden @endif>
            <p class="donor-signin-hint">
                Already donated with us?
                <button type="button" class="donor-signin-hint__btn" data-donor-signin>Sign in</button>
                to use your saved details.
            </p>
        </div>

        <div class="col-12">
            <p class="help-text mb-0">Entering Pincode will autofill City and State</p>
            <p id="pincodeFetchStatus" class="help-text mb-0 text-muted d-flex align-items-center gap-1" style="display: none;" aria-live="polite">
                <span id="pincodeFetchSpinner" class="spinner-border spinner-border-sm text-primary" style="display: none;" aria-hidden="true"></span>
                <span id="pincodeFetchStatusText"></span>
            </p>
        </div>

        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>Pincode <span class="text-danger">*</span></label>
                <input type="text" id="donorPincode" name="pincode" maxlength="6" pattern="[0-9]{6}" required>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>City <span class="text-danger">*</span></label>
                <input type="text" id="donorCity" name="city" required>
            </div>
        </div>

        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>State <span class="text-danger">*</span></label>
                <input type="text" id="donorState" name="state" required>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="form-group">
                <label>Country <span class="text-danger">*</span></label>
                <input type="text" id="donorCountryName" name="country" value="INDIA" required readonly>
            </div>
        </div>

        <div class="col-12 col-sm-6" id="panFieldWrap" @if (! $panCollectionEnabled) style="display: none;" @endif>
            {{-- PAN (INDIA ONLY) — always shown when collection enabled; required only at ₹1L+ FY total --}}
            <div class="form-group" id="panField">
                <label>
                    PAN Number
                    <span id="panRequiredMark" class="text-danger" style="display: none;">*</span>
                    <span id="panOptionalMark" class="text-muted fw-normal">(optional)</span>
                </label>
                <input type="text" name="pan_number" id="donorPan" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" placeholder="ABCDE1234F" style="text-transform: uppercase;" autocomplete="off">
                <small id="panRequirementHint" class="help-text text-muted" style="display: none;"></small>
            </div>
        </div>

        <div class="col-12">
            <p id="panNotice" class="text-danger fw-bold mb-2" @if (! $panCollectionEnabled) style="display: none;" @endif>Please note that if you do not provide your PAN Number, you will not be able to claim 50% tax exemption u/s 80G in India</p>
            <p class="secure-note text-start mb-2">Information is being collected to comply with government regulations and shall be treated as confidential.</p>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="consentIndianCitizen" name="consent_indian_citizen" required>
                <label class="form-check-label small" for="consentIndianCitizen">
                    I hereby declare that I am a citizen of India, making this donation out of my own funds.
                </label>
            </div>
            @if ($showRecurringOption)
                <div class="form-check mt-2" id="recurringConsentWrap" @if ($forceRecurring) style="display: block;" @else style="display: none;" @endif>
                    <input class="form-check-input" type="checkbox" value="1" id="consentRecurring" name="consent_recurring" @if ($forceRecurring) required @endif>
                    <label class="form-check-label small" for="consentRecurring">
                        {{ $branding['recurringMandateText'] }}
                    </label>
                </div>
            @endif
        </div>
    </div>

    <button type="submit" class="submit-donate" id="donationSubmitButton">
        {{ $ctaText }}
    </button>

    <div class="payment-trust-strip">
        <img src="{{ asset('images/payments/payment-logos.png') }}" alt="Visa, Mastercard, PhonePe, Google Pay, UPI" class="payment-logo" loading="lazy">
    </div>

    <p class="secure-note mb-0">
        100% Secure • 80G Tax Benefit (India only)
    </p>
</form>
