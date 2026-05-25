<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="min-h-screen bg-background text-foreground"
    >
        <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
            <section
                class="relative hidden overflow-hidden border-r border-border bg-card lg:flex lg:flex-col lg:justify-between"
            >
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.08]">
                    <div
                        class="h-full w-full bg-[linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] bg-[size:48px_48px] text-foreground"
                    ></div>
                </div>

                <div class="relative z-10 p-10">
                    <Link href="/" class="inline-flex items-center gap-3">
                        <ApplicationLogo class="h-11 w-11" />
                        <span class="text-xl font-semibold tracking-tight">
                            ServerVault
                        </span>
                    </Link>
                </div>

                <div class="relative z-10 mx-auto w-full max-w-xl px-10 pb-12">
                    <div class="mb-8 grid grid-cols-3 gap-3">
                        <div
                            class="rounded-lg border border-border bg-background/80 p-4 shadow-sm"
                        >
                            <div class="mb-3 h-2 w-10 rounded-full bg-primary"></div>
                            <div class="h-2 rounded-full bg-muted"></div>
                            <div class="mt-2 h-2 w-2/3 rounded-full bg-muted"></div>
                        </div>
                        <div
                            class="rounded-lg border border-border bg-background/80 p-4 shadow-sm"
                        >
                            <div class="mb-3 h-2 w-10 rounded-full bg-primary"></div>
                            <div class="h-2 rounded-full bg-muted"></div>
                            <div class="mt-2 h-2 w-2/3 rounded-full bg-muted"></div>
                        </div>
                        <div
                            class="rounded-lg border border-border bg-background/80 p-4 shadow-sm"
                        >
                            <div class="mb-3 h-2 w-10 rounded-full bg-primary"></div>
                            <div class="h-2 rounded-full bg-muted"></div>
                            <div class="mt-2 h-2 w-2/3 rounded-full bg-muted"></div>
                        </div>
                    </div>

                    <p class="text-sm font-medium uppercase text-muted-foreground">
                        Secure infrastructure access
                    </p>
                    <h1
                        class="mt-3 text-4xl font-semibold leading-tight tracking-normal text-foreground"
                    >
                        Manage servers, credentials, and terminal sessions from
                        one protected workspace.
                    </h1>
                    <div class="mt-8 grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="font-semibold text-foreground">Vault</p>
                            <p class="mt-1 text-muted-foreground">
                                Keep credentials organized.
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">Access</p>
                            <p class="mt-1 text-muted-foreground">
                                Launch sessions faster.
                            </p>
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">Audit</p>
                            <p class="mt-1 text-muted-foreground">
                                Track important activity.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <main
                class="flex min-h-screen flex-col px-6 py-6 sm:px-8 lg:px-12"
            >
                <div class="flex items-center justify-between">
                    <Link href="/" class="inline-flex items-center gap-3 lg:hidden">
                        <ApplicationLogo class="h-10 w-10" />
                        <span class="text-lg font-semibold tracking-tight">
                            ServerVault
                        </span>
                    </Link>

                    <div class="ms-auto">
                        <ThemeToggle />
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center py-10">
                    <div class="w-full max-w-md">
                        <div
                            v-if="
                                $slots.eyebrow ||
                                $slots.title ||
                                $slots.description
                            "
                            class="mb-8"
                        >
                            <p
                                v-if="$slots.eyebrow"
                                class="text-sm font-medium uppercase text-muted-foreground"
                            >
                                <slot name="eyebrow" />
                            </p>
                            <h1
                                v-if="$slots.title"
                                class="mt-2 text-3xl font-semibold tracking-normal text-foreground"
                            >
                                <slot name="title" />
                            </h1>
                            <p
                                v-if="$slots.description"
                                class="mt-3 text-sm leading-6 text-muted-foreground"
                            >
                                <slot name="description" />
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-border bg-card p-6 text-card-foreground shadow-sm"
                        >
                            <slot />
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
