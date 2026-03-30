<template>
  <div class="profile-page">
    <el-row :gutter="20">
      <el-col :xs="24" :lg="8">
        <el-card>
          <div class="text-center">
            <el-avatar :size="120" :src="store.user?.avatar">
              {{ store.user?.name?.charAt(0) || 'U' }}
            </el-avatar>
            <h3 class="mt-4 text-xl font-bold">{{ store.user?.name || t('user.name') }}</h3>
            <p class="text-gray-500">{{ store.user?.email || 'user@example.com' }}</p>
            <el-tag class="mt-2" type="success">{{ t('user.certified') }}</el-tag>
          </div>
          
          <el-divider />
          
          <div class="user-info">
            <div class="info-item">
              <span class="label">{{ t('user.name') }}:</span>
              <span class="value">{{ store.user?.name || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">{{ t('user.email') }}:</span>
              <span class="value">{{ store.user?.email || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">{{ t('user.phone') }}:</span>
              <span class="value">{{ store.user?.phone || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">{{ t('user.department') }}:</span>
              <span class="value">{{ store.user?.department?.name || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">{{ t('user.position') }}:</span>
              <span class="value">{{ store.user?.position?.name || '-' }}</span>
            </div>
            <div class="info-item">
              <span class="label">{{ t('user.registerTime') }}:</span>
              <span class="value">{{ store.user?.created_at || '-' }}</span>
            </div>
          </div>
        </el-card>
      </el-col>
      
      <el-col :xs="24" :lg="16">
        <el-card>
          <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('user.basicInfo')" name="basic">
              <el-form
                ref="basicFormRef"
                :model="basicForm"
                :rules="basicRules"
                label-width="100px"
              >
                <el-form-item :label="t('user.name')" prop="name">
                  <el-input v-model="basicForm.name" :placeholder="t('user.pleaseInputUsername')" />
                </el-form-item>
                
                <el-form-item :label="t('user.email')" prop="email">
                  <el-input v-model="basicForm.email" :placeholder="t('user.pleaseInputEmail')" />
                </el-form-item>
                
                <el-form-item :label="t('user.phone')" prop="phone">
                  <el-input v-model="basicForm.phone" :placeholder="t('user.pleaseInputPhone')" />
                </el-form-item>
                
                <el-form-item :label="t('user.avatar')">
                  <el-upload
                    class="avatar-uploader"
                    action="/api/v1/user/avatar"
                    :show-file-list="false"
                    :on-success="handleAvatarSuccess"
                  >
                    <img v-if="basicForm.avatar" :src="basicForm.avatar" class="avatar" />
                    <el-icon v-else class="avatar-uploader-icon"><Plus /></el-icon>
                  </el-upload>
                </el-form-item>
                
                <el-form-item>
                  <el-button type="primary" @click="saveBasicInfo" :loading="saving">
                    {{ t('common.save') }}
                  </el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>
            
            <el-tab-pane :label="t('user.changePassword')" name="password">
              <el-form
                ref="passwordFormRef"
                :model="passwordForm"
                :rules="passwordRules"
                label-width="100px"
              >
                <el-form-item :label="t('user.currentPassword')" prop="oldPassword">
                  <el-input
                    v-model="passwordForm.oldPassword"
                    type="password"
                    :placeholder="t('user.pleaseInputCurrentPassword')"
                    show-password
                  />
                </el-form-item>
                
                <el-form-item :label="t('user.newPassword')" prop="newPassword">
                  <el-input
                    v-model="passwordForm.newPassword"
                    type="password"
                    :placeholder="t('user.pleaseInputNewPassword')"
                    show-password
                  />
                </el-form-item>
                
                <el-form-item :label="t('user.confirmNewPassword')" prop="confirmPassword">
                  <el-input
                    v-model="passwordForm.confirmPassword"
                    type="password"
                    :placeholder="t('user.pleaseConfirmPassword')"
                    show-password
                  />
                </el-form-item>
                
                <el-form-item>
                  <el-button type="primary" @click="changePassword" :loading="changingPassword">
                    {{ t('user.changePassword') }}
                  </el-button>
                </el-form-item>
              </el-form>
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMainStore } from '@/store/main'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { authApi } from '@/api'

const { t } = useI18n()
const store = useMainStore()

const activeTab = ref('basic')
const basicFormRef = ref()
const passwordFormRef = ref()
const saving = ref(false)
const changingPassword = ref(false)

const basicForm = reactive({
  name: '',
  email: '',
  phone: '',
  avatar: ''
})

const passwordForm = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const basicRules = {
  name: [{ required: true, message: t('auth.usernameRequired'), trigger: 'blur' }],
  email: [
    { required: true, message: t('auth.emailRequired'), trigger: 'blur' },
    { type: 'email', message: t('auth.emailInvalid'), trigger: 'blur' }
  ],
  phone: [
    { pattern: /^1[3-9]\d{9}$/, message: t('validation.phoneFormat'), trigger: 'blur' }
  ]
}

const validateConfirmPassword = (rule, value, callback) => {
  if (value !== passwordForm.newPassword) {
    callback(new Error(t('auth.passwordMismatch')))
  } else {
    callback()
  }
}

const passwordRules = {
  oldPassword: [{ required: true, message: t('user.pleaseInputCurrentPassword'), trigger: 'blur' }],
  newPassword: [
    { required: true, message: t('user.pleaseInputNewPassword'), trigger: 'blur' },
    { min: 6, message: t('auth.passwordMinLength'), trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: t('user.pleaseConfirmPassword'), trigger: 'blur' },
    { validator: validateConfirmPassword, trigger: 'blur' }
  ]
}

onMounted(async () => {
  try {
    const res = await authApi.me()
    if (res.data) {
      store.setUser(res.data)
      basicForm.name = res.data.name || ''
      basicForm.email = res.data.email || ''
      basicForm.phone = res.data.phone || ''
      basicForm.avatar = res.data.avatar || ''
    }
  } catch (error) {
    console.error('Failed to fetch user info:', error)
  }
})

const handleAvatarSuccess = (response) => {
  if (response.data?.url) {
    basicForm.avatar = response.data.url
    ElMessage.success(t('user.avatarUploaded'))
  }
}

const saveBasicInfo = async () => {
  if (!basicFormRef.value) return
  
  await basicFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    saving.value = true
    try {
      const res = await authApi.updateProfile(basicForm)
      store.setUser(res.data)
      ElMessage.success(t('user.profileUpdated'))
    } catch (error) {
      console.error('Save failed:', error)
    } finally {
      saving.value = false
    }
  })
}

const changePassword = async () => {
  if (!passwordFormRef.value) return
  
  await passwordFormRef.value.validate(async (valid) => {
    if (!valid) return
    
    changingPassword.value = true
    try {
      await authApi.updatePassword({
        old_password: passwordForm.oldPassword,
        new_password: passwordForm.newPassword,
        new_password_confirmation: passwordForm.confirmPassword
      })
      ElMessage.success(t('user.passwordChanged'))
      passwordFormRef.value.resetFields()
    } catch (error) {
      console.error('Change password failed:', error)
    } finally {
      changingPassword.value = false
    }
  })
}
</script>

<style scoped>
.user-info {
  padding: 0 20px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  padding: 12px 0;
  border-bottom: 1px solid #eee;
}

.info-item:last-child {
  border-bottom: none;
}

.info-item .label {
  color: #666;
}

.info-item .value {
  color: #333;
  font-weight: 500;
}

.avatar-uploader {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  width: 100px;
  height: 100px;
}

.avatar-uploader:hover {
  border-color: #409eff;
}

.avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
  width: 100px;
  height: 100px;
  line-height: 100px;
  text-align: center;
}

.avatar {
  width: 100px;
  height: 100px;
  display: block;
  object-fit: cover;
}
</style>
