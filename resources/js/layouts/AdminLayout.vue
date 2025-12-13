<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import type { BreadcrumbItemType } from '@/types'

interface Props {
  breadcrumbs?: BreadcrumbItemType[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})
</script>

<template>
  <!-- Reuse the unified sidebar layout to avoid duplicate shells/overlapping -->
  <AppSidebarLayout :breadcrumbs="breadcrumbs">
    <div class="p-6">
      <slot />
    </div>
  </AppSidebarLayout>
</template>
