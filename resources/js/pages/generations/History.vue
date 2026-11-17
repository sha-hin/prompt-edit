<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { ImagePlus, Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { history, index } from '@/routes/generations';
import type { Generation, HistoryFilters, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Generate', href: index() },
            { title: 'History', href: history() },
        ],
    },
});

const props = defineProps<{
    generations: Paginated<Generation>;
    filters: HistoryFilters;
}>();

const statuses: { value: HistoryFilters['status']; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'running', label: 'Running' },
    { value: 'failed', label: 'Failed' },
];

const search = ref(props.filters.search ?? '');

// Filters live in the URL, so a reload or a shared link shows the same list.
function apply(filters: Partial<HistoryFilters>): void {
    const query = { ...props.filters, ...filters };

    router.get(
        history.url({
            query: {
                status: query.status ?? undefined,
                search: query.search || undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, (value) => apply({ search: value }), { debounce: 300 });

const inFlight = computed(() =>
    props.generations.data.some(
        (generation) => !['succeeded', 'failed'].includes(generation.status),
    ),
);

const poll = usePoll(
    3000,
    { only: ['generations', 'credits'] },
    { autoStart: false },
);

watch(inFlight, (running) => (running ? poll.start() : poll.stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="History" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    History
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Every image you've made, with what it cost. Pixelforge keeps
                    images for 24 hours, so download the ones you want to keep.
                </p>
            </div>
            <div class="relative w-full md:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search prompts"
                    aria-label="Search prompts"
                    class="bg-card pl-9"
                />
            </div>
        </header>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Status">
            <Button
                v-for="status in statuses"
                :key="status.label"
                size="sm"
                :variant="
                    filters.status === status.value ? 'default' : 'outline'
                "
                :aria-pressed="filters.status === status.value"
                @click="apply({ status: status.value })"
            >
                {{ status.label }}
            </Button>
            <span
                class="ml-auto self-center studio-label text-muted-foreground"
            >
                {{ generations.meta.total }}
                {{ generations.meta.total === 1 ? 'image' : 'images' }}
            </span>
        </div>

        <div
            v-if="!generations.data.length"
            class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center"
        >
            <ImagePlus class="size-8 text-muted-foreground" />
            <p class="font-medium">
                {{
                    filters.status || filters.search
                        ? 'Nothing matches these filters'
                        : 'No images yet'
                }}
            </p>
        </div>

        <div
            v-else
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <GenerationCard
                v-for="generation in generations.data"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <Pagination :pages="generations" />
    </div>
</template>
