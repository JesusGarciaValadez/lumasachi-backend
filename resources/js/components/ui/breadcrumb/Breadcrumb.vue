<script lang="ts" setup>
import type { HTMLAttributes } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n()

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <nav
    :aria-label="t('common.breadcrumb')"
    data-slot="breadcrumb"
    :class="props.class"
  >
    <slot />
  </nav>
</template>
