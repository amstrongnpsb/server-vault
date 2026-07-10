<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import { Toaster } from "@/Components/ui/sonner";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from "@/Components/ui/sidebar";
import { Link } from "@inertiajs/vue3";
import { LayoutDashboard, Server, Users } from "lucide-vue-next";
import { useTheme } from "@/composables/useTheme";
import { onMounted, Teleport, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { toast } from "vue-sonner";

const page = usePage();

const menuItems = [
    {
        title: "Dashboard",
        routeName: "dashboard",
        icon: LayoutDashboard,
        activePattern: "dashboard",
    },
    {
        title: "User",
        routeName: "users.index",
        icon: Users,
        activePattern: "users.*",
    },
    {
        title: "Server",
        routeName: "servers.index",
        icon: Server,
        activePattern: "servers.*",
    },
];

// Get current theme for toast notifications
const { currentTheme, initTheme } = useTheme();

// Initialize theme on mount
onMounted(() => {
    initTheme();
});
</script>

<template>
    <Toaster
        :theme="currentTheme"
        position="top-right"
        :close-button="true"
        :rich-colors="true"
        :style="{ '--z-index': 9999 }"
        :toast-options="{
            classes: {
                toast: 'rounded-md shadow-md p-4 w-[356px]',
                title: 'font-medium',
            },
        }"
    />
    <SidebarProvider>
        <Sidebar>
            <SidebarHeader>
                <Link
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-3 rounded-md px-2 py-1.5 transition hover:bg-muted"
                >
                    <ApplicationLogo class="h-8 w-8 shrink-0" />
                    <div
                        class="min-w-0 group-data-[state=collapsed]/sidebar-wrapper:hidden"
                    >
                        <div class="truncate text-sm font-semibold">
                            ServerVault
                        </div>
                        <div class="truncate text-xs text-muted-foreground">
                            Admin Panel
                        </div>
                    </div>
                </Link>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel>Navigation</SidebarGroupLabel>

                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in menuItems"
                            :key="item.title"
                        >
                            <SidebarMenuButton
                                :href="route(item.routeName)"
                                :is-active="route().current(item.activePattern)"
                                :tooltip="item.title"
                            >
                                <component
                                    :is="item.icon"
                                    class="h-5 w-5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span
                                    class="truncate group-data-[state=collapsed]/sidebar-wrapper:hidden"
                                >
                                    {{ item.title }}
                                </span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <div class="flex items-center gap-3 rounded-md px-2 py-1.5">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-semibold text-primary-foreground"
                    >
                        {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div
                        class="min-w-0 group-data-[state=collapsed]/sidebar-wrapper:hidden"
                    >
                        <div class="truncate text-sm font-medium">
                            {{ $page.props.auth.user.name }}
                        </div>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ $page.props.auth.user.email }}
                        </div>
                    </div>
                </div>
            </SidebarFooter>
        </Sidebar>

        <SidebarInset>
            <header
                class="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur"
            >
                <div
                    class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 md:px-8"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <SidebarTrigger />

                        <div class="min-w-0 pl-1">
                            <slot name="header">
                                <h1 class="truncate text-lg font-semibold">
                                    Dashboard
                                </h1>
                            </slot>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <ThemeToggle />

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex h-9 items-center gap-2 rounded-md border border-border bg-card px-2 text-sm transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-semibold text-primary-foreground"
                                    >
                                        {{
                                            $page.props.auth.user.name
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </span>
                                    <span
                                        class="hidden max-w-36 truncate font-medium sm:block"
                                    >
                                        {{ $page.props.auth.user.name }}
                                    </span>
                                </button>
                            </template>

                            <template #content>
                                <div class="border-b border-border px-4 py-3">
                                    <div class="truncate text-sm font-medium">
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>
                                <DropdownLink :href="route('profile.edit')">
                                    Profile
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    Log Out
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main>
                <slot />
            </main>
        </SidebarInset>
    </SidebarProvider>
</template>
