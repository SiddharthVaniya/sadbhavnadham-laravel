<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormSection from '@/Components/Admin/FormSection.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Alert, AlertDescription } from '@/Components/ui/alert';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    permissionGroups: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
    currentYearMonth: { type: String, default: '' },
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    referral_code: props.user?.referral_code ?? '',
    donation_target: props.user?.donation_target ?? '',
    monthly_target_amount: props.user?.monthly_target_amount ?? '',
    monthly_spend_amount: props.user?.monthly_spend_amount ?? '',
    department_id: props.user?.department_id ?? '',
    password: '',
    roles: props.user?.roles ?? [],
    permissions: props.user?.direct_permissions ?? [],
});

const showPassword = ref(false);
const passwordType = computed(() => (showPassword.value ? 'text' : 'password'));

const submit = () => {
    if (props.isEdit) {
        form.put(`/admin/users/${props.user.id}`);
    } else {
        form.post('/admin/users');
    }
};

const toggleRole = (roleName, checked) => {
    if (checked) {
        if (! form.roles.includes(roleName)) {
            form.roles.push(roleName);
        }

        return;
    }

    form.roles = form.roles.filter((role) => role !== roleName);
};

const togglePermission = (name) => {
    if (form.permissions.includes(name)) {
        form.permissions = form.permissions.filter((permission) => permission !== name);
    } else {
        form.permissions.push(name);
    }
};

const isPermissionChecked = (name) => form.permissions.includes(name);
</script>

