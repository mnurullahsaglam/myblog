import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import preset from './theme/preset'
import 'primeicons/primeicons.css'

createInertiaApp({
  title: (title) => (title ? `${title} — Admin` : 'Admin'),
  // Not eager: each page becomes its own chunk, so a visitor downloads the
  // page they asked for rather than all forty.
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.vue')

    return pages[`./pages/${name}.vue`]()
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .use(PrimeVue, {
        theme: {
          preset,
          options: {
            darkModeSelector: '.dark',
            cssLayer: { name: 'primevue', order: 'theme, base, primevue' },
          },
        },
      })
      .use(ToastService)
      .use(ConfirmationService)
      .directive('tooltip', Tooltip)
      .mount(el)
  },
  progress: { color: '#C9BE6E' },
})
