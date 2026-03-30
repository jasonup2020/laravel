<template>
  <div class="settings-page">
    <el-card>
      <template #header>
        <span class="font-bold text-lg">系统设置</span>
      </template>
      
      <el-tabs v-model="activeTab">
        <el-tab-pane label="外观设置" name="appearance">
          <el-form label-width="120px">
            <el-form-item label="主题模式">
              <el-radio-group v-model="settings.theme" @change="handleThemeChange">
                <el-radio label="light">浅色模式</el-radio>
                <el-radio label="dark">深色模式</el-radio>
              </el-radio-group>
            </el-form-item>
            
            <el-form-item label="语言">
              <el-select v-model="settings.lang" @change="handleLangChange">
                <el-option label="中文" value="zh_CN" />
                <el-option label="English" value="en" />
              </el-select>
            </el-form-item>
            
            <el-form-item label="侧边栏">
              <el-switch v-model="settings.sidebarCollapsed" />
              <span class="ml-2 text-gray-500">折叠侧边栏</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="通知设置" name="notification">
          <el-form label-width="120px">
            <el-form-item label="系统通知">
              <el-switch v-model="settings.systemNotification" />
            </el-form-item>
            
            <el-form-item label="邮件通知">
              <el-switch v-model="settings.emailNotification" />
            </el-form-item>
            
            <el-form-item label="消息提醒">
              <el-switch v-model="settings.messageReminder" />
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="安全设置" name="security">
          <el-form label-width="120px">
            <el-form-item label="登录验证">
              <el-switch v-model="settings.twoFactorAuth" />
              <span class="ml-2 text-gray-500">开启两步验证</span>
            </el-form-item>
            
            <el-form-item label="登录设备">
              <el-button type="primary" link>查看已登录设备</el-button>
            </el-form-item>
            
            <el-form-item label="登录日志">
              <el-button type="primary" link>查看登录记录</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        
        <el-tab-pane label="关于" name="about">
          <div class="about-content">
            <h3 class="text-lg font-bold mb-4">用户中心</h3>
            <p class="text-gray-600 mb-2">版本: 1.0.0</p>
            <p class="text-gray-600 mb-2">技术栈: Vue 3 + Vite + Element Plus + Tailwind CSS</p>
            <p class="text-gray-600 mb-4">后端: Laravel API</p>
            <el-divider />
            <p class="text-gray-500 text-sm">
              本系统为企业级用户管理平台，支持多租户、权限管理、动态菜单等功能。
            </p>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useMainStore } from '@/store/main'
import { ElMessage } from 'element-plus'

const store = useMainStore()
const activeTab = ref('appearance')

const settings = reactive({
  theme: 'light',
  lang: 'zh_CN',
  sidebarCollapsed: false,
  systemNotification: true,
  emailNotification: true,
  messageReminder: true,
  twoFactorAuth: false
})

onMounted(() => {
  settings.theme = store.theme
  settings.lang = store.lang
})

const handleThemeChange = (theme) => {
  store.setTheme(theme)
  ElMessage.success('主题已切换')
}

const handleLangChange = (lang) => {
  store.setLang(lang)
  ElMessage.success('语言已切换，页面即将刷新')
  setTimeout(() => {
    window.location.reload()
  }, 1000)
}
</script>

<style scoped>
.about-content {
  padding: 20px 0;
}
</style>