<template>
    <Head :title="isEdit ? 'Edit User' : 'Create User'" />
    <AdminLayout>
        <template #header>Users</template>
        <PageHeader
            :title="isEdit ? 'Edit user' : 'Create user'"
            subtitle="Assign roles for shared access, then add user-only permissions when someone needs extra or limited access beyond their role."
        />

        <form class="w-full space-y-6 pb-24" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>User account</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div class="space-y-2">
                            <Label for="user-name">Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="user-name"
                                v-model="form.name"
                                required
                                :aria-invalid="Boolean(form.errors.name)"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-email">Email <span class="text-destructive">*</span></Label>
                            <Input
                                id="user-email"
                                v-model="form.email"
                                type="email"
                                required
                                :aria-invalid="Boolean(form.errors.email)"
                            />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-department">Department</Label>
                            <select
                                id="user-department"
                                v-model="form.department_id"
                                class="admin-input"
                            >
                                <option value="">No department</option>
                                <option
                                    v-for="option in departmentOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p v-if="form.errors.department_id" class="text-xs text-destructive">
                                {{ form.errors.department_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-referral-code">Referral code</Label>
                            <Input
                                id="user-referral-code"
                                v-model="form.referral_code"
                                placeholder="e.g. paz"
                                autocomplete="off"
                                :aria-invalid="Boolean(form.errors.referral_code)"
                                @blur="form.referral_code = String(form.referral_code || '').trim().toLowerCase()"
                            />
                            <p class="text-xs text-muted-foreground">
                                Used in share links like /paz/donate/old-age-home so donations can be tracked to this user.
                            </p>
                            <p v-if="form.errors.referral_code" class="text-xs text-destructive">{{ form.errors.referral_code }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-donation-target">Lifetime target (₹)</Label>
                            <Input
                                id="user-donation-target"
                                v-model="form.donation_target"
                                type="number"
                                min="0"
                                max="1000000"
                                step="1"
                                placeholder="e.g. 20000"
                                :aria-invalid="Boolean(form.errors.donation_target)"
                            />
                            <p class="text-xs text-muted-foreground">
                                Lifetime rupee goal. Used when no this-month target is set.
                            </p>
                            <p v-if="form.errors.donation_target" class="text-xs text-destructive">{{ form.errors.donation_target }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-monthly-target">This month target (₹)</Label>
                            <Input
                                id="user-monthly-target"
                                v-model="form.monthly_target_amount"
                                type="number"
                                min="0"
                                max="10000000"
                                step="1"
                                placeholder="e.g. 50000"
                                :aria-invalid="Boolean(form.errors.monthly_target_amount)"
                            />
                            <p class="text-xs text-muted-foreground">
                                Collection target for {{ user?.monthly_budget_year_month || currentYearMonth || 'this month' }}.
                            </p>
                            <p v-if="form.errors.monthly_target_amount" class="text-xs text-destructive">{{ form.errors.monthly_target_amount }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-monthly-spend">This month spend (₹)</Label>
                            <Input
                                id="user-monthly-spend"
                                v-model="form.monthly_spend_amount"
                                type="number"
                                min="0"
                                max="10000000"
                                step="0.01"
                                placeholder="e.g. 12000"
                                :aria-invalid="Boolean(form.errors.monthly_spend_amount)"
                            />
                            <p class="text-xs text-muted-foreground">
                                Marketer ad spend for the current month.
                            </p>
                            <p v-if="form.errors.monthly_spend_amount" class="text-xs text-destructive">{{ form.errors.monthly_spend_amount }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="user-password">
                                Password
                                <span v-if="! isEdit" class="text-destructive">*</span>
                            </Label>
                            <div class="relative">
                                <Input
                                    id="user-password"
                                    v-model="form.password"
                                    :type="passwordType"
                                    class="pr-10"
                                    :required="! isEdit"
                                    :aria-invalid="Boolean(form.errors.password)"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="absolute inset-y-0 right-1 my-auto"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = ! showPassword"
                                >
                                    <EyeOff v-if="showPassword" />
                                    <Eye v-else />
                                </Button>
                            </div>
                            <p v-if="isEdit" class="text-xs text-muted-foreground">Leave blank to keep current password</p>
                            <p v-if="form.errors.password" class="text-xs text-destructive">{{ form.errors.password }}</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Label>Roles</Label>
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="role in roles"
                                :key="role.id"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="form.roles.includes(role.name)"
                                    @update:model-value="(checked) => toggleRole(role.name, checked === true)"
                                />
                                {{ role.name }}
                            </label>
                        </div>
                        <Alert v-if="form.errors.roles" variant="destructive">
                            <AlertDescription>{{ form.errors.roles }}</AlertDescription>
                        </Alert>
                    </div>
                </CardContent>
            </Card>

            <FormSection
                id="user-permissions"
                title="User-only permissions"
                subtitle="Extra access for this person only. These add on top of role permissions — useful for one-off cases like link-only access or a single module."
            >
                <Alert v-if="form.errors.permissions" variant="destructive" class="mb-4">
                    <AlertDescription>{{ form.errors.permissions }}</AlertDescription>
                </Alert>

                <div
                    v-for="group in permissionGroups"
                    :key="group.key"
                    class="mb-6 last:mb-0"
                >
                    <div class="mb-3">
                        <h3 class="text-sm font-semibold">{{ group.label }}</h3>
                        <p class="text-xs text-muted-foreground">{{ group.description }}</p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <label
                            v-for="permission in group.permissions"
                            :key="permission.name"
                            class="flex items-start gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                            :class="permission.name === 'view all donations' ? 'border-amber-300 bg-amber-50/60' : ''"
                        >
                            <input
                                type="checkbox"
                                class="mt-0.5"
                                :checked="isPermissionChecked(permission.name)"
                                @change="togglePermission(permission.name)"
                            >
                            <span>
                                <span class="font-medium text-foreground">{{ permission.label }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">{{ permission.help }}</span>
                                <span class="mt-1 block font-mono text-[11px] text-muted-foreground">{{ permission.name }}</span>
                            </span>
                        </label>
                    </div>
                </div>
            </FormSection>

            <div class="flex justify-end gap-2">
                <Button as-child variant="outline">
                    <Link href="/admin/users">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </Button>
            </div>
        </form>
    </AdminLayout>
</template>
