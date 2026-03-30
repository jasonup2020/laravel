<template>
  <div class="user-list-page">
    <el-card>
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">{{ t('user.userManagement') }}</span>
          <el-button type="primary" @click="showDialog()">
            <el-icon><Plus /></el-icon>
            {{ t('user.addUser') }}
          </el-button>
        </div>
      </template>
      
      <el-form :inline="true" :model="searchForm" class="mb-4">
        <el-form-item :label="t('common.keyword')">
          <el-input v-model="searchForm.keyword" :placeholder="t('user.usernameOrEmail')" clearable />
        </el-form-item>
        <el-form-item :label="t('common.status')">
          <el-select v-model="searchForm.status" :placeholder="t('common.pleaseSelect')" clearable>
            <el-option :label="t('common.enable')" value="active" />
            <el-option :label="t('common.disable')" value="inactive" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchData">{{ t('common.search') }}</el-button>
          <el-button @click="resetSearch">{{ t('common.reset') }}</el-button>
        </el-form-item>
      </el-form>
      
      <el-table :data="tableData" v-loading="loading" stripe>
        <el-table-column prop="id" :label="t('common.id')" width="80" />
        <el-table-column prop="name" :label="t('user.name')" />
        <el-table-column prop="email" :label="t('user.email')" />
        <el-table-column prop="department.name" :label="t('user.department')" />
        <el-table-column prop="position.name" :label="t('user.position')" />
        <el-table-column prop="status" :label="t('common.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'">
              {{ row.status === 'active' ? t('common.enable') : t('common.disable') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" :label="t('common.createTime')" width="180" />
        <el-table-column :label="t('common.action')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="showDialog(row)">{{ t('common.edit') }}</el-button>
            <el-button 
              :type="row.status === 'active' ? 'warning' : 'success'" 
              link 
              @click="toggleStatus(row)"
            >
              {{ row.status === 'active' ? t('common.disable') : t('common.enable') }}
            </el-button>
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
    
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item :label="t('user.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('user.pleaseInputUsername')" />
        </el-form-item>
        <el-form-item :label="t('user.email')" prop="email">
          <el-input v-model="form.email" :placeholder="t('user.pleaseInputEmail')" />
        </el-form-item>
        <el-form-item v-if="!form.id" :label="t('user.password')" prop="password">
          <el-input v-model="form.password" type="password" :placeholder="t('user.pleaseInputPassword')" show-password />
        </el-form-item>
        <el-form-item :label="t('user.department')" prop="department_id">
          <el-tree-select
            v-model="form.department_id"
            :data="departmentTree"
            :props="{ label: 'name', value: 'id' }"
            :placeholder="t('user.pleaseSelectDepartment')"
            clearable
            check-strictly
          />
        </el-form-item>
        <el-form-item :label="t('user.position')" prop="position_id">
          <el-select v-model="form.position_id" :placeholder="t('user.pleaseSelectPosition')" clearable>
            <el-option v-for="item in positionList" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('user.level')" prop="level_id">
          <el-select v-model="form.level_id" :placeholder="t('user.pleaseSelectLevel')" clearable>
            <el-option v-for="item in levelList" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('user.role')" prop="role_ids">
          <el-select v-model="form.role_ids" multiple :placeholder="t('user.pleaseSelectRole')" clearable>
            <el-option v-for="item in roleList" :key="item.id" :label="item.display_name || item.name" :value="item.id" />
          </el-select>
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
import { userApi, departmentApi, positionApi, levelApi, roleApi } from '@/api'

const { t } = useI18n()

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const tableData = ref([])
const formRef = ref()

const searchForm = reactive({
  keyword: '',
  status: ''
})

const pagination = reactive({
  page: 1,
  per_page: 20,
  total: 0
})

const form = reactive({
  id: null,
  name: '',
  email: '',
  password: '',
  department_id: null,
  position_id: null,
  level_id: null,
  role_ids: []
})

const rules = {
  name: [{ required: true, message: t('auth.usernameRequired'), trigger: 'blur' }],
  email: [
    { required: true, message: t('auth.emailRequired'), trigger: 'blur' },
    { type: 'email', message: t('auth.emailInvalid'), trigger: 'blur' }
  ],
  password: [
    { required: true, message: t('auth.passwordRequired'), trigger: 'blur' },
    { min: 6, message: t('auth.passwordMinLength'), trigger: 'blur' }
  ]
}

const departmentTree = ref([])
const positionList = ref([])
const levelList = ref([])
const roleList = ref([])

const dialogTitle = computed(() => form.id ? t('user.editUser') : t('user.addUser'))

const fetchData = async () => {
  loading.value = true
  try {
    const res = await userApi.getList({
      page: pagination.page,
      per_page: pagination.per_page,
      ...searchForm
    })
    tableData.value = res.data?.data || []
    pagination.total = res.data?.meta?.total || 0
  } catch (error) {
    console.error('Failed to fetch users:', error)
  } finally {
    loading.value = false
  }
}

const fetchOptions = async () => {
  try {
    const [deptRes, posRes, levelRes, roleRes] = await Promise.all([
      departmentApi.getTree(),
      positionApi.getList({ per_page: 100 }),
      levelApi.getList({ per_page: 100 }),
      roleApi.getList({ per_page: 100 })
    ])
    departmentTree.value = deptRes.data || []
    positionList.value = posRes.data?.data || []
    levelList.value = levelRes.data?.data || []
    roleList.value = roleRes.data?.data || []
  } catch (error) {
    console.error('Failed to fetch options:', error)
  }
}

const resetSearch = () => {
  searchForm.keyword = ''
  searchForm.status = ''
  pagination.page = 1
  fetchData()
}

const showDialog = (row = null) => {
  if (row) {
    form.id = row.id
    form.name = row.name
    form.email = row.email
    form.password = ''
    form.department_id = row.department_id
    form.position_id = row.position_id
    form.level_id = row.level_id
    form.role_ids = row.roles?.map(r => r.id) || []
  } else {
    form.id = null
    form.name = ''
    form.email = ''
    form.password = ''
    form.department_id = null
    form.position_id = null
    form.level_id = null
    form.role_ids = []
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
        const { role_ids, ...updateData } = form
        await userApi.update(form.id, updateData)
        if (role_ids.length > 0) {
          await userApi.assignRoles(form.id, role_ids)
        }
        ElMessage.success(t('user.updateSuccess'))
      } else {
        const { role_ids, ...createData } = form
        const res = await userApi.create(createData)
        if (role_ids.length > 0 && res.data?.id) {
          await userApi.assignRoles(res.data.id, role_ids)
        }
        ElMessage.success(t('user.createSuccess'))
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

const toggleStatus = async (row) => {
  try {
    if (row.status === 'active') {
      await userApi.disable(row.id)
      ElMessage.success(t('user.disableSuccess'))
    } else {
      await userApi.enable(row.id)
      ElMessage.success(t('user.enableSuccess'))
    }
    fetchData()
  } catch (error) {
    console.error('Toggle status failed:', error)
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(t('user.deleteConfirm'), t('common.tip'), {
      type: 'warning'
    })
    await userApi.delete(row.id)
    ElMessage.success(t('user.deleteSuccess'))
    fetchData()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('Delete failed:', error)
    }
  }
}

onMounted(() => {
  fetchData()
  fetchOptions()
})
</script>
