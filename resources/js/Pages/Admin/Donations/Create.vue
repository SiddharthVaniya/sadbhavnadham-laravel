<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import FormToggle from '@/Components/Admin/FormToggle.vue';
import { findDialOption, phoneDialCodes } from '@/utils/phoneDialCodes';

const page = usePage();

const props = defineProps({
    causes: { type: Array, default: () => [] },
    packages: { type: Array, default: () => [] },
    paymentProviders: { type: Array, default: () => [] },
    next_receipts: { type: Object, default: () => ({}) },
    next_receipt_number: { type: Number, default: 1 },
    next_receipt_formatted: { type: String, default: 'MSCT-OFF-1' },
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    donor_name: '',
    donor_email: '',
    donor_phone: '',
    phone_dial_code: '91',
    pan_number: '',
    address: '',
    pincode: '',
    city: '',
    state: '',
    country: 'INDIA',
    donor_country_code: 'IN',
    date_of_birth: '',
    donation_date: today,
    payment_provider: 'offline',
    total_amount: '',
    receipt_number: '',
    send_receipt_email: false,
    send_whatsapp_thank_you: false,
    send_whatsapp_certificate: false,
    cause_id: '',
    cause_package_id: '',
    item_title: '',
    quantity: 1,
});

const dialCodeOptions = phoneDialCodes;
const isIndiaDonor = computed(() => form.donor_country_code === 'IN');

const nextReceiptFormatted = computed(() => {
    const provider = form.payment_provider || 'offline';
    const fromMap = props.next_receipts?.[provider]?.formatted;

    if (fromMap) {
        return fromMap;
    }

    return props.next_receipt_formatted || 'MSCT-OFF-1';
});

const cityReadonly = ref(false);
const stateReadonly = ref(false);
const pincodeStatus = ref('');
const pincodeStatusTone = ref('muted');
const panRequired = ref(false);
const panRequirementHint = ref('');
const panManuallyEdited = ref(false);

let pincodeDebounceTimer = null;
let pincodeAbortController = null;
let panCheckTimer = null;
let panCheckAbortController = null;

const selectedCause = computed(() => props.causes.find((cause) => String(cause.id) === String(form.cause_id)));
const filteredPackages = computed(() => {
    if (!form.cause_id) {
        return [];
    }

    return props.packages.filter((pkg) => String(pkg.cause_id) === String(form.cause_id));
});
const selectedPackage = computed(() => filteredPackages.value.find((pkg) => String(pkg.id) === String(form.cause_package_id)));
const panCollectionEnabled = computed(() => {
    if (!form.cause_id) {
        return true;
    }

    return Boolean(selectedCause.value?.pan_required);
});
const canSendReceiptEmail = computed(() => String(form.donor_email).trim() !== '');
const hasPhone = computed(() => {
    const national = String(form.donor_phone).replace(/\D/g, '');

    if (form.phone_dial_code === '91') {
        return /^\d{10}$/.test(national);
    }

    return national.length >= 6 && national.length <= 15;
});

const onDialCountryChange = () => {
    const option = findDialOption(form.donor_country_code);
    form.donor_country_code = option.iso;
    form.phone_dial_code = option.dial;
    form.country = option.country;

    if (option.iso !== 'IN') {
        unlockLocationFields();
        setPincodeStatus('City/state auto-fill when you enter a valid postal code.', 'muted');
    }
};
const panFieldHint = computed(() => {
    if (panRequired.value) {
        return panRequirementHint.value || 'PAN is required for ₹1,00,000+ (single or FY total).';
    }

    return 'Optional. Required automatically when amount or FY total reaches ₹1,00,000.';
});

const csrfToken = () => page.props.csrf_token
    ?? document.querySelector('meta[name="csrf-token"]')?.content
    ?? '';

const unlockLocationFields = () => {
    cityReadonly.value = false;
    stateReadonly.value = false;
    form.city = '';
    form.state = '';
};

const setPincodeStatus = (message, tone = 'muted') => {
    pincodeStatus.value = message;
    pincodeStatusTone.value = tone;
};

const shouldLookupPostal = (postal) => {
    const compact = String(postal).replace(/\s+/g, '');

    if (isIndiaDonor.value) {
        return /^\d{6}$/.test(compact);
    }

    return compact.length >= 4;
};

