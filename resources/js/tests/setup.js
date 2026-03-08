import { config } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach } from 'vitest'

beforeEach(() => {
    setActivePinia(createPinia())
})

// Global stubs for Inertia components in tests
config.global.stubs = {
    'inertia-link': true,
    Link: true,
    Head: true,
}
