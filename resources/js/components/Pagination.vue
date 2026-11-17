<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    pages: Pick<Paginated<unknown>, 'links' | 'meta'>;
    previousLabel?: string;
    nextLabel?: string;
}>();
</script>

<template>
    <nav
        v-if="pages.meta.last_page > 1"
        class="flex items-center justify-between gap-4"
        aria-label="Pagination"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="!pages.links.prev"
            :as-child="!!pages.links.prev"
        >
            <Link
                v-if="pages.links.prev"
                :href="pages.links.prev"
                preserve-scroll
                preserve-state
            >
                {{ previousLabel ?? 'Newer' }}
            </Link>
            <template v-else>{{ previousLabel ?? 'Newer' }}</template>
        </Button>
        <span class="text-xs text-muted-foreground">
            Page {{ pages.meta.current_page }} of {{ pages.meta.last_page }}
        </span>
        <Button
            variant="outline"
            size="sm"
            :disabled="!pages.links.next"
            :as-child="!!pages.links.next"
        >
            <Link
                v-if="pages.links.next"
                :href="pages.links.next"
                preserve-scroll
                preserve-state
            >
                {{ nextLabel ?? 'Older' }}
            </Link>
            <template v-else>{{ nextLabel ?? 'Older' }}</template>
        </Button>
    </nav>
</template>
