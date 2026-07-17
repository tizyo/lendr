import './bootstrap'
import { createApp, h } from 'vue'
import { createInertiaApp, usePage } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { createPinia } from 'pinia'
import { useUiStore } from '@/admin/stores/ui.js'

const appName = import.meta.env.VITE_APP_NAME || 'LENDR'

createInertiaApp({
    title: (title) => `${title} — ${appName}`,
    resolve: (name) => {
        const adminPages    = import.meta.glob('./admin/pages/**/*.vue')
        const landlordPages = import.meta.glob('./landlord/pages/**/*.vue')

        // Landlord panel pages live under resources/js/landlord/pages/
        if (name.startsWith('landlord/')) {
            const key = `./landlord/pages/${name.replace('landlord/', '')}.vue`
            if (landlordPages[key]) return landlordPages[key]()
        }

        return resolvePageComponent(`./admin/pages/${name}.vue`, adminPages)
    },
    setup({ el, App, props, plugin }) {
        const pinia = createPinia()
        const app   = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)

        // Subscribe to real-time notifications once Echo is ready and user is authed
        app.mixin({
            mounted() {
                if (this.$root !== this) return   // only run on root instance
                const page = usePage()
                const ui   = useUiStore()

                const subscribe = () => {
                    const userId = page.props.auth?.user?.id
                    if (!userId || !window.Echo) return
                    window.Echo.private(`staff.${userId}`)
                        .listen('.notification.new', () => {
                            ui.incrementUnread()
                        })
                }

                // Echo may load asynchronously; retry briefly
                let attempts = 0
                const interval = setInterval(() => {
                    attempts++
                    if (window.Echo) { subscribe(); clearInterval(interval) }
                    if (attempts > 20) clearInterval(interval)
                }, 500)
            },
        })

        return app.mount(el)
    },
    progress: {
        color: '#2563EB',
        includeCSS: true,
        showSpinner: true,
    },
})
