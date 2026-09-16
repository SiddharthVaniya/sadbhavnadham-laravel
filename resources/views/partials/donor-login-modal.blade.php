@php
    $donorPortal = $donorPortal ?? ['signed_in' => false, 'display_name' => null, 'profile' => null];
    $dialCodes = \App\Support\PhoneDialCodes::all();
@endphp

<div
    id="donorLoginModal"
    class="donor-login-modal"
    hidden
    data-donor-login-modal
    data-otp-send-url="{{ route('donate.otp.send') }}"
    data-otp-verify-url="{{ route('donate.otp.verify') }}"
    data-otp-logout-url="{{ route('donate.otp.logout') }}"
    data-otp-session-url="{{ route('donate.otp.session') }}"
    data-donor-session='@json($donorPortal)'
    data-dial-codes='@json($dialCodes)'
>
    <div class="donor-login-modal__backdrop" data-donor-login-close tabindex="-1"></div>

    <div
        class="donor-login-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="donorLoginTitle"
        aria-describedby="donorLoginDesc"
    >
        <button type="button" class="donor-login-modal__close" data-donor-login-close aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>

        <div class="donor-login-modal__header">
            <p class="donor-login-modal__eyebrow">Secure verification</p>
            <h2 id="donorLoginTitle">Welcome back</h2>
            <p id="donorLoginDesc" class="donor-login-modal__lead" data-donor-login-lead>
                Confirm the mobile number linked to your previous donation. We’ll send a one-time code to verify it’s you and restore your saved details.
            </p>
        </div>

        <div class="donor-login-modal__step" data-donor-login-step="phone">
            <div data-donor-login-phone-fields>
                <label class="donor-login-modal__label" for="donorLoginPhone">Mobile number</label>
                <div class="donor-login-modal__phone">
                    <div class="donor-login-cc" data-donor-cc>
                        <button
                            type="button"
                            class="donor-login-cc__btn"
                            data-donor-cc-toggle
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-label="Select country code"
                        >
                            <img
                                src="https://flagcdn.com/w40/in.png"
                                alt=""
                                width="20"
                                height="15"
                                class="donor-login-cc__flag"
                                data-donor-cc-flag
                            >
                            <span data-donor-cc-dial>+91</span>
                            <svg class="donor-login-cc__chevron" viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1.5 6 6.5 11 1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>

                        <div class="donor-login-cc__menu" data-donor-cc-menu hidden role="listbox" aria-label="Country codes">
                            <input
                                type="search"
                                class="donor-login-cc__search"
                                placeholder="Search country"
                                data-donor-cc-search
                                autocomplete="off"
                            >
                            <ul class="donor-login-cc__list" data-donor-cc-list></ul>
                        </div>
                    </div>

                    <input
                        id="donorLoginPhone"
                        type="tel"
                        inputmode="numeric"
                        maxlength="10"
                        autocomplete="tel-national"
                        placeholder="Mobile number"
                        data-donor-login-phone
                    >
                </div>
            </div>

            <div data-donor-login-email-fields hidden>
                <label class="donor-login-modal__label" for="donorLoginEmail">Email address</label>
                <input
                    id="donorLoginEmail"
                    type="email"
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="donor-login-modal__email"
                    data-donor-login-email
                >
            </div>

            <button type="button" class="donor-login-modal__primary" data-donor-login-send>
                Send verification code
            </button>

            <button type="button" class="donor-login-modal__alt" data-donor-login-switch>
                <svg class="donor-login-modal__alt-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none">
                    <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="1.8"/>
                    <path d="m5.5 7.5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span data-donor-login-switch-label>Continue with email</span>
            </button>
        </div>

        <div class="donor-login-modal__step" data-donor-login-step="otp" hidden>
            <p class="donor-login-modal__sent" data-donor-login-sent aria-live="polite"></p>
            <label class="donor-login-modal__label" for="donorLoginOtp">Verification code</label>
            <input
                id="donorLoginOtp"
                type="text"
                inputmode="numeric"
                maxlength="6"
                autocomplete="one-time-code"
                placeholder="Enter 6-digit code"
                class="donor-login-modal__otp"
                data-donor-login-otp
            >
            <button type="button" class="donor-login-modal__primary" data-donor-login-verify disabled>
                Verify and continue
            </button>
            <button type="button" class="donor-login-modal__secondary" data-donor-login-resend disabled>
                Resend code
            </button>
            <button type="button" class="donor-login-modal__link" data-donor-login-change-phone>
                Use a different account
            </button>
        </div>

        <p class="donor-login-modal__status" data-donor-login-status aria-live="polite"></p>

        <p class="donor-login-modal__foot">
            First-time donor? Continue on the form — signing in is optional.
        </p>
    </div>
</div>