const hydrateLocationByPincode = async (pincode) => {
    if (! shouldLookupPostal(pincode)) {
        return;
    }

    if (pincodeAbortController) {
        pincodeAbortController.abort();
    }
    pincodeAbortController = new AbortController();

    cityReadonly.value = true;
    stateReadonly.value = true;
    setPincodeStatus('Fetching city and state for your postal code...', 'loading');

    try {
        const response = await fetch('/admin/donations/postal-lookup', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                country_code: form.donor_country_code || 'IN',
                postal: pincode,
            }),
            signal: pincodeAbortController.signal,
        });

        if (response.status === 404) {
            unlockLocationFields();
            setPincodeStatus('Postal code not found. Please enter city and state manually.', 'danger');
            return;
        }

        if (response.status === 419) {
            unlockLocationFields();
            setPincodeStatus('Session expired. Refresh the page and try again.', 'danger');
            return;
        }

        if (! response.ok) {
            unlockLocationFields();
            setPincodeStatus('Could not fetch details right now. Please enter city and state manually.', 'danger');
            return;
        }

        const data = await response.json();

        if (! data?.found) {
            unlockLocationFields();
            setPincodeStatus('Postal code not found. Please enter city and state manually.', 'danger');
            return;
        }

        form.city = data.city || '';
        form.state = data.state || '';
        form.country = data.country || form.country;
        if (data.postal) {
            form.pincode = data.postal;
        }
        if (data.country_code) {
            form.donor_country_code = data.country_code;
        }
        cityReadonly.value = Boolean(form.city);
        stateReadonly.value = Boolean(form.state);
        setPincodeStatus('City and state auto-filled successfully.', 'success');
    } catch (error) {
        if (error.name !== 'AbortError') {
            unlockLocationFields();
            setPincodeStatus('Unable to fetch postal details. Please enter city and state manually.', 'danger');
        }
    }
};

const onPincodeInput = (value) => {
    let pincode = String(value || '').toUpperCase();

    if (isIndiaDonor.value) {
        pincode = pincode.replace(/\D/g, '').slice(0, 6);
    } else {
        pincode = pincode.replace(/[^A-Z0-9\s\-]/g, '').slice(0, 16);
    }

    form.pincode = pincode;
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

    if (shouldLookupPostal(pincode)) {
        pincodeDebounceTimer = setTimeout(() => {
            pincodeDebounceTimer = null;
            hydrateLocationByPincode(pincode);
        }, 350);
    }
};

const setPanRequirementState = (required, data = {}) => {
    panRequired.value = Boolean(required) && panCollectionEnabled.value;

    if (panRequired.value) {
        if (!panManuallyEdited.value && data.known_pan_number && !form.pan_number) {
            form.pan_number = data.known_pan_number;
        }

        if (Number(data.fy_paid_total || 0) > 0 && Number(data.current_amount || 0) < Number(data.threshold || 100000)) {
            const paid = Number(data.fy_paid_total || 0).toLocaleString('en-IN');
            panRequirementHint.value = `This donor has already donated ₹${paid} this financial year. PAN is required to continue.`;
        } else {
            panRequirementHint.value = '';
        }
    } else {
        panRequirementHint.value = '';
        if (!panManuallyEdited.value && data.known_pan_number && !form.pan_number) {
            form.pan_number = data.known_pan_number;
        }
    }
};

const syncPanRequirement = async () => {
    if (!panCollectionEnabled.value) {
        setPanRequirementState(false);
        return;
    }

    const currentAmount = Number(form.total_amount || 0);
    const threshold = 100000;
    const phone = String(form.donor_phone).replace(/\D/g, '');

    if (phone.length < 10) {
        setPanRequirementState(currentAmount >= threshold);
        return;
    }

    if (panCheckTimer) {
        clearTimeout(panCheckTimer);
    }

    panCheckTimer = setTimeout(async () => {
        if (panCheckAbortController) {
            panCheckAbortController.abort();
        }
        panCheckAbortController = new AbortController();

        try {
            const response = await fetch('/admin/donations/pan-requirement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    cause_id: form.cause_id || null,
                    total_amount: currentAmount > 0 ? currentAmount : null,
                    donor_email: form.donor_email || null,
                    donor_phone: phone,
                }),
                signal: panCheckAbortController.signal,
            });

            if (!response.ok) {
                setPanRequirementState(currentAmount >= threshold);
                return;
            }

            const data = await response.json();
            setPanRequirementState(Boolean(data.required), data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setPanRequirementState(currentAmount >= threshold);
            }
        }
    }, 300);
};

watch(
    () => form.cause_id,
    () => {
        if (form.cause_package_id && !filteredPackages.value.some((pkg) => String(pkg.id) === String(form.cause_package_id))) {
            form.cause_package_id = '';
        }
    },
);

