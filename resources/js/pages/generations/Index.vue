<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ArrowRight, ImagePlus } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import { history, index } from '@/routes/generations';
import type { Generation } from '@/types';

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

const props = defineProps<{
    generations: { data: Generation[] };
    total: number;
}>();

const POLL_MS = 3000;

const inFlight = computed(() =>
    props.generations.data.some(
        (generation) => !['succeeded', 'failed'].includes(generation.status),
    ),
);

// Reload only the history and the credit (a finished image is charged), and only while something on this page is still running.
const poll = usePoll(
    POLL_MS,
    { only: ['generations', 'total', 'credits'] },
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
                    Recent generations
                </h2>
                <Link
                    v-if="total > generations.data.length"
                    :href="history()"
                    class="inline-flex items-center gap-1 text-sm font-medium text-accent-ink hover:underline"
                >
                    View all {{ total }}
                    <ArrowRight class="size-4" />
                </Link>
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
        </section>
    </div>
</template>
