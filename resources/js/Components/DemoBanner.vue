<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';
import { computed } from 'vue';

const demo = computed(() => usePage<SharedData>().props.demo);

const expiry = computed(() => {
    const days = demo.value?.days_left;
    if (days == null) return null;
    if (days === 0) return 'It will be deleted tonight.';
    if (days === 1) return 'It will be deleted in 1 day.';
    return `It will be deleted in ${days} days.`;
});
</script>

<template>
    <div
        v-if="demo"
        class="flex items-center justify-center gap-2 border-b border-amber-300 bg-amber-100 px-4 py-2 text-center text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-100"
    >
        <FlaskConical class="size-4 shrink-0" />
        <span>
            <strong>This is a demo.</strong>
            Data is sample-only and may be reset.
            <template v-if="expiry"> {{ expiry }}</template>
        </span>
    </div>
</template>
