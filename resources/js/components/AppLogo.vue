<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md"
    >
        <AppLogoIcon class="size-6 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]/sidebar-wrapper:hidden">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >PDP Tracker</span
        >
    </div>
</template>
