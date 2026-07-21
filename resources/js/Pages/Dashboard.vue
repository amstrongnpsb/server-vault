<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { Card, CardContent, CardHeader } from "@/Components/ui/card";
import { useServerStore } from "@/stores/useServerStore";
import { onMounted, onUnmounted } from "vue";
import { Server, ServerOff } from "lucide-vue-next";

const props = defineProps({
    servers: Object,
});

const serverStore = useServerStore();

onMounted(() => {
    if (props.servers?.data) {
        serverStore.setServers(props.servers.data);
    }

    window.Echo.channel("servers").listen(".server.status.changed", (e) => {
        serverStore.updateStatus(e.id, e.status, e.last_checked_at);
    });
});

onUnmounted(() => {
    window.Echo.leaveChannel("servers");
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">
                Dashboard
            </h2>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <Card class="mb-6 max-w-md">
                <CardHeader class="font-semibold text-base">
                    Server status
                </CardHeader>
                <CardContent class="flex flex-col gap-1">
                    <div
                        class="flex items-center justify-between rounded-lg bg-muted px-3.5 py-2.5"
                    >
                        <div class="flex items-center gap-2.5">
                            <Server
                                class="h-4 w-4 text-teal-600 dark:text-teal-400"
                            />
                            <span class="text-sm text-muted-foreground"
                                >Online</span
                            >
                        </div>
                        <span class="text-base font-semibold text-foreground">
                            {{ serverStore.onlineCount }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between rounded-lg bg-muted px-3.5 py-2.5"
                    >
                        <div class="flex items-center gap-2.5">
                            <ServerOff
                                class="h-4 w-4 text-rose-600 dark:text-rose-400"
                            />
                            <span class="text-sm text-muted-foreground"
                                >Offline</span
                            >
                        </div>
                        <span class="text-base font-semibold text-foreground">
                            {{ serverStore.offlineCount }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <div
                class="overflow-hidden border border-border bg-card shadow-sm sm:rounded-lg"
            >
                <div class="p-6 text-card-foreground">You're logged in!</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
