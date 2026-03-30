import { defineStore } from 'pinia'

export const useMainStore = defineStore('main', {
  state: () => ({
    token: localStorage.getItem('token') || '',
    user: JSON.parse(localStorage.getItem('user') || '{}'),
    menu: JSON.parse(localStorage.getItem('menu') || '[]'),
    permissions: JSON.parse(localStorage.getItem('permissions') || '[]'),
    theme: localStorage.getItem('theme') || 'light',
    lang: localStorage.getItem('lang') || 'zh_CN',
    pageConfig: {}
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    hasPermission: (state) => (permission) => state.permissions.includes(permission)
  },
  actions: {
    setToken(token) {
      this.token = token
      localStorage.setItem('token', token)
    },
    setUser(data) {
      this.user = data
      localStorage.setItem('user', JSON.stringify(data))
    },
    setMenu(data) {
      this.menu = data
      localStorage.setItem('menu', JSON.stringify(data))
    },
    setPermissions(data) {
      this.permissions = data
      localStorage.setItem('permissions', JSON.stringify(data))
    },
    setTheme(theme) {
      this.theme = theme
      localStorage.setItem('theme', theme)
      document.documentElement.setAttribute('data-theme', theme)
    },
    setLang(lang) {
      this.lang = lang
      localStorage.setItem('lang', lang)
    },
    setPageConfig(config) {
      this.pageConfig = config
    },
    logout() {
      this.token = ''
      this.user = {}
      this.menu = []
      this.permissions = []
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('menu')
      localStorage.removeItem('permissions')
    }
  }
})
