<template>
  <div class="role-list-page">
    <el-card>
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">{{ t('role.title') }}</span>
          <el-button type="primary" @click="showDialog()">
            <el-icon><Plus /></el-icon>
            {{ t('role.addRole') }}
          </el-button>
        </div>
      </template>
      
      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="id" :label="t('common.id')" width="80" />
        <el-table-column prop="name" :label="t('role.name')" />
        <el-table-column prop="display_name" :label="t('role.displayName')" />
        <el-table-column prop="description" :label="t('common.description')" />
        <el-table-column prop="created_at" :label="t('common.createTime')" width="180" />
        <el-table-column :label="t('common.action')" width="250" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="showDialog(row)">{{ t('common.edit') }}</el-button>
            <el-button type="success" link @click="showPermissionDialog(row)">{{ t('role.assignPermission') }}</el-button>
            <el-button type="danger" link @click="handleDelete(row)">{{ t('common.delete') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
      
      <el-pagination
        class="mt-4"
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchData"
        @current-change="fetchData"
      />
    </el-card>
    
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('role.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('role.pleaseInputName')" />
        </el-form-item>
        <el-form-item :label="t('role.displayName')" prop="display_name">
          <el-input v-model="form.display_name" :placeholder="t('role.pleaseInputDisplayName')" />
        </el-form-item>
        <el-form-item :label="t('common.description')" prop="description">
          <el-input v-model="form.description" type="textarea" :placeholder="t('role.pleaseInputDescription')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">{{ t('common.confirm') }}</el-button>
      </template>
    </el-dialog>
    
    <el-dialog v-model="permissionDialogVisible" :title="t('role.assignPermissionTitle')" width="600px">
      <el-tree
        ref="permissionTreeRef"
        :data="permissionTree"
        :props="{ label: 'display_name', children: 'children' }"
        show-checkbox
        node-key="id"
        default-expand-all
      />
      <template #footer>
        <el-button @click="permissionDialogVisible = false">{{ t('common.cancel') }}</el-button>
        <el-button type="primary" @click="handleAssignPermissions" :loading="assigning">{{ t('common.confirm') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { roleApi, permissionApi } from '@/api'

const { t } = useI18n()

const loading = ref(false)
const submitting = ref(false)
const assigning = ref(false)
const dialogVisible = ref(false)
const permissionDialogVisible = ref(false)
const tableData = ref([])
const formRef = ref()
const permissionTreeRef = ref()
const currentRole = ref(null)

const pagination = reactive({
  page: 1,
  per_page: 20,
  total: 0
})

const form = reactive({
  id: null,
  name: '',
  display_name: '',
  description: ''
})

const rules = {
  name: [{ required: true, message: t('role.pleaseInputName'), trigger: 'blur' }],
  display_name: [{ required: true, message: t('role.pleaseInputDisplayName'), trigger: 'blur' }]
}

const permissionTree = ref([])

const dialogTitle = computed(() => form.id ? t('role.editRole') : t('role.addRole'))

const fetchData = async () => {
  loading.value = true
  try {
    const res = await roleApi.getList({
      page: pagination.page,
      per_page: pagination.per_page
    })
    tableData.value = res.data?.data || []
    pagination.total = res.data?.meta?.total || 0
  } catch (error) {
    console.error('Failed to fetch roles:', error)
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const res = await permissionApi.getList({ per_page: 1000 })
    permissionTree.value = res.data?.data || []
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
  }
}

const showDialog = (row = null) => {
  if (row) {
    form.id = row.id
    form.name = row.name
    form.display_name = row.display_name
    form.description = row.description
  } else {
    form.id = null
    form.name = ''
    form.display_name = ''
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
        await roleApi.update(form.id, form)
        ElMessage.success(t('role.updateSuccess'))
      } else {
        await roleApi.create(form)
        ElMessage.success(t('role.createSuccess'))
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

const showPermissionDialog = async (row) => {
  currentRole.value = row
  permissionDialogVisible.value = true
  
  try {
    const res = await roleApi.getDetail(row.id)
    const permissionIds = res.data?.permissions?.map(p => p.id) || []
    setTimeout(() => {
      permissionTreeRef.value?.setCheckedKeys(permissionIds)
    }, 100)
  } catch (error) {
    console.error('Failed to fetch role permissions:', error)
  }
}

const handleAssignPermissions = async () => {
  if (!currentRole.value) return
  
  assigning.value = true
  try {
    const permissionIds = permissionTreeRef.value?.getCheckedKeys() || []
    await roleApi.assignPermissions(currentRole.value.id, permissionIds)
    ElMessage.success(t('role.assignSuccess'))
    permissionDialogVisible.value = false
  } catch (error) {
    console.error('Assign permissions failed:', error)
  } finally {
    assigning.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(t('role.deleteConfirm'), t('common.tip'), {
      type: 'warning'
    })
    await roleApi.delete(row.id)
    ElMessage.success(t('role.deleteSuccess'))
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete failed:', error)
    }
  }
}

onMounted(() => {
  fetchData()
  fetchPermissions()
})
</script>
