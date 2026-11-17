<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { report as reportRoute } from '@/routes/admin';
import type { SpendReport } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin report',
                href: reportRoute(),
            },
        ],
    },
});

const props = defineProps<{ report: SpendReport }>();

const errors = computed(() => usePage().props.errors);

const start = ref(props.report.start);
const end = ref(props.report.end);
const hideEmptyDays = ref(false);

// Dates are UTC days, the same as Pixelforge's usage report.
const isoDay = (date: Date): string => date.toISOString().slice(0, 10);

function daysBack(days: number): [string, string] {
    const today = new Date();
    const from = new Date(today);
    from.setUTCDate(today.getUTCDate() - (days - 1));

    return [isoDay(from), isoDay(today)];
}

function thisMonth(): [string, string] {
    const today = new Date();

    return [
        isoDay(
            new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1)),
        ),
        isoDay(today),
    ];
}

const presets: { label: string; range: () => [string, string] }[] = [
    { label: '7 days', range: () => daysBack(7) },
    { label: '30 days', range: () => daysBack(30) },
    { label: '90 days', range: () => daysBack(90) },
    { label: 'This month', range: thisMonth },
];

function load(): void {
    router.get(
        reportRoute.url({ query: { start: start.value, end: end.value } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function applyPreset(range: () => [string, string]): void {
    [start.value, end.value] = range();
    load();
}

const days = computed(() =>
    hideEmptyDays.value
        ? props.report.days.filter(
              (day) =>
                  day.succeeded + day.failed > 0 ||
                  (day.pixelforge !== null && !day.pixelforge.matches),
          )
        : props.report.days,
);

const mismatchedDays = computed(
    () =>
        props.report.days.filter(
            (day) => day.pixelforge !== null && !day.pixelforge.matches,
        ).length,
);

const formatDay = (date: string): string =>
    new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    });
</script>

<template>
    <Head title="Admin report" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Admin report
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Credit spend, Pixelforge costs and profit by UTC day of
                    completion, checked against Pixelforge's usage report.
                </p>
            </div>

            <form class="flex flex-wrap items-end gap-3" @submit.prevent="load">
                <div class="grid gap-1.5">
                    <Label
                        for="start"
                        class="studio-label text-muted-foreground"
                    >
                        From
                    </Label>
                    <Input
                        id="start"
                        v-model="start"
                        type="date"
                        :max="end"
                        class="w-40 bg-card"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="end" class="studio-label text-muted-foreground">
                        To
                    </Label>
                    <Input
                        id="end"
                        v-model="end"
                        type="date"
                        :min="start"
                        class="w-40 bg-card"
                    />
                </div>
                <Button type="submit">Show</Button>
            </form>
        </header>

        <div class="-mt-3 flex flex-wrap items-center gap-2">
            <Button
                v-for="preset in presets"
                :key="preset.label"
                variant="outline"
                size="sm"
                @click="applyPreset(preset.range)"
            >
                {{ preset.label }}
            </Button>
            <InputError :message="errors.start ?? errors.end" />
        </div>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <p class="studio-label text-muted-foreground">Credit spend</p>
                <p
                    class="mt-3 font-mono text-2xl font-medium"
                    :title="report.total.spend.exact"
                >
                    {{ report.total.spend.display }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    What users were charged
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <p class="studio-label text-muted-foreground">Provider cost</p>
                <p
                    class="mt-3 font-mono text-2xl font-medium"
                    :title="report.total.cost.exact"
                >
                    {{ report.total.cost.display }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    What Pixelforge charged us
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <p class="studio-label text-muted-foreground">Profit</p>
                <p
                    class="mt-3 font-mono text-2xl font-medium text-accent-ink"
                    :title="report.total.profit.exact"
                >
                    {{ report.total.profit.display }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Margin {{ report.total.margin ?? '—' }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4 shadow-xs">
                <p class="studio-label text-muted-foreground">Images</p>
                <p class="mt-3 font-mono text-2xl font-medium">
                    {{ report.total.succeeded }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ report.total.failed }} failed, not charged
                </p>
            </div>
            <div
                class="rounded-xl border p-4 shadow-xs"
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

        <section class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="font-display text-xl font-semibold tracking-tight">
                    By day
                </h2>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <input
                        v-model="hideEmptyDays"
                        type="checkbox"
                        class="size-4 accent-[var(--accent-ink)]"
                    />
                    Hide days with no activity
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[52rem] text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th
                                class="px-4 py-3 studio-label font-normal text-muted-foreground"
                            >
                                Day (UTC)
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Images
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Failed
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Spend
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Cost
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Profit
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Pixelforge
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            class="border-b last:border-b-0"
                            :class="{
                                'bg-destructive/5':
                                    day.pixelforge && !day.pixelforge.matches,
                                'text-muted-foreground':
                                    day.succeeded + day.failed === 0,
                            }"
                        >
                            <td class="px-4 py-2.5 font-sans text-sm">
                                <time :datetime="day.date">{{
                                    formatDay(day.date)
                                }}</time>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {{ day.succeeded }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {{ day.failed }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="day.spend.exact"
                            >
                                {{ day.spend.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="day.cost.exact"
                            >
                                {{ day.cost.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="day.profit.exact"
                            >
                                {{ day.profit.display }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <span
                                    v-if="day.pixelforge === null"
                                    class="text-muted-foreground"
                                    >—</span
                                >
                                <span
                                    v-else-if="day.pixelforge.matches"
                                    class="inline-flex items-center gap-1.5"
                                    :title="day.pixelforge.cost.exact"
                                >
                                    {{ day.pixelforge.cost.display }}
                                    <CircleCheck
                                        class="size-3.5 text-accent-ink"
                                        aria-label="Matches"
                                    />
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 text-destructive"
                                    :title="`Pixelforge: ${day.pixelforge.succeeded} images, ${day.pixelforge.cost.exact}. Difference ${day.pixelforge.difference.exact}`"
                                >
                                    {{ day.pixelforge.succeeded }} ·
                                    {{ day.pixelforge.cost.display }}
                                    <CircleAlert
                                        class="size-3.5"
                                        aria-label="Differs"
                                    />
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!days.length">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center font-sans text-sm text-muted-foreground"
                            >
                                No finished generations in this range.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="font-mono text-xs font-medium">
                        <tr class="border-t bg-secondary/60">
                            <td class="px-4 py-3 font-sans text-sm">Total</td>
                            <td class="px-4 py-3 text-right">
                                {{ report.total.succeeded }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ report.total.failed }}
                            </td>
                            <td
                                class="px-4 py-3 text-right"
                                :title="report.total.spend.exact"
                            >
                                {{ report.total.spend.display }}
                            </td>
                            <td
                                class="px-4 py-3 text-right"
                                :title="report.total.cost.exact"
                            >
                                {{ report.total.cost.display }}
                            </td>
                            <td
                                class="px-4 py-3 text-right"
                                :title="report.total.profit.exact"
                            >
                                {{ report.total.profit.display }}
                            </td>
                            <td
                                class="px-4 py-3 text-right"
                                :title="report.total.pixelforge?.cost.exact"
                            >
                                {{
                                    report.total.pixelforge?.cost.display ?? '—'
                                }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-xl font-semibold tracking-tight">
                By user
            </h2>

            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th
                                class="px-4 py-3 studio-label font-normal text-muted-foreground"
                            >
                                User
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Images
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Failed
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Spend
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Cost
                            </th>
                            <th
                                class="px-4 py-3 text-right studio-label font-normal text-muted-foreground"
                            >
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr
                            v-for="user in report.users"
                            :key="user.id ?? 'deleted'"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-4 py-2.5 font-sans">
                                <p class="text-sm font-medium">
                                    {{ user.name }}
                                </p>
                                <p
                                    v-if="user.email"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ user.email }}
                                </p>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {{ user.succeeded }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {{ user.failed }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="user.spend.exact"
                            >
                                {{ user.spend.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="user.cost.exact"
                            >
                                {{ user.cost.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="user.profit.exact"
                            >
                                {{ user.profit.display }}
                            </td>
                        </tr>
                        <tr v-if="!report.users.length">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center font-sans text-sm text-muted-foreground"
                            >
                                No one finished a generation in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
