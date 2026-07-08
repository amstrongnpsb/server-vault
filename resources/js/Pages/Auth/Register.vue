<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FadeIn from '@/Components/FadeIn.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <template #eyebrow>Create workspace</template>
        <template #title>Start securing your servers</template>
        <template #description>
            Create your ServerVault account and prepare a private place for
            infrastructure access.
        </template>

        <form class="space-y-5" @submit.prevent="submit">
            <FadeIn :delay="0.1">
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </FadeIn>

            <FadeIn :delay="0.2">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="you@example.com"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </FadeIn>

            <FadeIn :delay="0.3">
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Create a strong password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </FadeIn>

            <FadeIn :delay="0.4">
                <InputLabel
                    for="password_confirmation"
                    value="Confirm Password"
                />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full transition-all duration-200 focus:scale-[1.01]"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm your password"
                />
                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </FadeIn>

            <FadeIn :delay="0.5">
                <PrimaryButton
                    class="w-full justify-center transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    :class="{ 'opacity-25 animate-pulse': form.processing }"
                    :disabled="form.processing"
                >
                    Create account
                </PrimaryButton>
            </FadeIn>

            <FadeIn :delay="0.6" class="text-center text-sm text-muted-foreground">
                Already registered?
                <Link
                    :href="route('login')"
                    class="font-medium text-foreground underline underline-offset-4 transition-all duration-200 hover:underline-offset-8"
                >
                    Sign in
                </Link>
            </FadeIn>
        </form>
    </GuestLayout>
</template>
