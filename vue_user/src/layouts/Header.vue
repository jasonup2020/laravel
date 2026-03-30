<template>
  <header class="header bg-white dark:bg-gray-800 shadow-sm px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <el-button 
        v-if="isMobile"
        :icon="Menu" 
        @click="$emit('toggleSidebar')"
        circle
      />
      <el-button 
        v-else
        :icon="collapsed ? Expand : Fold" 
        @click="$emit('toggleSidebar')"
        circle
      />
      <el-breadcrumb separator="/">
        <el-breadcrumb-item :to="{ path: '/' }">{{ t('nav.home') }}</el-breadcrumb-item>
        <el-breadcrumb-item v-if="currentRoute.meta?.title">
          {{ currentRoute.meta.title }}
        </el-breadcrumb-item>
      </el-breadcrumb>
    </div>
    
    <div class="flex items-center gap-4">
      <el-dropdown @command="handleCommand">
        <span class="flex items-center gap-2 cursor-pointer">
          <el-avatar :size="32" :src="store.user?.avatar">
            {{ store.user?.name?.charAt(0) || 'U' }}
          </el-avatar>
          <span class="hidden md:inline">{{ store.user?.name || t('user.name') }}</span>
          <el-icon><ArrowDown /></el-icon>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="profile">
              <el-icon><User /></el-icon>
              {{ t('header.profile') }}
            </el-dropdown-item>
            <el-dropdown-item command="settings">
              <el-icon><Setting /></el-icon>
              {{ t('header.settings') }}
            </el-dropdown-item>
            <el-dropdown-item divided command="logout">
              <el-icon><SwitchButton /></el-icon>
              {{ t('header.logout') }}
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
      
      <el-dropdown @command="handleThemeChange">
        <el-button :icon="themeIcon" circle />
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="light">{{ t('settings.themeLight') }}</el-dropdown-item>
            <el-dropdown-item command="dark">{{ t('settings.themeDark') }}</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
      
      <el-dropdown @command="handleLangChange">
        <el-button :icon="Globe" circle />
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="zh_CN">{{ t('settings.languageZh') }}</el-dropdown-item>
            <el-dropdown-item command="en">{{ t('settings.languageEn') }}</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useMainStore } from '@/store/main'
import { ElMessageBox, ElMessage } from 'element-plus'
import { 
  Menu, 
  Fold, 
  Expand, 
  ArrowDown, 
  User, 
  Setting, 
  SwitchButton,
  Sunny,
  Moon,
  Globe
} from '@element-plus/icons-vue'

defineProps({
  isMobile: Boolean,
  collapsed: Boolean
})

defineEmits(['toggleSidebar'])

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useMainStore()

const currentRoute = computed(() => route)

const themeIcon = computed(() => {
  return store.theme === 'dark' ? Moon : Sunny
})

const handleCommand = (command) => {
  switch (command) {
    case 'profile':
      router.push('/profile')
      break
    case 'settings':
      router.push('/settings')
      break
    case 'logout':
      handleLogout()
      break
  }
}

const handleThemeChange = (theme) => {
  store.setTheme(theme)
  ElMessage.success(t('settings.themeChanged'))
}

const handleLangChange = (lang) => {
  store.setLang(lang)
  ElMessage.success(t('settings.languageChanged'))
  window.location.reload()
}

const handleLogout = async () => {
  try {
    await ElMessageBox.confirm(t('header.logoutConfirm'), t('common.tip'), {
      confirmButtonText: t('common.confirm'),
      cancelButtonText: t('common.cancel'),
      type: 'warning'
    })
    store.logout()
    router.push('/login')
  } catch {
    // 用户取消
  }
}
</script>

<style scoped>
.header {
  position: sticky;
  top: 0;
  z-index: 100;
}
</style>
