<template>
  <aside 
    class="sidebar bg-white dark:bg-gray-800 shadow-lg transition-all duration-300"
    :class="{ 'w-64': !collapsed, 'w-16': collapsed }"
  >
    <div class="logo p-4 border-b dark:border-gray-700">
      <h1 v-if="!collapsed" class="text-xl font-bold text-primary">{{ t('user.title') }}</h1>
      <span v-else class="text-2xl">🏠</span>
    </div>
    
    <el-menu
      :default-active="activeMenu"
      :collapse="collapsed"
      class="border-none"
      router
    >
      <template v-for="item in menuList" :key="item.path">
        <el-sub-menu v-if="item.children && item.children.length" :index="item.path">
          <template #title>
            <el-icon><component :is="item.icon" /></el-icon>
            <span>{{ item.name }}</span>
          </template>
          <el-menu-item 
            v-for="child in item.children" 
            :key="child.path" 
            :index="child.path"
            v-permission="child.permission"
          >
            <el-icon><component :is="child.icon" /></el-icon>
            <template #title>{{ child.name }}</template>
          </el-menu-item>
        </el-sub-menu>
        <el-menu-item v-else :index="item.path" v-permission="item.permission">
          <el-icon><component :is="item.icon" /></el-icon>
          <template #title>{{ item.name }}</template>
        </el-menu-item>
      </template>
    </el-menu>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useMainStore } from '@/store/main'
import { 
  HomeFilled, 
  User, 
  Setting, 
  Document,
  Menu,
  Users,
  Key,
  OfficeBuilding,
  Grid
} from '@element-plus/icons-vue'

defineProps({
  collapsed: Boolean
})

defineEmits(['toggle'])

const { t } = useI18n()
const route = useRoute()
const store = useMainStore()

const activeMenu = computed(() => route.path)

const menuList = computed(() => {
  const defaultMenus = [
    { name: t('nav.home'), path: '/home', icon: HomeFilled },
    { 
      name: t('nav.systemManagement'), 
      path: '/system', 
      icon: Setting,
      children: [
        { name: t('nav.userManagement'), path: '/users', icon: Users, permission: 'user.index' },
        { name: t('nav.roleManagement'), path: '/roles', icon: Key, permission: 'role.index' },
        { name: t('nav.departmentManagement'), path: '/departments', icon: OfficeBuilding, permission: 'department.index' },
        { name: t('nav.menuManagement'), path: '/menus', icon: Menu, permission: 'menu.index' }
      ]
    },
    { name: t('nav.profile'), path: '/profile', icon: User },
    { name: t('nav.settings'), path: '/settings', icon: Setting }
  ]
  
  if (store.menu && store.menu.length > 0) {
    return store.menu.map(item => ({
      name: item.name,
      path: item.path,
      icon: getIcon(item.icon),
      permission: item.permission,
      children: item.children?.map(child => ({
        name: child.name,
        path: child.path,
        icon: getIcon(child.icon),
        permission: child.permission
      }))
    }))
  }
  
  return defaultMenus
})

const getIcon = (iconName) => {
  const icons = {
    HomeFilled,
    User,
    Setting,
    Document,
    Menu,
    Users,
    Key,
    OfficeBuilding,
    Grid
  }
  return icons[iconName] || Document
}
</script>

<style scoped>
.sidebar {
  height: 100vh;
  position: sticky;
  top: 0;
}

:deep(.el-menu) {
  border-right: none;
}
</style>
