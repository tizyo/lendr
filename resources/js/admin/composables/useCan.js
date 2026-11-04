import { usePage } from '@inertiajs/vue3'

/**
 * Permission check against the signed-in staff member's permissions (shared by
 * HandleInertiaRequests). Hide what the server would refuse anyway.
 *
 *   const can = useCan()
 *   v-if="can('billing.manage')"
 */
export function useCan() {
  const page = usePage()

  return (permission) => (page.props.auth?.user?.permissions ?? []).includes(permission)
}
