import './bootstrap'
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { createPinia } from 'pinia'

const appName = import.meta.env.VITE_APP_NAME || 'LENDR'

createInertiaApp({
    title: (title) => `${title} — ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pwa/pages/${name}.vue`,
            import.meta.glob('./pwa/pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia()

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .mount(el)
    },
    progress: {
        color: '#10B981',
        includeCSS: true,
        showSpinner: true,
    },
})
