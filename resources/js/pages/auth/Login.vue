<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

type DemoAccount = { role: string; email: string; password: string };

defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoAccounts: DemoAccount[];
}>();

const email = ref('');
const password = ref('');

function fillWith(account: DemoAccount): void {
    email.value = account.email;
    password.value = account.password;
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    required
                    v-focus
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
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    v-model="password"
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
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>

    <section
        v-if="demoAccounts.length"
        class="mt-8 rounded-lg border bg-secondary/60 p-4"
        aria-labelledby="demo-accounts"
    >
        <h2 id="demo-accounts" class="studio-label text-muted-foreground">
            Demo accounts
        </h2>
        <ul class="mt-3 grid gap-3">
            <li
                v-for="account in demoAccounts"
                :key="account.email"
                class="flex items-center justify-between gap-3 rounded-md border bg-card p-3"
            >
                <div class="min-w-0 text-sm">
                    <p class="font-medium">{{ account.role }}</p>
                    <p class="truncate font-mono text-xs text-muted-foreground">
                        {{ account.email }}
                    </p>
                    <p class="truncate font-mono text-xs text-muted-foreground">
                        {{ account.password }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    :data-test="`fill-${account.role.toLowerCase()}`"
                    @click="fillWith(account)"
                >
                    Fill in
                </Button>
            </li>
        </ul>
    </section>
</template>
