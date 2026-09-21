<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormInput from '@/Components/Admin/FormInput.vue';
import FormDatePicker from '@/Components/Admin/FormDatePicker.vue';
import FormTextarea from '@/Components/Admin/FormTextarea.vue';
import { findDialOption, phoneDialCodes, splitPhoneNumber } from '@/utils/phoneDialCodes';

const page = usePage();

const props = defineProps({
    donation: { type: Object, required: true },
    causes: { type: Array, default: () => [] },
    packages: { type: Array, default: () => [] },
});

const initialPhone = splitPhoneNumber(
    props.donation.donor_phone,
    props.donation.donor_country_code ?? 'IN',
);

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    donor_name: props.donation.donor_name ?? '',
    donor_email: props.donation.donor_email ?? '',
    donor_phone: initialPhone.national,
    phone_dial_code: initialPhone.dial,
    pan_number: props.donation.pan_number ?? '',
    address: props.donation.address ?? '',
    pincode: props.donation.pincode ?? '',
    city: props.donation.city ?? '',
    state: props.donation.state ?? '',
    country: props.donation.country ?? 'INDIA',
    donor_country_code: initialPhone.iso || (props.donation.donor_country_code ?? 'IN'),
    date_of_birth: props.donation.date_of_birth ?? '',
    donation_date: props.donation.donation_date ?? '',
    total_amount: props.donation.total_amount ?? '',
    cause_id: props.donation.cause_id ?? '',
    cause_package_id: props.donation.cause_package_id ?? '',
    item_title: props.donation.item_title ?? '',
    quantity: props.donation.quantity ?? 1,
});

