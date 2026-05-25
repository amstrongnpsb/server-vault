<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Server" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="truncate text-lg font-semibold">Server</h1>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-md border border-border bg-card p-6 text-card-foreground shadow-sm">
                <h2 class="text-base font-semibold">Server Management</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Add servers, track connection details, and monitor server access from this page.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
