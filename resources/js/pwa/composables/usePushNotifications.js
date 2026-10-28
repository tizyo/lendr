import { ref, computed } from 'vue'
import axios from 'axios'

/**
 * Browser push notifications for the borrower PWA (Web Push / VAPID).
 *
 * iOS only allows push once the app has been added to the home screen,
 * so `needsInstall` tells the UI to ask for that first.
 */
export function usePushNotifications() {
  const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
  const isStandalone = window.matchMedia?.('(display-mode: standalone)').matches || navigator.standalone === true

  const permission = ref(supported ? Notification.permission : 'unsupported')
  const subscribed = ref(false)
  const enabledOnServer = ref(false)
  const busy = ref(false)
  const error = ref('')

  const needsInstall = computed(() => isIos && ! isStandalone)
  const canEnable = computed(() => supported && enabledOnServer.value && permission.value !== 'denied' && ! needsInstall.value)

  async function refresh() {
    if (! supported) return

    try {
      const { data } = await axios.get('/api/v1/me/push/config')
      enabledOnServer.value = !! data.data?.enabled
    } catch {
      enabledOnServer.value = false
    }

    const registration = await navigator.serviceWorker.getRegistration('/app/')
    subscribed.value = !! (await registration?.pushManager.getSubscription())
    permission.value = Notification.permission
  }

  async function enable() {
    error.value = ''
    busy.value = true

    try {
      permission.value = await Notification.requestPermission()
      if (permission.value !== 'granted') return

      const { data } = await axios.get('/api/v1/me/push/config')
      const registration = await navigator.serviceWorker.ready

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: base64UrlToUint8Array(data.data.public_key),
      })

      const json = subscription.toJSON()
      await axios.post('/api/v1/me/device-tokens', {
        platform: 'webpush',
        token: json.endpoint,
        keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
        device_name: deviceName(),
      })

      subscribed.value = true
    } catch (e) {
      error.value = 'Could not turn on notifications. Please try again.'
      console.warn('Push subscribe failed', e)
    } finally {
      busy.value = false
    }
  }

  /** Also called on logout, so the next person on this phone doesn't get these alerts. */
  async function disable() {
    busy.value = true

    try {
      const registration = await navigator.serviceWorker?.getRegistration('/app/')
      const subscription = await registration?.pushManager.getSubscription()

      if (subscription) {
        await axios.delete('/api/v1/me/device-tokens', { data: { token: subscription.endpoint } }).catch(() => {})
        await subscription.unsubscribe()
      }

      subscribed.value = false
    } finally {
      busy.value = false
    }
  }

  return { supported, permission, subscribed, enabledOnServer, needsInstall, canEnable, busy, error, refresh, enable, disable }
}

function base64UrlToUint8Array(value) {
  const padding = '='.repeat((4 - (value.length % 4)) % 4)
  const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'))

  return Uint8Array.from(raw, (c) => c.charCodeAt(0))
}

function deviceName() {
  const ua = navigator.userAgent
  const os = /android/i.test(ua) ? 'Android' : /iphone|ipad|ipod/i.test(ua) ? 'iOS' : /windows/i.test(ua) ? 'Windows' : /mac/i.test(ua) ? 'Mac' : 'Device'
  const browser = /edg/i.test(ua) ? 'Edge' : /chrome|crios/i.test(ua) ? 'Chrome' : /firefox|fxios/i.test(ua) ? 'Firefox' : /safari/i.test(ua) ? 'Safari' : 'Browser'

  return `${browser} on ${os}`
}
