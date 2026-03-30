import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import pinia from './store'
import i18n from './lang'
import directive from './directive'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import './styles/index.css'

const app = createApp(App)
app.use(router).use(pinia).use(i18n).use(directive).use(ElementPlus)
app.mount('#app')
