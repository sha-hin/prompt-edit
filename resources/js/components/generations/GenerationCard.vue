<script setup lang="ts">
import { CircleAlert, CircleCheck, ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Spinner } from '@/components/ui/spinner';
import type { Generation, GenerationStatus } from '@/types';

const props = defineProps<{ generation: Generation }>();

const statusLabels: Record<GenerationStatus, string> = {
    pending: 'Submitting',
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const imageFailed = ref(false);

const inProgress = computed(
    () => !['succeeded', 'failed'].includes(props.generation.status),
);

const createdAt = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);

const sizeLabel = computed(() => props.generation.size.replace('x', '×'));
</script>

<template>
    <article class="overflow-hidden rounded-lg border bg-card shadow-xs">
        <div
            class="relative flex aspect-square items-center justify-center bg-secondary"
        >
            <img
                v-if="
                    generation.status === 'succeeded' &&
                    generation.image_url &&
                    !imageFailed
                "
                :src="generation.image_url"
                :alt="generation.prompt"
                loading="lazy"
                class="size-full object-cover"
                @error="imageFailed = true"
            />
            <div
                v-else-if="generation.status === 'succeeded'"
                class="flex flex-col items-center gap-2 text-muted-foreground"
            >
                <ImageOff class="size-6" />
                <span class="text-xs">Image link expired</span>
            </div>
            <div
                v-else-if="generation.status === 'failed'"
                class="flex flex-col items-center gap-2 px-6 text-center text-destructive"
            >
                <CircleAlert class="size-6" />
                <span class="text-xs">{{
                    generation.error_message ?? 'This generation failed.'
                }}</span>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-2 text-muted-foreground"
            >
                <Spinner class="size-6" />
                <span class="text-xs">{{
                    statusLabels[generation.status]
                }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="{
                        'border-transparent bg-primary text-primary-foreground':
                            generation.status === 'succeeded',
                        'border-destructive/30 text-destructive':
                            generation.status === 'failed',
                        'text-muted-foreground': inProgress,
                    }"
                >
                    <Spinner v-if="inProgress" class="size-3" />
                    <CircleCheck
                        v-else-if="generation.status === 'succeeded'"
                        class="size-3"
                    />
                    <CircleAlert v-else class="size-3" />
                    {{ statusLabels[generation.status] }}
                </span>
                <time
                    :datetime="generation.created_at"
                    class="text-xs text-muted-foreground"
                >
                    {{ createdAt }}
                </time>
            </div>

            <p class="line-clamp-3 text-sm" :title="generation.prompt">
                {{ generation.prompt }}
            </p>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-2 border-t pt-3">
                <div>
                    <dt class="studio-label text-muted-foreground">Estimate</dt>
                    <dd class="mt-1 font-mono text-xs">
                        {{ generation.estimate.display }}
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">Charged</dt>
                    <dd
                        class="mt-1 font-mono text-xs font-medium"
                        :title="generation.charge?.exact"
                    >
                        <template v-if="generation.status === 'failed'">
                            <span class="text-muted-foreground"
                                >Not charged</span
                            >
                        </template>
                        <template v-else-if="generation.charge">
                            {{ generation.charge.display }}
                        </template>
                        <span v-else class="text-muted-foreground"
                            >When done</span
                        >
                    </dd>
                </div>
                <div class="col-span-2 text-xs text-muted-foreground">
                    {{ sizeLabel }} · {{ generation.quality }} quality
                    <template v-if="generation.reference_images.length">
                        · {{ generation.reference_images.length }} reference
                        {{
                            generation.reference_images.length === 1
                                ? 'image'
                                : 'images'
                        }}
                    </template>
                </div>
            </dl>
        </div>
    </article>
</template>
