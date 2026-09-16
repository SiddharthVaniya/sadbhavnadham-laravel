/**
 * Next.js checkout helper for Sadbhavna donations.
 *
 * Public pages live on sadbhavnadham.org (Next.js). Payment lives on
 * donate.sadbhavnadham.org (Laravel). Browsers will NOT send cookies between
 * those hosts, so first-touch attribution must be:
 *
 *   1. Captured on the Next.js origin (sessionStorage + localStorage, 30 days)
 *   2. Copied into the JSON body of POST /api/next-razorpay
 *
 * In app/layout.tsx (or _app):
 *
 *   import Script from 'next/script';
 *
 *   <Script
 *     src="https://donate.sadbhavnadham.org/js/donation-attribution.js"
 *     strategy="afterInteractive"
 *   />
 *
 * Call capture() again on App Router navigations if the script is not reloaded:
 *
 *   usePathname();
 *   useEffect(() => { window.SadbhavnaAttribution?.capture(); }, [pathname]);
 *
 * When starting Razorpay checkout:
 *
 *   const body = withAttribution({
 *     cause: 'tree-plantation',
 *     amount: 3000,
 *     quantity: 2,
 *     honoree_names: ['Ramesh Patel', 'Sita Devi'],
 *     donor_name, donor_email, donor_phone,
 *     address, pincode, city, state, country: 'INDIA',
 *     consent_indian_citizen: true,
 *   });
 *
 *   await fetch('https://donate.sadbhavnadham.org/api/next-razorpay', {
 *     method: 'POST',
 *     headers: {
 *       'Content-Type': 'application/json',
 *       Accept: 'application/json',
 *       'X-WP-TOKEN': process.env.NEXT_PUBLIC_DONATE_API_TOKEN,
 *     },
 *     body: JSON.stringify(body),
 *   });
 */
export function withAttribution(payload) {
    const fields =
        typeof window !== 'undefined' && window.SadbhavnaAttribution?.checkoutFields
            ? window.SadbhavnaAttribution.checkoutFields()
            : {};

    return {
        source_channel: payload.source_channel || 'web',
        ...payload,
        ...fields,
    };
}
