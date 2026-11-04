<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import GoogleSignInButton from '@/components/GoogleSignInButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import type { AppPageProps } from '@/types';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<AppPageProps>();
const features = computed(() => page.props.authFeatures);
// Set by the Google callback when PDP does not let the account in
const googleError = computed(() => page.props.errors?.google);

const description = computed(() =>
    features.value.passwordLogin
        ? 'Enter your email and password below to log in'
        : 'Sign in with your work Google account',
);
</script>

<template>
    <AuthBase title="Log in to your account" :description="description">
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div v-if="features.googleLogin" class="flex flex-col gap-6">
            <div class="flex flex-col gap-3">
                <GoogleSignInButton />
                <InputError class="text-center" :message="googleError" />
            </div>

            <div
                v-if="features.passwordLogin"
                class="flex items-center gap-3 text-xs text-muted-foreground uppercase"
            >
                <span class="h-px flex-1 bg-border" />
                or
                <span class="h-px flex-1 bg-border" />
            </div>
        </div>

        <Form
            v-if="features.passwordLogin"
            v-bind="AuthenticatedSessionController.store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>

        <p
            v-if="!features.googleLogin && !features.passwordLogin"
            class="text-center text-sm text-muted-foreground"
        >
            Sign-in is not configured yet. Please contact an administrator.
        </p>
    </AuthBase>
</template>
