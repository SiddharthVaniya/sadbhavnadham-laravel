<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';

const props = defineProps({
    department: { type: Object, default: null },
    isEdit: { type: Boolean, default: false },
});

const form = useForm({
    name: props.department?.name ?? '',
    slug: props.department?.slug ?? '',
    description: props.department?.description ?? '',
    is_active: props.department?.is_active ?? true,
    sort_order: props.department?.sort_order ?? 0,
});

const submit = () => {
    if (props.isEdit) {
        form.put(`/admin/departments/${props.department.id}`);
    } else {
        form.post('/admin/departments');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Department' : 'Create Department'" />
    <AdminLayout>
        <template #header>Departments</template>
        <PageHeader :title="isEdit ? 'Edit department' : 'Create department'" />

        <form class="w-full pb-24" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Department details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="department-name">Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="department-name"
                                v-model="form.name"
                                required
                                :aria-invalid="Boolean(form.errors.name)"
                            />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="department-slug">Slug</Label>
                            <Input
                                id="department-slug"
                                v-model="form.slug"
                                placeholder="Auto-generated from name if left blank"
                                :aria-invalid="Boolean(form.errors.slug)"
                            />
                            <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="department-description">Description</Label>
                            <textarea
                                id="department-description"
                                v-model="form.description"
                                rows="3"
                                class="admin-input min-h-24"
                            />
                            <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="department-sort-order">Sort order</Label>
                            <Input
                                id="department-sort-order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                                :aria-invalid="Boolean(form.errors.sort_order)"
                            />
                            <p v-if="form.errors.sort_order" class="text-xs text-destructive">{{ form.errors.sort_order }}</p>
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
                                <Checkbox
                                    :model-value="form.is_active"
                                    @update:model-value="(checked) => form.is_active = checked === true"
                                />
                                Active department
                            </label>
                        </div>
                    </div>
                </CardContent>
                <CardFooter class="justify-end gap-2 border-t">
                    <Button as-child variant="outline">
                        <Link href="/admin/departments">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save changes' }}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </AdminLayout>
</template>
