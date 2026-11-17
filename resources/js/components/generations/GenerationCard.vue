<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, ExternalLink } from '@lucide/vue';
import GenerationPreview from '@/components/generations/GenerationPreview.vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/generations';
import type { Generation } from '@/types';

defineProps<{ generation: Generation }>();

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-lg border bg-card shadow-xs"
    >
        <Link
            :href="show(generation.id)"
            class="block aspect-square bg-secondary outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            :aria-label="`Open details for: ${generation.prompt}`"
        >
            <GenerationPreview :generation="generation" />
        </Link>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2">
                <GenerationStatusBadge :status="generation.status" />
                <time
                    :datetime="generation.created_at"
                    class="text-xs text-muted-foreground"
                >
                    {{ formatDate(generation.created_at) }}
                </time>
            </div>

            <Link
                :href="show(generation.id)"
                class="line-clamp-3 text-sm hover:underline"
                :title="generation.prompt"
            >
                {{ generation.prompt }}
            </Link>

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
                        <span
                            v-if="generation.status === 'failed'"
                            class="text-muted-foreground"
                            >Not charged</span
                        >
                        <template v-else-if="generation.charge">
                            {{ generation.charge.display }}
                        </template>
                        <span v-else class="text-muted-foreground"
                            >When done</span
                        >
                    </dd>
                </div>
            </dl>

            <div
                v-if="generation.image.status === 'ready'"
                class="mt-auto flex gap-2"
            >
                <Button variant="outline" size="sm" class="flex-1" as-child>
                    <a
                        :href="generation.image.url ?? undefined"
                        target="_blank"
                        rel="noopener"
                    >
                        <ExternalLink /> Open
                    </a>
                </Button>
                <Button variant="outline" size="sm" class="flex-1" as-child>
                    <a :href="generation.image.download_url ?? undefined">
                        <Download /> Download
                    </a>
                </Button>
            </div>
        </div>
    </article>
</template>
