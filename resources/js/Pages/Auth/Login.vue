<script setup>
import Checkbox from "@/Components/Checkbox.vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import FadeIn from "@/Components/FadeIn.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <template #eyebrow>Welcome back</template>
        <template #title>Sign in to ServerVault</template>
        <template #description>
            Access your saved servers, credentials, and browser-based terminal
            sessions.
        </template>

        <FadeIn
            v-if="status"
            direction="down"
            :duration="0.4"
            class="mb-5 rounded-md border border-border bg-muted px-4 py-3 text-sm font-medium text-foreground"
        >
            {{ status }}
        </FadeIn>

        <form class="space-y-5" @submit.prevent="submit">
            <FadeIn :delay="0.1">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </FadeIn>

            <FadeIn :delay="0.2">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </FadeIn>

            <FadeIn
                :delay="0.3"
                class="flex items-center justify-between gap-4"
            >
                <label class="flex items-center cursor-pointer group">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span
                        class="ms-2 text-sm text-muted-foreground transition-colors duration-200 group-hover:text-foreground"
                        >Remember me</span
                    >
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-muted-foreground underline transition-all duration-200 hover:text-foreground hover:translate-x-0.5 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
                >
                    Reset password
                </Link>
            </FadeIn>

            <FadeIn :delay="0.4">
                <PrimaryButton
                    class="w-full justify-center transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    :class="{
                        'opacity-25 disabled:hover:scale-100': form.processing,
                    }"
                    :disabled="form.processing"
                >
                    <svg
                        v-if="form.processing"
                        class="me-2 h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                    {{ form.processing ? "Signing in..." : "Sign in" }}
                </PrimaryButton>
            </FadeIn>

            <FadeIn
                :delay="0.5"
                class="text-center text-sm text-muted-foreground"
            >
                Need a workspace?
                <Link
                    :href="route('register')"
                    class="font-medium text-foreground underline underline-offset-4 transition-all duration-200 hover:underline-offset-8"
                >
                    Create an account
                </Link>
            </FadeIn>
        </form>
    </GuestLayout>
</template>