watch(
    () => form.cause_package_id,
    (packageId) => {
        if (!packageId || !selectedPackage.value) {
            return;
        }

        const qty = Number(form.quantity) || 1;
        const unitAmount = Number(selectedPackage.value.amount ?? 0);
        form.total_amount = String(unitAmount * qty);
        if (!form.item_title) {
            form.item_title = selectedPackage.value.title ?? '';
        }
    },
);

watch(
    () => form.quantity,
    (qty) => {
        if (!selectedPackage.value) {
            return;
        }

        const unitAmount = Number(selectedPackage.value.amount ?? 0);
        form.total_amount = String(unitAmount * (Number(qty) || 1));
    },
);

watch(
    () => [form.cause_id, form.total_amount, form.donor_email, form.donor_phone],
    () => {
        syncPanRequirement();
    },
    { immediate: true },
);

watch(
    () => form.donor_email,
    (email) => {
        if (!String(email).trim()) {
            form.send_receipt_email = false;
        }
    },
);

watch(
    () => [form.donor_phone, form.phone_dial_code],
    () => {
        if (! hasPhone.value) {
            form.send_whatsapp_thank_you = false;
            form.send_whatsapp_certificate = false;
        }
    },
);

watch(
    () => form.cause_id,
    (causeId) => {
        if (!causeId) {
            form.send_whatsapp_thank_you = false;
            form.send_whatsapp_certificate = false;
        }
    },
);

watch(
    () => form.pan_number,
    (value, previous) => {
        const normalized = String(value).toUpperCase();
        if (normalized !== value) {
            form.pan_number = normalized;
            return;
        }

        if (previous && normalized !== previous) {
            panManuallyEdited.value = true;
        }
    },
);

const submit = () => form.post('/admin/donations');
</script>

