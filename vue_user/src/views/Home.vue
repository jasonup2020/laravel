<template>
  <div class="home-page">
    <el-row :gutter="20">
      <el-col :span="24">
        <el-card class="welcome-card mb-4">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold">{{ t('home.welcome') }}，{{ store.user?.name || t('user.name') }}！</h2>
              <p class="text-gray-500 mt-2">{{ t('home.todayIs') }} {{ currentDate }}</p>
            </div>
            <el-avatar :size="80" :src="store.user?.avatar">
              {{ store.user?.name?.charAt(0) || 'U' }}
            </el-avatar>
          </div>
        </el-card>
      </el-col>
    </el-row>
    
    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <el-icon :size="40" class="text-primary"><User /></el-icon>
            <div class="ml-4">
              <p class="text-gray-500 text-sm">{{ t('home.totalUsers') }}</p>
              <p class="text-2xl font-bold">1,234</p>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <el-icon :size="40" class="text-success"><Document /></el-icon>
            <div class="ml-4">
              <p class="text-gray-500 text-sm">{{ t('home.documentCount') }}</p>
              <p class="text-2xl font-bold">567</p>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <el-icon :size="40" class="text-warning"><TrendCharts /></el-icon>
            <div class="ml-4">
              <p class="text-gray-500 text-sm">{{ t('home.visitCount') }}</p>
              <p class="text-2xl font-bold">8,901</p>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :sm="12" :md="6">
        <el-card class="stat-card">
          <div class="stat-content">
            <el-icon :size="40" class="text-danger"><ChatDotRound /></el-icon>
            <div class="ml-4">
              <p class="text-gray-500 text-sm">{{ t('home.messageCount') }}</p>
              <p class="text-2xl font-bold">234</p>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
    
    <el-row :gutter="20" class="mt-4">
      <el-col :xs="24" :lg="16">
        <el-card>
          <template #header>
            <div class="flex items-center justify-between">
              <span class="font-bold">{{ t('home.recentActivity') }}</span>
              <el-button type="primary" link>{{ t('home.viewAll') }}</el-button>
            </div>
          </template>
          <el-timeline>
            <el-timeline-item
              v-for="(activity, index) in activities"
              :key="index"
              :timestamp="activity.time"
              placement="top"
            >
              {{ activity.content }}
            </el-timeline-item>
          </el-timeline>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :lg="8">
        <el-card>
          <template #header>
            <span class="font-bold">{{ t('home.quickActions') }}</span>
          </template>
          <div class="grid grid-cols-2 gap-4">
            <el-button type="primary" :icon="User">{{ t('home.personalCenter') }}</el-button>
            <el-button type="success" :icon="Document">{{ t('home.documentManagement') }}</el-button>
            <el-button type="warning" :icon="Setting">{{ t('home.systemSettings') }}</el-button>
            <el-button type="info" :icon="ChatDotRound">{{ t('home.messageCenter') }}</el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMainStore } from '@/store/main'
import { User, Document, TrendCharts, ChatDotRound, Setting } from '@element-plus/icons-vue'

const { t } = useI18n()
const store = useMainStore()

const currentDate = computed(() => {
  const now = new Date()
  return now.toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long'
  })
})

const activities = computed(() => [
  { content: t('home.completedProfile'), time: '2024-01-15 10:30' },
  { content: t('home.createdDocument'), time: '2024-01-14 15:20' },
  { content: t('home.loginSystem'), time: '2024-01-14 09:00' },
  { content: t('home.changedPassword'), time: '2024-01-13 16:45' }
])
</script>

<style scoped>
.stat-card {
  margin-bottom: 20px;
}

.stat-content {
  display: flex;
  align-items: center;
}

.welcome-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.welcome-card .text-gray-500 {
  color: rgba(255, 255, 255, 0.8) !important;
}
</style>
