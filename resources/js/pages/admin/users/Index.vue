<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import DateRangePicker from '@/components/admin/DateRangePicker.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { index as usersRoute, show as userRoute } from '@/routes/admin/users';
import type { AdminUserRow, Paginated, UsersReportFilters } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: usersRoute() }],
    },
});

const props = defineProps<{
    users: Paginated<AdminUserRow>;
    filters: UsersReportFilters;
}>();

const sorts: { value: UsersReportFilters['sort']; label: string }[] = [
    { value: 'spend', label: 'Spend' },
    { value: 'balance', label: 'Balance' },
    { value: 'joined', label: 'Newest' },
];

const search = ref(props.filters.search ?? '');

function apply(filters: Partial<UsersReportFilters>): void {
    const query = { ...props.filters, ...filters };

    router.get(
        usersRoute.url({
            query: {
                start: query.start,
                end: query.end,
                search: query.search || undefined,
                sort: query.sort === 'spend' ? undefined : query.sort,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watchDebounced(search, (value) => apply({ search: value }), { debounce: 300 });

const joined = (iso: string | null): string =>
    iso === null
        ? '—'
        : new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });

const th =
    'studio-label px-4 py-3 text-right font-normal text-muted-foreground';
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight">
                    Users
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    What each user spent in the range, what they cost us, and
                    their credit balance now.
                </p>
            </div>
            <DateRangePicker
                :start="filters.start"
                :end="filters.end"
                @change="(range) => apply(range)"
            />
        </header>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative w-full sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search name or email"
                    aria-label="Search name or email"
                    class="bg-card pl-9"
                />
            </div>
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground sm:ml-auto"
            >
                Sort by
                <select
                    :value="filters.sort"
                    class="h-9 rounded-md border border-input bg-card px-2 text-sm text-foreground"
                    @change="
                        apply({
                            sort: ($event.target as HTMLSelectElement)
                                .value as UsersReportFilters['sort'],
                        })
                    "
                >
                    <option
                        v-for="sort in sorts"
                        :key="sort.value"
                        :value="sort.value"
                    >
                        {{ sort.label }}
                    </option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <table class="w-full min-w-[56rem] text-sm">
                <thead>
                    <tr class="border-b">
                        <th :class="[th, 'text-left']">User</th>
                        <th :class="[th, 'text-left']">Joined</th>
                        <th :class="th">Balance</th>
                        <th :class="th">Images</th>
                        <th :class="th">Failed</th>
                        <th :class="th">Spend</th>
                        <th :class="th">Cost</th>
                        <th :class="th">Profit</th>
                    </tr>
                </thead>
                <tbody class="font-mono text-xs">
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b last:border-b-0 hover:bg-secondary/50"
                    >
                        <td class="px-4 py-2.5 font-sans">
                            <Link
                                :href="
                                    userRoute.url(user.id, {
                                        query: {
                                            start: filters.start,
                                            end: filters.end,
                                        },
                                    })
                                "
                                class="text-sm font-medium hover:underline"
                            >
                                {{ user.name }}
                            </Link>
                            <span
                                v-if="user.is_admin"
                                class="ml-2 rounded-full border px-1.5 py-0.5 text-[0.625rem] text-muted-foreground"
                                >Admin</span
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </td>
                        <td class="px-4 py-2.5 font-sans text-xs">
                            {{ joined(user.joined_at) }}
                        </td>
                        <td
                            class="px-4 py-2.5 text-right"
                            :title="user.balance.exact"
                        >
                            {{ user.balance.display }}
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
                    <tr v-if="!users.data.length">
                        <td
                            colspan="8"
                            class="px-4 py-8 text-center font-sans text-sm text-muted-foreground"
                        >
                            No users match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :pages="users"
            previous-label="Previous"
            next-label="Next"
        />
    </div>
</template>
