<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="User" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">User</h1>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                <h2 class="text-base font-semibold">User Management</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Manage application users, roles, and access from this page.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
