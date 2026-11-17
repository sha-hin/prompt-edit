<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import DateRangePicker from '@/components/admin/DateRangePicker.vue';
import SpendDaysTable from '@/components/admin/SpendDaysTable.vue';
import StatCard from '@/components/admin/StatCard.vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import Pagination from '@/components/Pagination.vue';
import { index as usersRoute, show as userRoute } from '@/routes/admin/users';
import { show as generationRoute } from '@/routes/generations';
import type {
    AdminGeneration,
    LedgerEntry,
    Paginated,
    UserReport,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: usersRoute() }],
    },
});

const props = defineProps<{
    report: UserReport;
    generations: Paginated<AdminGeneration>;
    ledger: Paginated<LedgerEntry>;
}>();

function load(range: { start: string; end: string }): void {
    router.get(
        userRoute.url(props.report.user.id, { query: range }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

const formatDate = (iso: string | null): string =>
    iso === null
        ? '—'
        : new Date(iso).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });

const ledgerLabels: Record<LedgerEntry['type'], string> = {
    grant: 'Free credit',
    purchase: 'Purchase',
    charge: 'Image charged',
};

const th =
    'studio-label px-4 py-3 text-right font-normal text-muted-foreground';
</script>

<template>
    <Head :title="report.user.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1
                    class="flex items-center gap-3 font-display text-2xl font-semibold tracking-tight"
                >
                    {{ report.user.name }}
                    <span
                        v-if="report.user.is_admin"
                        class="rounded-full border px-2 py-0.5 font-sans text-xs font-normal text-muted-foreground"
                        >Admin</span
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ report.user.email }} · joined
                    {{ formatDate(report.user.joined_at) }}
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
                label="Balance now"
                :value="report.user.balance.display"
                :title="report.user.balance.exact"
                :hint="`Bought ${report.user.purchased.display} · given ${report.user.granted.display}`"
            />
            <StatCard
                label="Spend in range"
                :value="report.total.spend.display"
                :title="report.total.spend.exact"
                :hint="`${report.total.succeeded} images, ${report.total.failed} failed`"
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
        </section>

        <SpendDaysTable :days="report.days" :total="report.total" />

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-xl font-semibold tracking-tight">
                Generations
            </h2>
            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead>
                        <tr class="border-b">
                            <th :class="[th, 'text-left']">Prompt</th>
                            <th :class="[th, 'text-left']">Status</th>
                            <th :class="[th, 'text-left']">Created</th>
                            <th :class="th">Estimate</th>
                            <th :class="th">Charged</th>
                            <th :class="th">Cost</th>
                            <th :class="th">Profit</th>
                            <th :class="th">
                                <span class="sr-only">Image</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr
                            v-for="generation in generations.data"
                            :key="generation.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="max-w-72 px-4 py-2.5 font-sans">
                                <Link
                                    :href="generationRoute(generation.id)"
                                    class="line-clamp-2 text-sm hover:underline"
                                    :title="generation.prompt"
                                >
                                    {{ generation.prompt }}
                                </Link>
                            </td>
                            <td class="px-4 py-2.5 font-sans">
                                <GenerationStatusBadge
                                    :status="generation.status"
                                />
                            </td>
                            <td class="px-4 py-2.5 font-sans text-xs">
                                {{ formatDate(generation.created_at) }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {{ generation.estimate.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="generation.charge?.exact"
                            >
                                {{ generation.charge?.display ?? '—' }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="generation.cost?.exact"
                            >
                                {{ generation.cost?.display ?? '—' }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="generation.profit?.exact"
                            >
                                {{ generation.profit?.display ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <a
                                    v-if="generation.image.download_url"
                                    :href="generation.image.download_url"
                                    class="inline-flex items-center gap-1 font-sans text-accent-ink hover:underline"
                                >
                                    <Download class="size-3.5" /> PNG
                                </a>
                                <span
                                    v-else-if="
                                        generation.image.status === 'expired'
                                    "
                                    class="font-sans text-muted-foreground"
                                    >Expired</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!generations.data.length">
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center font-sans text-sm text-muted-foreground"
                            >
                                No generations yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :pages="generations" />
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-display text-xl font-semibold tracking-tight">
                Credit ledger
            </h2>
            <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b">
                            <th :class="[th, 'text-left']">When</th>
                            <th :class="[th, 'text-left']">Entry</th>
                            <th :class="th">Amount</th>
                            <th :class="th">Balance after</th>
                        </tr>
                    </thead>
                    <tbody class="font-mono text-xs">
                        <tr
                            v-for="entry in ledger.data"
                            :key="entry.id"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-4 py-2.5 font-sans text-xs">
                                {{ formatDate(entry.created_at) }}
                            </td>
                            <td class="px-4 py-2.5 font-sans text-sm">
                                <Link
                                    v-if="entry.generation_id"
                                    :href="generationRoute(entry.generation_id)"
                                    class="hover:underline"
                                >
                                    {{ ledgerLabels[entry.type] }}
                                </Link>
                                <template v-else>{{
                                    ledgerLabels[entry.type]
                                }}</template>
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :class="{
                                    'text-accent-ink':
                                        !entry.amount.amount.startsWith('-'),
                                }"
                                :title="entry.amount.exact"
                            >
                                {{ entry.amount.display }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-right"
                                :title="entry.balance_after.exact"
                            >
                                {{ entry.balance_after.display }}
                            </td>
                        </tr>
                        <tr v-if="!ledger.data.length">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center font-sans text-sm text-muted-foreground"
                            >
                                No credit movements.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :pages="ledger" />
        </section>
    </div>
</template>
