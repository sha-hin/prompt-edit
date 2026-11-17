<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { PixelforgeComparison, SpendFigures } from '@/types';

type Day = SpendFigures & {
    date: string;
    pixelforge?: PixelforgeComparison | null;
};

const props = defineProps<{
    days: Day[];
    total: SpendFigures & { pixelforge?: PixelforgeComparison | null };
    pixelforge?: boolean;
}>();

const hideEmptyDays = ref(false);

const shown = computed(() =>
    hideEmptyDays.value
        ? props.days.filter(
              (day) =>
                  day.succeeded + day.failed > 0 ||
                  (day.pixelforge != null && !day.pixelforge.matches),
          )
        : props.days,
);

const formatDay = (date: string): string =>
    new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    });

const th =
    'studio-label px-4 py-3 text-right font-normal text-muted-foreground';
</script>

<template>
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
            <table class="w-full min-w-[44rem] text-sm">
                <thead>
                    <tr class="border-b">
                        <th :class="[th, 'text-left']">Day (UTC)</th>
                        <th :class="th">Images</th>
                        <th :class="th">Failed</th>
                        <th :class="th">Spend</th>
                        <th :class="th">Cost</th>
                        <th :class="th">Profit</th>
                        <th v-if="pixelforge" :class="th">Pixelforge</th>
                    </tr>
                </thead>
                <tbody class="font-mono text-xs">
                    <tr
                        v-for="day in shown"
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
                        <td class="px-4 py-2.5 text-right">{{ day.failed }}</td>
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
                        <td v-if="pixelforge" class="px-4 py-2.5 text-right">
                            <span
                                v-if="!day.pixelforge"
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
                    <tr v-if="!shown.length">
                        <td
                            :colspan="pixelforge ? 7 : 6"
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
                            {{ total.succeeded }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ total.failed }}</td>
                        <td
                            class="px-4 py-3 text-right"
                            :title="total.spend.exact"
                        >
                            {{ total.spend.display }}
                        </td>
                        <td
                            class="px-4 py-3 text-right"
                            :title="total.cost.exact"
                        >
                            {{ total.cost.display }}
                        </td>
                        <td
                            class="px-4 py-3 text-right"
                            :title="total.profit.exact"
                        >
                            {{ total.profit.display }}
                        </td>
                        <td
                            v-if="pixelforge"
                            class="px-4 py-3 text-right"
                            :title="total.pixelforge?.cost.exact"
                        >
                            {{ total.pixelforge?.cost.display ?? '—' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>
