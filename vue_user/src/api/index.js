import request from '@/utils/request'

export const authApi = {
  login: (data) => request.post('/v1/auth/login', data),
  register: (data) => request.post('/v1/auth/register', data),
  logout: () => request.post('/v1/auth/logout'),
  refresh: () => request.post('/v1/auth/refresh'),
  me: () => request.get('/v1/auth/me'),
  updatePassword: (data) => request.put('/v1/auth/password', data)
}

export const userApi = {
  getList: (params) => request.get('/v1/users', { params }),
  getDetail: (id) => request.get(`/v1/users/${id}`),
  create: (data) => request.post('/v1/users', data),
  update: (id, data) => request.put(`/v1/users/${id}`, data),
  delete: (id) => request.delete(`/v1/users/${id}`),
  enable: (id) => request.put(`/v1/users/${id}/enable`),
  disable: (id) => request.put(`/v1/users/${id}/disable`),
  assignRoles: (id, roleIds) => request.post(`/v1/users/${id}/roles`, { role_ids: roleIds })
}

export const tenantApi = {
  getList: (params) => request.get('/v1/tenants', { params }),
  getDetail: (id) => request.get(`/v1/tenants/${id}`),
  create: (data) => request.post('/v1/tenants', data),
  update: (id, data) => request.put(`/v1/tenants/${id}`, data),
  delete: (id) => request.delete(`/v1/tenants/${id}`),
  enable: (id) => request.put(`/v1/tenants/${id}/enable`),
  disable: (id) => request.put(`/v1/tenants/${id}/disable`),
  getConfig: (id) => request.get(`/v1/tenants/${id}/config`),
  setConfig: (id, config) => request.put(`/v1/tenants/${id}/config`, { config }),
  getRateLimit: (id) => request.get(`/v1/tenants/${id}/rate-limit`),
  setRateLimit: (id, data) => request.put(`/v1/tenants/${id}/rate-limit`, data),
  resetRateLimit: (id) => request.post(`/v1/tenants/${id}/rate-limit/reset`),
  getRateLimitStatus: (id) => request.get(`/v1/tenants/${id}/rate-limit/status`)
}

export const roleApi = {
  getList: (params) => request.get('/v1/roles', { params }),
  getDetail: (id) => request.get(`/v1/roles/${id}`),
  create: (data) => request.post('/v1/roles', data),
  update: (id, data) => request.put(`/v1/roles/${id}`, data),
  delete: (id) => request.delete(`/v1/roles/${id}`),
  assignPermissions: (id, permissionIds) => request.post(`/v1/roles/${id}/assign-permissions`, { permission_ids: permissionIds }),
  batchDelete: (ids) => request.post('/v1/roles/batch-delete', { ids })
}

export const permissionApi = {
  getList: (params) => request.get('/v1/permissions', { params }),
  getDetail: (id) => request.get(`/v1/permissions/${id}`),
  create: (data) => request.post('/v1/permissions', data),
  update: (id, data) => request.put(`/v1/permissions/${id}`, data),
  delete: (id) => request.delete(`/v1/permissions/${id}`),
  batchDelete: (ids) => request.post('/v1/permissions/batch-delete', { ids })
}

export const departmentApi = {
  getList: (params) => request.get('/v1/departments', { params }),
  getTree: () => request.get('/v1/departments/tree'),
  getDetail: (id) => request.get(`/v1/departments/${id}`),
  create: (data) => request.post('/v1/departments', data),
  update: (id, data) => request.put(`/v1/departments/${id}`, data),
  delete: (id) => request.delete(`/v1/departments/${id}`),
  batchDelete: (ids) => request.post('/v1/departments/batch-delete', { ids })
}

export const positionApi = {
  getList: (params) => request.get('/v1/positions', { params }),
  getDetail: (id) => request.get(`/v1/positions/${id}`),
  create: (data) => request.post('/v1/positions', data),
  update: (id, data) => request.put(`/v1/positions/${id}`, data),
  delete: (id) => request.delete(`/v1/positions/${id}`),
  batchDelete: (ids) => request.post('/v1/positions/batch-delete', { ids })
}

export const levelApi = {
  getList: (params) => request.get('/v1/levels', { params }),
  getDetail: (id) => request.get(`/v1/levels/${id}`),
  create: (data) => request.post('/v1/levels', data),
  update: (id, data) => request.put(`/v1/levels/${id}`, data),
  delete: (id) => request.delete(`/v1/levels/${id}`),
  batchDelete: (ids) => request.post('/v1/levels/batch-delete', { ids })
}

export const menuApi = {
  getList: (params) => request.get('/v1/menus', { params }),
  getTree: () => request.get('/v1/menus/tree'),
  getUserMenus: () => request.get('/v1/menus/user-menus'),
  getDetail: (id) => request.get(`/v1/menus/${id}`),
  create: (data) => request.post('/v1/menus', data),
  update: (id, data) => request.put(`/v1/menus/${id}`, data),
  delete: (id) => request.delete(`/v1/menus/${id}`),
  batchDelete: (ids) => request.post('/v1/menus/batch-delete', { ids })
}

export const deviceApi = {
  getList: (params) => request.get('/v1/devices', { params }),
  getDetail: (id) => request.get(`/v1/devices/${id}`),
  delete: (id) => request.delete(`/v1/devices/${id}`),
  logout: (id) => request.post(`/v1/devices/${id}/logout`),
  logoutAll: () => request.post('/v1/devices/logout-all')
}

export const healthApi = {
  check: () => request.get('/v1/health')
}

export const pageApi = {
  getPage: (code) => request.get(`/user/pages/${code}`),
  getPages: (params) => request.get('/user/pages', { params })
}

export default {
  auth: authApi,
  user: userApi,
  tenant: tenantApi,
  role: roleApi,
  permission: permissionApi,
  department: departmentApi,
  position: positionApi,
  level: levelApi,
  menu: menuApi,
  device: deviceApi,
  health: healthApi,
  page: pageApi
}
