<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import FloatingCard from "@/Components/FloatingCard.vue";
import FadeIn from "@/Components/FadeIn.vue";
import { Link } from "@inertiajs/vue3";

const serverCards = [
    { delay: 0.1, duration: 3, distance: 6 },
    { delay: 0.2, duration: 3.5, distance: 8 },
    { delay: 0.3, duration: 4, distance: 5 },
];

const features = [
    { title: "Vault", description: "Keep credentials organized.", delay: 0.6 },
    { title: "Access", description: "Launch sessions faster.", delay: 0.7 },
    { title: "Audit", description: "Track important activity.", delay: 0.8 },
];
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
            <section
                class="relative hidden overflow-hidden border-r border-border bg-card lg:flex lg:flex-col lg:justify-between"
            >
                <div
                    class="absolute inset-0 opacity-[0.06] dark:opacity-[0.08]"
                >
                    <div
                        class="h-full w-full bg-[linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] bg-[size:48px_48px] text-foreground"
                    ></div>
                </div>

                <FadeIn direction="down" :delay="0" class="relative z-10 p-10">
                    <Link
                        href="/"
                        class="inline-flex items-center gap-3 transition-all duration-300 hover:scale-105"
                    >
                        <ApplicationLogo class="h-11 w-11" />
                        <span class="text-xl font-semibold tracking-tight">
                            ServerVault
                        </span>
                    </Link>
                </FadeIn>

                <div class="relative z-10 mx-auto w-full max-w-xl px-10 pb-12">
                    <div class="mb-8 grid grid-cols-3 gap-3">
                        <FloatingCard
                            v-for="(card, index) in serverCards"
                            :key="index"
                            :delay="card.delay"
                            :float-duration="card.duration"
                            :float-distance="card.distance"
                        >
                            <div class="mb-3 h-2 w-10 rounded-full bg-primary"></div>
                            <div class="h-2 rounded-full bg-muted"></div>
                            <div class="mt-2 h-2 w-2/3 rounded-full bg-muted"></div>
                        </FloatingCard>
                    </div>

                    <FadeIn
                        tag="p"
                        :delay="0.4"
                        direction="none"
                        class="text-sm font-medium uppercase text-muted-foreground"
                    >
                        Secure infrastructure access
                    </FadeIn>

                    <FadeIn
                        tag="h1"
                        :delay="0.5"
                        direction="none"
                        class="mt-3 text-4xl font-semibold leading-tight tracking-normal text-foreground"
                    >
                        Manage servers, credentials, and terminal sessions from
                        one protected workspace.
                    </FadeIn>

                    <div class="mt-8 grid grid-cols-3 gap-4 text-sm">
                        <FadeIn
                            v-for="feature in features"
                            :key="feature.title"
                            :delay="feature.delay"
                            direction="none"
                        >
                            <p class="font-semibold text-foreground">{{ feature.title }}</p>
                            <p class="mt-1 text-muted-foreground">{{ feature.description }}</p>
                        </FadeIn>
                    </div>
                </div>
            </section>

            <main class="flex min-h-screen flex-col px-6 py-6 sm:px-8 lg:px-12">
                <div class="flex items-center justify-between">
                    <Link
                        href="/"
                        class="inline-flex items-center gap-3 lg:hidden"
                    >
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
