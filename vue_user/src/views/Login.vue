<template>
  <div class="login-container min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600">
    <el-card class="login-card w-full max-w-md mx-4">
      <template #header>
        <div class="text-center">
          <h2 class="text-2xl font-bold text-gray-800">{{ isRegister ? t('auth.registerTitle') : t('auth.loginTitle') }}</h2>
          <p class="text-gray-500 mt-2">{{ isRegister ? t('auth.createAccount') : t('auth.welcomeBack') }}</p>
        </div>
      </template>
      
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        @submit.prevent="handleSubmit"
      >
        <el-form-item v-if="isRegister" :label="t('user.name')" prop="name">
          <el-input
            v-model="form.name"
            :placeholder="t('user.pleaseInputUsername')"
            :prefix-icon="User"
            size="large"
          />
        </el-form-item>
        
        <el-form-item :label="t('auth.email')" prop="email">
          <el-input
            v-model="form.email"
            :placeholder="t('user.pleaseInputEmail')"
            :prefix-icon="Message"
            size="large"
          />
        </el-form-item>
        
        <el-form-item :label="t('auth.password')" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            :placeholder="t('user.pleaseInputPassword')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>
        
        <el-form-item v-if="isRegister" :label="t('auth.confirmPassword')" prop="password_confirmation">
          <el-input
            v-model="form.password_confirmation"
            type="password"
            :placeholder="t('user.pleaseConfirmPassword')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>
        
        <el-form-item v-if="!isRegister">
          <div class="flex justify-between w-full">
            <el-checkbox v-model="rememberMe">{{ t('auth.rememberMe') }}</el-checkbox>
            <a href="#" class="text-primary">{{ t('auth.forgotPassword') }}</a>
          </div>
        </el-form-item>
        
        <el-form-item>
          <el-button
            type="primary"
            native-type="submit"
            :loading="loading"
            class="w-full"
            size="large"
          >
            {{ isRegister ? t('auth.register') : t('auth.login') }}
          </el-button>
        </el-form-item>
        
        <div class="text-center">
          <span class="text-gray-500">{{ isRegister ? t('auth.hasAccount') : t('auth.noAccount') }}</span>
          <a href="#" class="text-primary ml-1" @click.prevent="isRegister = !isRegister">
            {{ isRegister ? t('auth.loginNow') : t('auth.registerNow') }}
          </a>
        </div>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useMainStore } from '@/store/main'
import { ElMessage } from 'element-plus'
import { User, Lock, Message } from '@element-plus/icons-vue'
import { authApi } from '@/api'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const store = useMainStore()

const formRef = ref()
const loading = ref(false)
const rememberMe = ref(false)
const isRegister = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const validateConfirmPassword = (rule, value, callback) => {
  if (isRegister.value && value !== form.password) {
    callback(new Error(t('auth.passwordMismatch')))
  } else {
    callback()
  }
}

const rules = computed(() => ({
  name: isRegister.value ? [
    { required: true, message: t('auth.usernameRequired'), trigger: 'blur' }
  ] : [],
  email: [
    { required: true, message: t('auth.emailRequired'), trigger: 'blur' },
    { type: 'email', message: t('auth.emailInvalid'), trigger: 'blur' }
  ],
  password: [
    { required: true, message: t('auth.passwordRequired'), trigger: 'blur' },
    { min: 6, message: t('auth.passwordMinLength'), trigger: 'blur' }
  ],
  password_confirmation: isRegister.value ? [
    { required: true, message: t('user.pleaseConfirmPassword'), trigger: 'blur' },
    { validator: validateConfirmPassword, trigger: 'blur' }
  ] : []
}))

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    loading.value = true
    try {
      if (isRegister.value) {
        await authApi.register(form)
        ElMessage.success(t('auth.registerSuccess'))
        isRegister.value = false
        form.password = ''
        form.password_confirmation = ''
      } else {
        const res = await authApi.login({
          email: form.email,
          password: form.password
        })
        
        store.setToken(res.data.token)
        store.setUser(res.data.user)
        
        if (res.data.menu) {
          store.setMenu(res.data.menu)
        }
        
        if (res.data.permissions) {
          store.setPermissions(res.data.permissions)
        }
        
        ElMessage.success(t('auth.loginSuccess'))
        
        const redirect = route.query.redirect || '/home'
        router.push(redirect)
      }
    } catch (error) {
      console.error('Submit failed:', error)
    } finally {
      loading.value = false
    }
  })
}
</script>

<style scoped>
.login-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.95);
}
</style>
