<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import BuyCreditsDialog from '@/components/credits/BuyCreditsDialog.vue';
import { Button } from '@/components/ui/button';

const credits = computed(() => usePage().props.credits);
</script>

<template>
    <div
        v-if="credits"
        class="rounded-lg border bg-card p-3 group-data-[collapsible=icon]:hidden"
    >
        <p class="studio-label text-muted-foreground">Credits</p>
        <p
            class="mt-2 font-mono text-lg font-medium"
            :class="{
                'text-destructive': credits.available.amount.startsWith('-'),
            }"
            :title="`Balance ${credits.balance.exact}`"
        >
            {{ credits.available.display }}
        </p>
        <p
            v-if="credits.reserved.amount !== '0.000000000'"
            class="text-xs text-muted-foreground"
            :title="credits.reserved.exact"
        >
            {{ credits.reserved.display }} held for running images
        </p>
        <BuyCreditsDialog>
            <Button size="sm" variant="outline" class="mt-3 w-full">
                <Plus /> Buy credits
            </Button>
        </BuyCreditsDialog>
    </div>
</template>
