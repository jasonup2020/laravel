import { createRouter, createWebHistory } from 'vue-router'
import { useMainStore } from '@/store/main'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false, title: '登录' }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/home'
      },
      {
        path: 'home',
        name: 'Home',
        component: () => import('@/views/Home.vue'),
        meta: { title: '首页' }
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/Profile.vue'),
        meta: { title: '个人中心' }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('@/views/Settings.vue'),
        meta: { title: '设置' }
      },
      {
        path: 'users',
        name: 'UserList',
        component: () => import('@/views/user/List.vue'),
        meta: { title: '用户管理', permission: 'user.index' }
      },
      {
        path: 'roles',
        name: 'RoleList',
        component: () => import('@/views/role/List.vue'),
        meta: { title: '角色管理', permission: 'role.index' }
      },
      {
        path: 'departments',
        name: 'DepartmentList',
        component: () => import('@/views/department/List.vue'),
        meta: { title: '部门管理', permission: 'department.index' }
      },
      {
        path: 'menus',
        name: 'MenuList',
        component: () => import('@/views/menu/List.vue'),
        meta: { title: '菜单管理', permission: 'menu.index' }
      },
      {
        path: 'page/:code',
        name: 'DynamicPage',
        component: () => import('@/components/DragPage.vue'),
        meta: { title: '动态页面' }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound.vue'),
    meta: { title: '页面未找到' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - 用户中心` : '用户中心'
  
  const store = useMainStore()
  
  if (to.meta.requiresAuth && !store.isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else if (to.name === 'Login' && store.isLoggedIn) {
    next({ name: 'Home' })
  } else if (to.meta.permission && !store.hasPermission(to.meta.permission)) {
    next({ name: 'Home' })
  } else {
    next()
  }
})

export default router
