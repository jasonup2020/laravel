<template>
  <div class="tab-bar fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 shadow-lg border-t dark:border-gray-700 md:hidden">
    <div class="flex justify-around items-center h-14">
      <div 
        v-for="item in menuList" 
        :key="item.path"
        class="tab-item flex flex-col items-center justify-center flex-1 h-full cursor-pointer"
        :class="{ 'text-primary': isActive(item.path) }"
        @click="navigateTo(item.path)"
      >
        <el-icon :size="20">
          <component :is="item.icon" />
        </el-icon>
        <span class="text-xs mt-1">{{ item.name }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMainStore } from '@/store/main'
import { HomeFilled, User, Setting, Document } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()
const store = useMainStore()

const menuList = computed(() => {
  const defaultMenus = [
    { name: '首页', path: '/home', icon: HomeFilled },
    { name: '个人中心', path: '/profile', icon: User },
    { name: '设置', path: '/settings', icon: Setting }
  ]
  
  if (store.menu && store.menu.length > 0) {
    return store.menu.slice(0, 4).map(item => ({
      name: item.name,
      path: item.path,
      icon: getIcon(item.icon)
    }))
  }
  
  return defaultMenus
})

const isActive = (path) => {
  return route.path === path
}

const navigateTo = (path) => {
  router.push(path)
}

const getIcon = (iconName) => {
  const icons = {
    HomeFilled,
    User,
    Setting,
    Document
  }
  return icons[iconName] || Document
}
</script>

<style scoped>
.tab-bar {
  z-index: 1000;
}

.tab-item {
  color: #666;
  transition: color 0.3s;
}

.tab-item.text-primary {
  color: var(--el-color-primary);
}
</style>
