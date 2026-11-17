<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CreditPurchaseController from '@/actions/App/Http/Controllers/Credits/CreditPurchaseController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { CreditPack } from '@/types';

const packs: { value: CreditPack; label: string }[] = [
    { value: '5', label: '$5' },
    { value: '10', label: '$10' },
    { value: '25', label: '$25' },
];

const open = ref(false);
const buying = ref<CreditPack | null>(null);

// One key per opening: a double click or a retried request buys once.
const idempotencyKey = ref(crypto.randomUUID());

watch(open, (isOpen) => {
    if (isOpen) {
        idempotencyKey.value = crypto.randomUUID();
    }
});

function buy(pack: CreditPack): void {
    if (buying.value !== null) {
        return;
    }

    router.post(
        CreditPurchaseController.url(),
        { pack, idempotency_key: idempotencyKey.value },
        {
            preserveScroll: true,
            onStart: () => (buying.value = pack),
            onFinish: () => (buying.value = null),
            onSuccess: () => (open.value = false),
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot />
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Buy credits</DialogTitle>
                <DialogDescription>
                    Credits are US dollars. Each image costs what Pixelforge
                    charges plus 25%, and failed images are free. This is a
                    demo: no payment is taken.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    v-for="pack in packs"
                    :key="pack.value"
                    type="button"
                    class="flex flex-col items-center gap-1 rounded-lg border bg-card p-4 text-center transition-colors outline-none hover:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                    :disabled="buying !== null"
                    @click="buy(pack.value)"
                >
                    <span class="font-display text-2xl font-semibold">
                        <Spinner
                            v-if="buying === pack.value"
                            class="inline size-5"
                        />
                        <template v-else>{{ pack.label }}</template>
                    </span>
                    <span class="text-xs text-muted-foreground">credits</span>
                </button>
            </div>

            <Button
                variant="ghost"
                class="w-fit self-end"
                @click="open = false"
            >
                Cancel
            </Button>
        </DialogContent>
    </Dialog>
</template>
