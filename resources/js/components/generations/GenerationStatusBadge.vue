<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { computed } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import type { GenerationStatus } from '@/types';

const props = defineProps<{ status: GenerationStatus }>();

const labels: Record<GenerationStatus, string> = {
    pending: 'Submitting',
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const inProgress = computed(
    () => props.status !== 'succeeded' && props.status !== 'failed',
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium"
        :class="{
            'border-transparent bg-primary text-primary-foreground':
                status === 'succeeded',
            'border-destructive/30 text-destructive': status === 'failed',
            'text-muted-foreground': inProgress,
        }"
    >
        <Spinner v-if="inProgress" class="size-3" />
        <CircleCheck v-else-if="status === 'succeeded'" class="size-3" />
        <CircleAlert v-else class="size-3" />
        {{ labels[status] }}
    </span>
</template>
