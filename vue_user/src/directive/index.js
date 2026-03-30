import { useMainStore } from '@/store/main'

export default {
  install(app) {
    app.directive('permission', {
      mounted(el, binding) {
        const store = useMainStore()
        const permission = binding.value
        if (permission && !store.hasPermission(permission)) {
          el.parentNode?.removeChild(el)
        }
      }
    })

    app.directive('loading', {
      mounted(el, binding) {
        if (binding.value) {
          el.classList.add('loading')
          const overlay = document.createElement('div')
          overlay.className = 'loading-overlay'
          overlay.innerHTML = '<div class="loading-spinner"></div>'
          el.appendChild(overlay)
        }
      },
      updated(el, binding) {
        const overlay = el.querySelector('.loading-overlay')
        if (binding.value && !overlay) {
          el.classList.add('loading')
          const newOverlay = document.createElement('div')
          newOverlay.className = 'loading-overlay'
          newOverlay.innerHTML = '<div class="loading-spinner"></div>'
          el.appendChild(newOverlay)
        } else if (!binding.value && overlay) {
          el.classList.remove('loading')
          overlay.remove()
        }
      }
    })
  }
}
