<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { Download, ExternalLink } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationPreview from '@/components/generations/GenerationPreview.vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { history, index } from '@/routes/generations';
import type { Generation } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Generate', href: index() },
            { title: 'History', href: history() },
        ],
    },
});

const props = defineProps<{ generation: Generation }>();

const formatDate = (iso: string | null): string =>
    iso === null
        ? '—'
        : new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'medium',
          });

const duration = computed(() => {
    const { created_at, completed_at } = props.generation;

    if (completed_at === null) {
        return null;
    }

    const seconds = Math.round(
        (Date.parse(completed_at) - Date.parse(created_at)) / 1000,
    );

    return seconds < 60
        ? `${seconds}s`
        : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
});

const tokens = computed(() => {
    const usage = props.generation.usage;

    return usage === null
        ? []
        : [
              { label: 'Prompt text', value: usage.input_text_tokens },
              { label: 'Reference images', value: usage.input_image_tokens },
              { label: 'Output', value: usage.output_tokens },
              { label: 'Reasoning', value: usage.reasoning_tokens },
          ];
});

const inFlight = computed(
    () => !['succeeded', 'failed'].includes(props.generation.status),
);

const poll = usePoll(
    3000,
    { only: ['generation', 'credits'] },
    { autoStart: false },
);

watch(inFlight, (running) => (running ? poll.start() : poll.stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Image" />

    <div
        class="grid flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]"
    >
        <section class="flex flex-col gap-3">
            <div
                class="aspect-square w-full overflow-hidden rounded-xl border bg-secondary shadow-xs lg:max-h-[calc(100vh-10rem)]"
            >
                <GenerationPreview :generation="generation" />
            </div>
            <div
                v-if="generation.image.status === 'ready'"
                class="flex flex-wrap items-center gap-2"
            >
                <Button as-child>
                    <a :href="generation.image.download_url ?? undefined">
                        <Download /> Download PNG
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="generation.image.url ?? undefined"
                        target="_blank"
                        rel="noopener"
                    >
                        <ExternalLink /> Open full size
                    </a>
                </Button>
                <span class="text-xs text-muted-foreground">
                    Available until
                    {{ formatDate(generation.image.expires_at) }}
                </span>
            </div>
        </section>

        <aside class="flex flex-col gap-4">
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <GenerationStatusBadge :status="generation.status" />
                    <span v-if="duration" class="text-xs text-muted-foreground"
                        >Took {{ duration }}</span
                    >
                </div>
                <p class="mt-5 studio-label text-muted-foreground">Prompt</p>
                <p class="mt-2 text-sm leading-relaxed whitespace-pre-wrap">
                    {{ generation.prompt }}
                </p>
                <p
                    v-if="generation.status === 'failed'"
                    class="mt-4 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
                >
                    {{ generation.error_message ?? 'This generation failed.' }}
                    You were not charged.
                </p>
            </div>

            <dl
                class="grid grid-cols-2 gap-x-4 gap-y-4 rounded-xl border bg-card p-5 shadow-xs"
            >
                <div>
                    <dt class="studio-label text-muted-foreground">Estimate</dt>
                    <dd class="mt-2 font-mono text-sm">
                        {{ generation.estimate.display }}
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">Charged</dt>
                    <dd
                        class="mt-2 font-mono text-sm font-medium"
                        :title="generation.charge?.exact"
                    >
                        <span
                            v-if="generation.status === 'failed'"
                            class="text-muted-foreground"
                            >Not charged</span
                        >
                        <template v-else-if="generation.charge">{{
                            generation.charge.exact
                        }}</template>
                        <span v-else class="text-muted-foreground"
                            >When done</span
                        >
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">Size</dt>
                    <dd class="mt-2 text-sm">
                        {{ generation.size.replace('x', '×') }}
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">Quality</dt>
                    <dd class="mt-2 text-sm capitalize">
                        {{ generation.quality }}
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">Created</dt>
                    <dd class="mt-2 text-sm">
                        {{ formatDate(generation.created_at) }}
                    </dd>
                </div>
                <div>
                    <dt class="studio-label text-muted-foreground">
                        Completed
                    </dt>
                    <dd class="mt-2 text-sm">
                        {{ formatDate(generation.completed_at) }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="tokens.length"
                class="rounded-xl border bg-card p-5 shadow-xs"
            >
                <p class="studio-label text-muted-foreground">Tokens used</p>
                <ul class="mt-3 grid gap-2 text-sm">
                    <li
                        v-for="token in tokens"
                        :key="token.label"
                        class="flex justify-between"
                    >
                        <span class="text-muted-foreground">{{
                            token.label
                        }}</span>
                        <span class="font-mono">{{
                            token.value.toLocaleString()
                        }}</span>
                    </li>
                </ul>
            </div>

            <div
                v-if="generation.reference_images.length"
                class="rounded-xl border bg-card p-5 shadow-xs"
            >
                <p class="studio-label text-muted-foreground">
                    Reference images
                </p>
                <ul class="mt-3 grid gap-2">
                    <li
                        v-for="url in generation.reference_images"
                        :key="url"
                        class="truncate text-sm"
                    >
                        <a
                            :href="url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-accent-ink hover:underline"
                            >{{ url }}</a
                        >
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</template>