const dialCodeOptions = phoneDialCodes;
const isIndiaDonor = computed(() => form.donor_country_code === 'IN');

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
const isOffline = computed(() => Boolean(props.donation.is_offline));
const canEditDonorDetails = computed(() => Boolean(props.donation.can_edit_donor_details ?? props.donation.is_offline));
const canEditAmount = computed(() => Boolean(props.donation.can_edit_amount ?? props.donation.is_offline));
const isQrDonation = computed(() => props.donation.provider === 'razorpay_qr');
const pageTitle = computed(() => {
    if (isOffline.value) {
        return 'Edit offline donation';
    }

    if (isQrDonation.value) {
        return 'Complete QR donation details';
    }

    if (canEditDonorDetails.value) {
        return 'Edit donation details';
    }

    return 'Edit donation cause';
});
const filteredPackages = computed(() => {
    if (!form.cause_id) {
        return [];
    }

    return props.packages.filter((pkg) => String(pkg.cause_id) === String(form.cause_id));
});
const selectedPackage = computed(() => filteredPackages.value.find((pkg) => String(pkg.id) === String(form.cause_package_id)));
const panCollectionEnabled = computed(() => Boolean(selectedCause.value?.pan_required) || canEditDonorDetails.value);
const showPanField = computed(() => canEditDonorDetails.value);
const panFieldHint = computed(() => {
    if (panRequirementHint.value) {
        return panRequirementHint.value;
    }

    if (!panRequired.value) {
        return 'Optional. Enter the donor PAN card number if available.';
    }

    return 'PAN is required for ₹1,00,000+ (single or FY total).';
});
const formatMoney = (amount) => `₹ ${Number(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const csrfToken = () => page.props.csrf_token
    ?? document.querySelector('meta[name="csrf-token"]')?.content
    ?? '';

const unlockLocationFields = () => {
    cityReadonly.value = false;
    stateReadonly.value = false;
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

const setPanVisibility = (required, data = {}) => {
    panRequired.value = required;

    if (required) {
        if (!panManuallyEdited.value && data.known_pan_number && !form.pan_number) {
            form.pan_number = data.known_pan_number;
        }

        if (Number(data.fy_paid_total || 0) > 0) {
            const paid = Number(data.fy_paid_total || 0).toLocaleString('en-IN');
            panRequirementHint.value = `This donor has already donated ₹${paid} this financial year. PAN is required to continue.`;
        } else {
            panRequirementHint.value = '';
        }
    } else {
        panRequirementHint.value = '';
        panManuallyEdited.value = false;
    }
};

const syncPanRequirement = async () => {
    if (!canEditDonorDetails.value) {
        setPanVisibility(false);
        return;
    }

    if (!panCollectionEnabled.value && !isQrDonation.value) {
        setPanVisibility(false);
        return;
    }

    const currentAmount = Number(form.total_amount || 0);
    const threshold = 100000;
    const phone = String(form.donor_phone).replace(/\D/g, '');

    if (phone.length < 10) {
        setPanVisibility(currentAmount >= threshold);
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
                setPanVisibility(currentAmount >= threshold);
                return;
            }

            const data = await response.json();
            setPanVisibility(Boolean(data.required), data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setPanVisibility(currentAmount >= threshold);
            }
        }
    }, 300);
};

watch(() => form.cause_id, () => {
    if (form.cause_package_id && !filteredPackages.value.some((pkg) => String(pkg.id) === String(form.cause_package_id))) {
        form.cause_package_id = '';
    }
});

watch(() => form.cause_package_id, (packageId) => {
    if (!packageId || !selectedPackage.value || !canEditAmount.value) {
        return;
    }

    form.total_amount = String(selectedPackage.value.amount ?? '');
    if (!form.item_title) {
        form.item_title = selectedPackage.value.title ?? '';
    }
});

watch(
    () => [form.cause_id, form.total_amount, form.donor_email, form.donor_phone],
    () => {
        syncPanRequirement();
    },
    { immediate: true },
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

const submit = () => form.put(`/admin/donations/${props.donation.uuid}`);
</script>

<template>
    <Head :title="pageTitle" />
    <AdminLayout>
        <template #header>Donations</template>
        <PageHeader :title="pageTitle" :subtitle="donation.receipt_number ? `Receipt ${donation.receipt_number}` : ''" />
        <form class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-none" @submit.prevent="submit">
            <p
                v-if="isQrDonation"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
                Fill donor and cause details here. Amount, payment ID, and receipt stay the same.
                After saving, use Resend on the donation details page if you need to send the receipt.
            </p>
            <p
                v-else-if="canEditDonorDetails && !canEditAmount"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
                You can update donor and cause details here. Amount, payment ID, and receipt stay the same.
                After saving, use Resend on the donation details page if you need to send the receipt again.
            </p>
            <p
                v-else-if="!canEditDonorDetails"
                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
            >
                Only cause and package can be changed for online donations. Amount, payment ID, and receipt stay the same.
            </p>
            <p
                v-if="donation.item_count > 1"
                class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-foreground"
            >
                This donation has {{ donation.item_count }} items. Editing updates the primary (first) item only.
            </p>
            <div class="grid gap-4 md:grid-cols-3">
                <template v-if="canEditDonorDetails">
                    <FormInput v-model="form.donor_name" label="Donor name" :error="form.errors.donor_name" />
                    <FormInput v-model="form.donor_email" label="Donor email" type="email" :error="form.errors.donor_email" />
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
                    </div>
                    <div>
                        <FormInput
                            :model-value="form.pincode"
                            :label="isIndiaDonor ? 'Pincode' : 'Postal / ZIP code'"
                            :error="form.errors.pincode"
                            :hint="isIndiaDonor ? '6-digit Indian PIN auto-fills city/state.' : 'Postal code auto-fills city/state/country when found.'"
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
                        v-if="showPanField"
                        v-model="form.pan_number"
                        :label="panRequired ? 'PAN card number' : 'PAN card number (optional)'"
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
                        v-if="canEditAmount"
                        v-model="form.donation_date"
                        label="Donation date"
                        :error="form.errors.donation_date"
                        :max="today"
                        placeholder="DD MMM YYYY"
                    />
                    <FormInput
                        v-if="canEditAmount"
                        v-model="form.total_amount"
                        label="Amount"
                        type="number"
                        :error="form.errors.total_amount"
                        required
                    />
                    <div v-else class="rounded-lg border border-border bg-muted/40 px-3 py-2">
                        <p class="text-xs font-medium text-muted-foreground">Amount</p>
                        <p class="mt-1 text-sm font-medium text-foreground">{{ formatMoney(donation.total_amount) }}</p>
                    </div>
                </template>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">Cause</label>
                    <select v-model="form.cause_id" class="w-full rounded-lg border border-border px-3 py-2 text-sm" :required="!isOffline">
                        <option value="">{{ isOffline ? 'None' : 'Select cause' }}</option>
                        <option v-for="cause in causes" :key="cause.id" :value="cause.id">{{ cause.title }}</option>
                    </select>
                    <p v-if="form.errors.cause_id" class="mt-1 text-xs text-rose-600">{{ form.errors.cause_id }}</p>
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
                </div>
                <FormInput
                    v-if="canEditDonorDetails"
                    v-model="form.quantity"
                    label="Quantity"
                    type="number"
                    min="1"
                    :error="form.errors.quantity"
                    hint="Number of units (e.g. trees / bags). Paid amount stays the same."
                />
                <FormInput v-if="canEditDonorDetails" v-model="form.item_title" label="Custom item title" :error="form.errors.item_title" />
            </div>
            <FormTextarea v-if="canEditDonorDetails" v-model="form.address" label="Address" :error="form.errors.address" />
            <div v-else-if="form.address" class="rounded-lg border border-border bg-muted/40 px-3 py-2">
                <p class="text-xs font-medium text-muted-foreground">Address</p>
                <p class="mt-1 text-sm text-foreground">{{ form.address }}</p>
            </div>
            <p class="text-xs text-muted-foreground">
                Receipt number stays the same. Google Sheet row for this donation will be updated in place.
                Use Resend on the donation details page if you need to email the receipt again.
            </p>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background" :disabled="form.processing">Save changes</button>
                <Link
                    :href="isOffline ? '/admin/donations/offline' : `/admin/donations/${donation.uuid}`"
                    class="rounded-lg border border-border px-4 py-2 text-sm"
                >
                    Cancel
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
