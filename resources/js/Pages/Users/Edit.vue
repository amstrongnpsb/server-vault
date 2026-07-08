<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserForm from '@/Components/UserForm.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const handleSubmit = (form) => {
    form.put(route('users.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('User updated successfully!');
        },
    });
};

const handleCancel = () => {
    router.visit(route('users.index'));
};
</script>

<template>
    <Head :title="`Edit User - ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">Edit User: {{ user.name }}</h1>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl space-y-4">
                <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                    <h2 class="text-base font-semibold">Update User Information</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Modify user details and role assignments.
                    </p>
                </div>

                <UserForm
                    :user="user"
                    :roles="roles"
                    :is-edit="true"
                    @submit="handleSubmit"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
