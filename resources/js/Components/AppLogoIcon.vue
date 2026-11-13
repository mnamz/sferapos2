<script setup lang="ts">
import { Store } from 'lucide-vue-next';

defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <!-- Generic storefront mark. Callers pass fill-current; force fill off so the outline icon stays an outline. -->
    <Store v-bind="$attrs" :stroke-width="1.75" style="fill: none" />
</template>
