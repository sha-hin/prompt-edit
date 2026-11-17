<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import DateRangePicker from '@/components/admin/DateRangePicker.vue';
import SpendDaysTable from '@/components/admin/SpendDaysTable.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { report as reportRoute } from '@/routes/admin';
import type { SpendReport } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: reportRoute() }],
    },
});

const props = defineProps<{ report: SpendReport }>();

function load(range: { start: string; end: string }): void {
    router.get(
        reportRoute.url({ query: range }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

const mismatchedDays = computed(
    () =>
        props.report.days.filter(
            (day) => day.pixelforge !== null && !day.pixelforge.matches,
        ).length,
);
</script>

<template>
    <Head title="Overview" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Overview
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Credit spend, Pixelforge costs and profit by UTC day of
                    completion, checked against Pixelforge's usage report.
                </p>
            </div>
            <DateRangePicker
                :start="report.start"
                :end="report.end"
                @change="load"
            />
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Credit spend"
                :value="report.total.spend.display"
                :title="report.total.spend.exact"
                hint="What users were charged"
            />
            <StatCard
                label="Provider cost"
                :value="report.total.cost.display"
                :title="report.total.cost.exact"
                hint="What Pixelforge charged us"
            />
            <StatCard
                label="Profit"
                :value="report.total.profit.display"
                :title="report.total.profit.exact"
                :hint="`Margin ${report.total.margin ?? '—'}`"
                tone="profit"
            />
            <StatCard
                label="Images"
                :value="String(report.total.succeeded)"
                :hint="`${report.total.failed} failed, not charged`"
            />
            <StatCard
                label="Credits sold"
                :value="report.credits_sold.display"
                :title="report.credits_sold.exact"
                hint="Purchases in the range"
            />
            <StatCard
                label="Free credit given"
                :value="report.credits_granted.display"
                :title="report.credits_granted.exact"
                hint="Signup bonuses in the range"
            />
            <div
                class="rounded-xl border p-4 shadow-xs sm:col-span-2"
                :class="
                    !report.pixelforge_available
                        ? 'bg-card'
                        : report.total.pixelforge?.matches
                          ? 'border-primary/60 bg-primary/10'
                          : 'border-destructive/40 bg-destructive/5'
                "
            >
                <p class="studio-label text-muted-foreground">
                    Pixelforge check
                </p>
                <template v-if="!report.pixelforge_available">
                    <p class="mt-3 flex items-center gap-2 text-sm font-medium">
                        <TriangleAlert class="size-4 text-muted-foreground" />
                        Usage unavailable
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Pixelforge didn't answer. Our numbers are still shown.
                    </p>
                </template>
                <template v-else-if="report.total.pixelforge?.matches">
                    <p class="mt-3 flex items-center gap-2 text-sm font-medium">
                        <CircleCheck class="size-4 text-accent-ink" />
                        Matches Pixelforge
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Every day's cost and image count agree.
                    </p>
                </template>
                <template v-else>
                    <p
                        class="mt-3 flex items-center gap-2 text-sm font-medium text-destructive"
                    >
                        <CircleAlert class="size-4" />
                        {{ mismatchedDays }}
                        {{
                            mismatchedDays === 1 ? 'day differs' : 'days differ'
                        }}
                    </p>
                    <p
                        class="mt-1 text-xs text-muted-foreground"
                        :title="report.total.pixelforge?.difference.exact"
                    >
                        Pixelforge billed
                        {{ report.total.pixelforge?.cost.display }}; we are off
                        by {{ report.total.pixelforge?.difference.display }}.
                    </p>
                </template>
            </div>
        </section>

        <SpendDaysTable :days="report.days" :total="report.total" pixelforge />
    </div>
</template>
