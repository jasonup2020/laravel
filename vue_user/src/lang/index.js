import { createI18n } from 'vue-i18n'
import zh_CN from './locales/zh_CN'
import en from './locales/en'

const messages = {
  zh_CN,
  en
}

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('lang') || 'zh_CN',
  fallbackLocale: 'zh_CN',
  messages
})

export function __(key, params = {}) {
  return i18n.global.t(key, params)
}

export default i18n
