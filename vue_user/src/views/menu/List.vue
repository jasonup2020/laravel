<template>
  <div class="menu-list-page">
    <el-card>
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">{{ t('menu.title') }}</span>
          <el-button type="primary" @click="showDialog()">
            <el-icon><Plus /></el-icon>
            {{ t('menu.addMenu') }}
          </el-button>
        </div>
      </template>
      
      <el-table
        :data="tableData"
        v-loading="loading"
        row-key="id"
        :tree-props="{ children: 'children' }"
        default-expand-all
        stripe
      >
        <el-table-column prop="name" :label="t('menu.name')" width="200" />
        <el-table-column prop="path" :label="t('menu.path')" />
        <el-table-column prop="icon" :label="t('menu.icon')" width="100">
          <template #default="{ row }">
            <el-icon v-if="row.icon"><component :is="row.icon" /></el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="sort" :label="t('menu.sort')" width="80" />
        <el-table-column prop="created_at" :label="t('common.createTime')" width="180" />
        <el-table-column :label="t('common.action')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="showDialog(row)">{{ t('common.edit') }}</el-button>
            <el-button type="success" link @click="showDialog(null, row.id)">{{ t('menu.addChildMenu') }}</el-button>
            <el-button type="danger" link @click="handleDelete(row)">{{ t('common.delete') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
    
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('menu.parentMenu')" prop="parent_id">
          <el-tree-select
            v-model="form.parent_id"
            :data="tableData"
            :props="{ label: 'name', value: 'id', children: 'children' }"
            :placeholder="t('menu.pleaseSelectParent')"
            clearable
            check-strictly
          />
        </el-form-item>
        <el-form-item :label="t('menu.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('menu.pleaseInputName')" />
        </el-form-item>
        <el-form-item :label="t('menu.path')" prop="path">
          <el-input v-model="form.path" :placeholder="t('menu.pleaseInputPath')" />
        </el-form-item>
        <el-form-item :label="t('menu.icon')" prop="icon">
          <el-input v-model="form.icon" :placeholder="t('menu.pleaseInputIcon')" />
        </el-form-item>
        <el-form-item :label="t('menu.sort')" prop="sort">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">{{ t('common.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { menuApi } from '@/api'

const { t } = useI18n()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const tableData = ref([])
const formRef = ref()

const form = reactive({
  id: null,
  name: '',
  path: '',
  icon: '',
  sort: 0,
  parent_id: null
})

const rules = {
  name: [{ required: true, message: t('menu.pleaseInputName'), trigger: 'blur' }],
  path: [{ required: true, message: t('menu.pleaseInputPath'), trigger: 'blur' }]
}

const dialogTitle = computed(() => form.id ? t('menu.editMenu') : t('menu.addMenu'))

const fetchData = async () => {
  loading.value = true
  try {
    const res = await menuApi.getTree()
    tableData.value = res.data || []
  } catch (error) {
    console.error('Failed to fetch menus:', error)
  } finally {
    loading.value = false
  }
}

const showDialog = (row = null, parentId = null) => {
  if (row) {
    form.id = row.id
    form.name = row.name
    form.path = row.path
    form.icon = row.icon
    form.sort = row.sort
    form.parent_id = row.parent_id
  } else {
    form.id = null
    form.name = ''
    form.path = ''
    form.icon = ''
    form.sort = 0
    form.parent_id = parentId
  }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    
    submitting.value = true
    try {
      if (form.id) {
        await menuApi.update(form.id, form)
        ElMessage.success(t('menu.updateSuccess'))
      } else {
        await menuApi.create(form)
        ElMessage.success(t('menu.createSuccess'))
      }
      dialogVisible.value = false
      fetchData()
    } catch (error) {
      console.error('Submit failed:', error)
    } finally {
      submitting.value = false
    }
  })
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(t('menu.deleteConfirm'), t('common.tip'), {
      type: 'warning'
    })
    await menuApi.delete(row.id)
    ElMessage.success(t('menu.deleteSuccess'))
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete failed:', error)
    }
  }
}

onMounted(() => {
  fetchData()
})
</script>
