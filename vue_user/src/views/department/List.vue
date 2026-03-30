<template>
  <div class="department-list-page">
    <el-card>
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">{{ t('department.title') }}</span>
          <el-button type="primary" @click="showDialog()">
            <el-icon><Plus /></el-icon>
            {{ t('department.addDepartment') }}
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
        <el-table-column prop="name" :label="t('department.name')" />
        <el-table-column prop="description" :label="t('common.description')" />
        <el-table-column prop="created_at" :label="t('common.createTime')" width="180" />
        <el-table-column :label="t('common.action')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="showDialog(row)">{{ t('common.edit') }}</el-button>
            <el-button type="success" link @click="showDialog(null, row.id)">{{ t('department.addChildDepartment') }}</el-button>
            <el-button type="danger" link @click="handleDelete(row)">{{ t('common.delete') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
    
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('department.parentDepartment')" prop="parent_id">
          <el-tree-select
            v-model="form.parent_id"
            :data="tableData"
            :props="{ label: 'name', value: 'id', children: 'children' }"
            :placeholder="t('department.pleaseSelectParent')"
            clearable
            check-strictly
          />
        </el-form-item>
        <el-form-item :label="t('department.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('department.pleaseInputName')" />
        </el-form-item>
        <el-form-item :label="t('common.description')" prop="description">
          <el-input v-model="form.description" type="textarea" :placeholder="t('department.pleaseInputDescription')" />
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
import { departmentApi } from '@/api'

const { t } = useI18n()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const tableData = ref([])
const formRef = ref()

const form = reactive({
  id: null,
  name: '',
  parent_id: null,
  description: ''
})

const rules = {
  name: [{ required: true, message: t('department.pleaseInputName'), trigger: 'blur' }]
}

const dialogTitle = computed(() => form.id ? t('department.editDepartment') : t('department.addDepartment'))

const fetchData = async () => {
  loading.value = true
  try {
    const res = await departmentApi.getTree()
    tableData.value = res.data || []
  } catch (error) {
    console.error('Failed to fetch departments:', error)
  } finally {
    loading.value = false
  }
}

const showDialog = (row = null, parentId = null) => {
  if (row) {
    form.id = row.id
    form.name = row.name
    form.parent_id = row.parent_id
    form.description = row.description
  } else {
    form.id = null
    form.name = ''
    form.parent_id = parentId
    form.description = ''
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
        await departmentApi.update(form.id, form)
        ElMessage.success(t('department.updateSuccess'))
      } else {
        await departmentApi.create(form)
        ElMessage.success(t('department.createSuccess'))
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
    await ElMessageBox.confirm(t('department.deleteConfirm'), t('common.tip'), {
      type: 'warning'
    })
    await departmentApi.delete(row.id)
    ElMessage.success(t('department.deleteSuccess'))
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
