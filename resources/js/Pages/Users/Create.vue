<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from '@/Components/UserForm.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
});

const handleSubmit = (form) => {
    form.post(route('users.store'), {
        preserveScroll: true,
    });
};

const handleCancel = () => {
    router.visit(route('users.index'));
};
</script>

<template>
    <Head title="Create User" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">Create New User</h1>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl space-y-4">
                <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                    <h2 class="text-base font-semibold">User Information</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Create a new user account and assign roles.
                    </p>
                </div>

                <UserForm
                    :roles="roles"
                    :is-edit="false"
                    @submit="handleSubmit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
