<template>
  <div class="main-layout flex min-h-screen">
    <Sidebar v-if="!isMobile" :collapsed="sidebarCollapsed" @toggle="sidebarCollapsed = !sidebarCollapsed" />
    <div class="flex-1 flex flex-col">
      <Header :isMobile="isMobile" @toggleSidebar="sidebarCollapsed = !sidebarCollapsed" />
      <main class="flex-1 p-4 bg-gray-50 dark:bg-gray-900 overflow-auto">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
    <TabBar v-if="isMobile" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Sidebar from './Sidebar.vue'
import Header from './Header.vue'
import TabBar from './TabBar.vue'

const isMobile = ref(false)
const sidebarCollapsed = ref(false)

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style scoped>
.main-layout {
  min-height: 100vh;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
