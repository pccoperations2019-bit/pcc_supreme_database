import '../css/app.css'
import 'leaflet/dist/leaflet.css'
import 'vue-sonner/style.css'

import './bootstrap'

import { createInertiaApp } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { Toaster } from '@/components/ui/sonner'
import { initInertiaFlashToasts } from '@/lib/flashToasts'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'

createInertiaApp({
  title: title => `${title} - ${appName}`,
  resolve: name =>
    resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    initInertiaFlashToasts(router, props?.initialPage)

    return createApp({
      render: () => h('div', [h(App, props), h(Toaster)]),
    })
      .use(plugin)
      .use(ZiggyVue)
      .mount(el)
  },
  progress: {
    color: '#4B5563',
  },
})
