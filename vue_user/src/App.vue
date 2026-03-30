<template>
  <div :class="store.theme">
    <router-view />
  </div>
</template>

<script setup>
import { useMainStore } from '@/store/main'
const store = useMainStore()
</script>