<template>
    <Head title="Record Offline Donation" />
    <AdminLayout>
        <template #header>Donations</template>
        <PageHeader title="Record offline donation" />
        <form class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-none" @submit.prevent="submit">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                For Razorpay QR / incomplete payments, only <strong>Amount</strong> and <strong>Payment provider</strong> are required.
                Add donor details when you have them.
            </p>
            <div class="grid gap-4 md:grid-cols-3">
                <FormInput
                    v-model="form.donor_name"
                    label="Donor name"
                    :error="form.errors.donor_name"
                    hint="Optional. Saved as “Unknown Donor” if blank."
                />
                <FormInput
                    v-model="form.donor_email"
                    label="Donor email"
                    type="email"
                    :error="form.errors.donor_email"
                    hint="Optional. Required only if you send a receipt by email."
                />
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Donor phone</label>
                    <div class="flex gap-2">
                        <select
                            v-model="form.donor_country_code"
                            class="w-[11rem] shrink-0 rounded-lg border border-border px-2 py-2 text-sm"
                            @change="onDialCountryChange"
                        >
                            <option
                                v-for="option in dialCodeOptions"
                                :key="option.iso"
                                :value="option.iso"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <input
                            v-model="form.donor_phone"
                            type="tel"
                            class="min-w-0 flex-1 rounded-lg border border-border px-3 py-2 text-sm"
                            :placeholder="isIndiaDonor ? '10-digit mobile' : 'Local number'"
                            autocomplete="tel-national"
                        >
                    </div>
                    <p v-if="form.errors.donor_phone" class="mt-1 text-xs text-rose-600">{{ form.errors.donor_phone }}</p>
                    <p v-else class="mt-1 text-xs text-muted-foreground">
                        Optional. Select dial code for international numbers (e.g. +44).
                    </p>
                </div>
                <div>
                    <FormInput
                        :model-value="form.pincode"
                        :label="isIndiaDonor ? 'Pincode' : 'Postal / ZIP code'"
                        :error="form.errors.pincode"
                        :hint="isIndiaDonor ? '6-digit Indian PIN auto-fills city/state.' : 'Postal code auto-fills city/state/country when found (e.g. N1 9GU, 90210).'"
                        @update:model-value="onPincodeInput"
                    />
                    <p
                        v-if="pincodeStatus"
                        class="mt-1.5 text-xs"
                        :class="{
                            'text-muted-foreground': pincodeStatusTone === 'muted' || pincodeStatusTone === 'loading',
                            'text-emerald-600': pincodeStatusTone === 'success',
                            'text-rose-600': pincodeStatusTone === 'danger',
                        }"
                        aria-live="polite"
                    >
                        {{ pincodeStatus }}
                    </p>
                </div>
                <FormInput v-model="form.city" label="City" :error="form.errors.city" :readonly="cityReadonly" />
                <FormInput v-model="form.state" :label="isIndiaDonor ? 'State' : 'State / Region'" :error="form.errors.state" :readonly="stateReadonly" />
                <FormInput v-model="form.country" label="Country" :error="form.errors.country" />
                <FormInput
                    v-if="panCollectionEnabled"
                    v-model="form.pan_number"
                    :label="panRequired ? 'PAN' : 'PAN (optional)'"
                    :error="form.errors.pan_number"
                    :required="panRequired"
                    :hint="panFieldHint"
                />
                <FormDatePicker
                    v-model="form.date_of_birth"
                    label="Date of birth"
                    :error="form.errors.date_of_birth"
                    :max="today"
                    placeholder="DD MMM YYYY"
                />
                <FormDatePicker
                    v-model="form.donation_date"
                    label="Donation date"
                    :error="form.errors.donation_date"
                    :max="today"
                    hint="Defaults to today. Choose a past date for older entries."
                    placeholder="DD MMM YYYY"
                />
                <FormInput
                    v-model="form.receipt_number"
                    label="Receipt number"
                    type="number"
                    :error="form.errors.receipt_number"
                    :hint="`Leave blank to auto-assign. Next auto: ${nextReceiptFormatted}`"
                />
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">
                        Payment provider <span class="text-rose-600">*</span>
                    </label>
                    <select
                        v-model="form.payment_provider"
                        class="w-full rounded-lg border border-border px-3 py-2 text-sm"
                        required
                    >
                        <option
                            v-for="provider in paymentProviders"
                            :key="provider.value"
                            :value="provider.value"
                        >
                            {{ provider.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.payment_provider" class="mt-1 text-xs text-rose-600">{{ form.errors.payment_provider }}</p>
                    <p v-else class="mt-1 text-xs text-muted-foreground">Use Razorpay QR for UPI QR payments recorded manually.</p>
                </div>
                <FormInput v-model="form.total_amount" label="Amount" type="number" :error="form.errors.total_amount" required />
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Cause</label>
                    <select v-model="form.cause_id" class="w-full rounded-lg border border-border px-3 py-2 text-sm">
                        <option value="">None</option>
                        <option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.title }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Package</label>
                    <select
                        v-model="form.cause_package_id"
                        class="w-full rounded-lg border border-border px-3 py-2 text-sm"
                        :disabled="!form.cause_id"
                    >
                        <option value="">None</option>
                        <option v-for="pkg in filteredPackages" :key="pkg.id" :value="pkg.id">
                            {{ pkg.title }} (₹ {{ Number(pkg.amount || 0).toLocaleString('en-IN') }})
                        </option>
                    </select>
                    <p v-if="form.errors.cause_package_id" class="mt-1 text-xs text-rose-600">{{ form.errors.cause_package_id }}</p>
                    <p v-else-if="!form.cause_id" class="mt-1 text-xs text-muted-foreground">Select a cause first to choose a package.</p>
                </div>
                <FormInput
                    v-model="form.quantity"
                    label="Quantity"
                    type="number"
                    min="1"
                    :error="form.errors.quantity"
                    hint="Number of units (e.g. trees). Total = unit price × qty."
                />
                <FormInput v-model="form.item_title" label="Custom item title" :error="form.errors.item_title" />
            </div>
            <FormTextarea v-model="form.address" label="Address" :error="form.errors.address" />
            <div class="space-y-3 rounded-lg border border-border bg-muted/40 p-4">
                <p class="text-sm font-medium text-foreground">After save notifications</p>
                <FormToggle
                    v-model="form.send_receipt_email"
                    label="Send receipt email now"
                    :disabled="!canSendReceiptEmail"
                />
                <p v-if="!canSendReceiptEmail" class="text-xs text-muted-foreground">Add donor email to enable receipt email.</p>
                <FormToggle
                    v-model="form.send_whatsapp_thank_you"
                    label="Send thank-you WhatsApp now"
                    :disabled="!form.cause_id || !hasPhone"
                />
                <FormToggle
                    v-model="form.send_whatsapp_certificate"
                    label="Send certificate WhatsApp now"
                    :disabled="!form.cause_id || !hasPhone"
                />
                <p v-if="!form.cause_id" class="text-xs text-muted-foreground">Select a cause to enable WhatsApp messages.</p>
                <p v-else-if="!hasPhone" class="text-xs text-muted-foreground">
                    Add a valid phone (India: 10 digits) to enable WhatsApp messages.
                </p>
                <p class="text-xs text-muted-foreground">Google Sheet logging runs automatically for every manual donation.</p>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background" :disabled="form.processing">Save donation</button>
                <Link href="/admin/donations" class="rounded-lg border border-border px-4 py-2 text-sm">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
