<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/generations';
import type { Generation, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Generate',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{ generations: Paginated<Generation> }>();

const POLL_MS = 3000;

const inFlight = computed(() =>
    props.generations.data.some(
        (generation) => !['succeeded', 'failed'].includes(generation.status),
    ),
);

// Reload only the history and the credit (a finished image is charged), and only while something on this page is still running.
const poll = usePoll(
    POLL_MS,
    { only: ['generations', 'credits'] },
    { autoStart: false },
);

watch(inFlight, (running) => (running ? poll.start() : poll.stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Generate" />

    <div
        class="grid flex-1 gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]"
    >
        <section
            class="h-fit rounded-xl border bg-card p-5 shadow-xs lg:sticky lg:top-4"
        >
            <header class="mb-6">
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Create an image
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Describe it, pick a size and quality, and see the cost
                    before you generate.
                </p>
            </header>
            <GenerationForm />
        </section>

        <section class="flex min-w-0 flex-col gap-4">
            <header class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-xl font-semibold tracking-tight">
                    Your generations
                </h2>
                <span
                    v-if="generations.meta.total"
                    class="studio-label text-muted-foreground"
                >
                    {{ generations.meta.total }} total
                </span>
            </header>

            <div
                v-if="!generations.data.length"
                class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
            >
                <ImagePlus class="size-8 text-muted-foreground" />
                <p class="font-medium">No images yet</p>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Write a prompt to make your first one. It appears here as
                    soon as you submit, with its status and cost.
                </p>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <GenerationCard
                    v-for="generation in generations.data"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>

            <nav
                v-if="generations.meta.last_page > 1"
                class="flex items-center justify-between gap-4"
                aria-label="Pagination"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!generations.links.prev"
                    :as-child="!!generations.links.prev"
                >
                    <Link
                        v-if="generations.links.prev"
                        :href="generations.links.prev"
                        preserve-scroll
                    >
                        Newer
                    </Link>
                    <template v-else>Newer</template>
                </Button>
                <span class="text-xs text-muted-foreground">
                    Page {{ generations.meta.current_page }} of
                    {{ generations.meta.last_page }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!generations.links.next"
                    :as-child="!!generations.links.next"
                >
                    <Link
                        v-if="generations.links.next"
                        :href="generations.links.next"
                        preserve-scroll
                    >
                        Older
                    </Link>
                    <template v-else>Older</template>
                </Button>
            </nav>
        </section>
    </div>
</template>
