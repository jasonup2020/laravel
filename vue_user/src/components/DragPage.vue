<template>
  <div class="p-4">
    <component
      v-for="item in list"
      :key="item.id"
      :is="getCom(item.type)"
      v-bind="item.props"
      v-permission="item.permission"
      :style="item.style"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import request from '@/utils/request'

const route = useRoute()
const props = defineProps({
  data: Object
})

const list = ref(props.data?.list || [])

const getCom = (type) => {
  const map = {
    text: 'h3',
    button: 'el-button',
    input: 'el-input',
    form: 'el-form',
    card: 'el-card',
    table: 'el-table',
    image: 'el-image',
    divider: 'el-divider',
    tabs: 'el-tabs'
  }
  return map[type] || 'div'
}

onMounted(async () => {
  if (!props.data && route.params.code) {
    try {
      const res = await request.get(`/user/pages/${route.params.code}`)
      list.value = res.data?.list || []
    } catch (error) {
      console.error('Failed to load page data:', error)
    }
  }
})
</script>
