<template>
  <LandlordLayout :title="tenant?.name ?? 'Tenant'">
    <Link :href="route('landlord.tenants')" class="inline-block text-sm text-neutral-500 hover:text-neutral-800 mb-4">← All tenants</Link>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="loadError" class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ loadError }}</div>

    <div v-else class="space-y-6">
      <!-- Identity -->
      <div class="flex flex-wrap items-center gap-2">
        <span :class="planBadge(tenant.plan)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ tenant.plan }}</span>
        <span :class="statusBadge(tenant.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ tenant.status }}</span>
        <span class="text-xs text-neutral-400 font-mono">{{ tenant.slug }}</span>
      </div>

      <!-- Unverified workspace -->
      <div v-if="!tenant.email_verified_at" class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-amber-800">
          <p class="font-semibold">Email not verified</p>
          <p>Nobody in this workspace can log in until its email address is verified.</p>
        </div>
        <button @click="openVerify" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
          Verify email
        </button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Staff</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.staff_active }} <span class="text-sm font-normal text-neutral-400">active of {{ stats.staff_total }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrowers</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.borrowers.toLocaleString() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Active loans</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.active_loans.toLocaleString() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Outstanding</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1 truncate">{{ tenant.currency }} {{ formatMoney(stats.outstanding_balance) }}</p>
        </div>
      </div>

      <!-- Details -->
      <div class="bg-white rounded-xl border border-neutral-200 p-6">
        <h3 class="font-semibold text-neutral-900 mb-4">Workspace</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
          <div>
            <dt class="text-xs text-neutral-500">Staff login</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.login_url }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Domain</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.domains.join(', ') || 'Shared portal' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Admin email</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.admin_email ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Email verified</dt>
            <dd v-if="tenant.email_verified_at" class="text-neutral-900">{{ formatDate(tenant.email_verified_at) }}</dd>
            <dd v-else class="text-amber-700 font-medium">Not verified</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Currency / timezone</dt>
            <dd class="text-neutral-900">{{ tenant.currency }} · {{ tenant.timezone }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Created</dt>
            <dd class="text-neutral-900">{{ formatDate(tenant.created_at) }}</dd>
          </div>
          <div v-if="tenant.trial_ends_at">
            <dt class="text-xs text-neutral-500">Trial ends</dt>
            <dd class="text-neutral-900">{{ formatDate(tenant.trial_ends_at) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Last sign-in</dt>
            <dd v-if="stats.last_login" class="text-neutral-900">
              <button @click="showUserActivity(stats.last_login.user_id)" class="hover:underline">{{ stats.last_login.name }}</button>
              · <span :title="formatDateTime(stats.last_login.at)">{{ timeAgo(stats.last_login.at) }}</span>
            </dd>
            <dd v-else class="text-neutral-400">No sign-in recorded yet</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Last active</dt>
            <dd v-if="stats.last_seen_at" class="text-neutral-900" :title="formatDateTime(stats.last_seen_at)">{{ timeAgo(stats.last_seen_at) }}</dd>
            <dd v-else class="text-neutral-400">—</dd>
          </div>
        </dl>
      </div>

      <!-- Notice -->
      <div v-if="notice" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ notice }}</div>

      <!-- Users -->
      <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between gap-3">
          <h3 class="font-semibold text-neutral-900">Users</h3>
          <div class="flex items-center gap-2">
            <button
              @click="openImpersonate(activeUsers[0])"
              :disabled="activeUsers.length === 0"
              class="border border-indigo-200 text-indigo-700 hover:bg-indigo-50 text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap disabled:opacity-40 disabled:hover:bg-transparent"
            >
              Sign in as…
            </button>
            <button @click="openAdd" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
              + Add User
            </button>
          </div>
        </div>
        <p v-if="activeUsers.length === 0" class="px-6 py-3 text-sm text-neutral-500 bg-neutral-50 border-b border-neutral-200">
          You can sign in as an active staff member only.
          <template v-if="users.some(u => u.status === 'invited')">Nobody has accepted their invitation yet; once they do, you can sign in as them.</template>
          <template v-else>Use Add User to invite the owner; once they accept, you can sign in as them.</template>
        </p>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">2FA</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Last login</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="users.length === 0">
                <td colspan="6" class="px-4 py-10 text-center text-neutral-400">No users yet.</td>
              </tr>
              <tr v-for="u in users" :key="u.id" class="hover:bg-neutral-50">
                <td class="px-4 py-3">
                  <p class="font-medium text-neutral-900">{{ u.name }}</p>
                  <p class="text-xs text-neutral-400">{{ u.email }}</p>
                </td>
                <td class="px-4 py-3 text-neutral-600">{{ u.role_label ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="userBadge(u.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ u.status }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-500">{{ u.two_factor ? 'On' : 'Off' }}</td>
                <td class="px-4 py-3 text-neutral-600 whitespace-nowrap">
                  <span v-if="u.last_login_at" :title="formatDateTime(u.last_login_at)">{{ timeAgo(u.last_login_at) }}</span>
                  <span v-else class="text-neutral-400" title="No sign-in has been recorded for this user yet.">—</span>
                </td>
                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                  <button v-if="u.status !== 'invited'" @click="showUserActivity(u.id)" class="text-xs text-neutral-600 hover:underline font-medium">Activity</button>
                  <template v-if="u.status === 'active'">
                    <button @click="openImpersonate(u)" class="text-xs text-indigo-600 hover:underline font-medium">Sign in as</button>
                    <button @click="openUserAction(u, 'suspend')" class="text-xs text-amber-600 hover:underline font-medium">Suspend</button>
                  </template>
                  <button v-else-if="u.status === 'suspended'" @click="openUserAction(u, 'activate')" class="text-xs text-emerald-600 hover:underline font-medium">Reactivate</button>
                  <button v-else @click="resendInvite(u)" :disabled="resending === u.id" class="text-xs text-blue-600 hover:underline font-medium disabled:opacity-40">
                    {{ resending === u.id ? 'Sending…' : 'Resend invite' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Emails -->
      <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="font-semibold text-neutral-900">Emails</h3>
            <p class="text-xs text-neutral-500 mt-0.5">Everything sent to this workspace's owner and staff. Delivery status comes from Resend.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button @click="refreshEmails()" :disabled="refreshingEmails" class="border border-neutral-300 text-neutral-700 hover:bg-neutral-50 text-sm font-medium px-3 py-2 rounded-lg whitespace-nowrap disabled:opacity-50">
              {{ refreshingEmails ? 'Checking…' : 'Check delivery' }}
            </button>
            <button @click="openOnboardingResend" class="border border-neutral-300 text-neutral-700 hover:bg-neutral-50 text-sm font-medium px-3 py-2 rounded-lg whitespace-nowrap">
              {{ tenant.email_verified_at ? 'Resend welcome email' : 'Resend verification email' }}
            </button>
            <button @click="openCompose" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
              Send email
            </button>
          </div>
        </div>
        <p v-if="emailsError" class="px-6 py-3 text-sm text-red-700 bg-red-50 border-b border-red-100">{{ emailsError }}</p>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">When</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">To</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="emails.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-neutral-400">
                  No emails logged yet. Emails sent from now on appear here.
                </td>
              </tr>
              <tr v-for="m in emails" :key="m.id" class="hover:bg-neutral-50 align-top">
                <td class="px-4 py-3 text-neutral-500 whitespace-nowrap" :title="formatDateTime(m.created_at)">{{ timeAgo(m.created_at) }}</td>
                <td class="px-4 py-3 text-neutral-700 break-all min-w-40">{{ m.to }}</td>
                <td class="px-4 py-3 min-w-56">
                  <p class="font-medium text-neutral-900">{{ m.kind }}</p>
                  <p class="text-xs text-neutral-500 truncate max-w-xs" :title="m.subject">{{ m.subject }}</p>
                  <p v-if="m.sent_by" class="text-xs text-neutral-400">{{ m.resent ? 'Resent' : 'Sent' }} by {{ m.sent_by }}</p>
                </td>
                <td class="px-4 py-3 whitespace-nowrap">
                  <span :class="emailBadge(m.status)" :title="m.error || emailStatusHint(m)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold">{{ m.status_label }}</span>
                  <p v-if="m.error" class="text-xs text-red-600 mt-1 max-w-xs whitespace-normal">{{ m.error }}</p>
                  <p v-else-if="!m.tracked && m.status === 'sent'" class="text-xs text-neutral-400 mt-1">Delivery not tracked</p>
                </td>
                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                  <button v-if="m.has_body" @click="viewEmail(m)" class="text-xs text-neutral-600 hover:underline font-medium">View</button>
                  <button v-if="m.resendable" @click="openLogResend(m)" class="text-xs text-blue-600 hover:underline font-medium">Resend</button>
                  <span v-else class="text-xs text-neutral-400 cursor-help" :title="m.not_resendable_reason">Can't resend</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activity -->
      <div ref="activityCard" class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="font-semibold text-neutral-900">Activity</h3>
            <p class="text-xs text-neutral-500 mt-0.5">Sign-ins, screens opened and changes made by this workspace's staff, newest first.</p>
          </div>
          <select v-model="activityUserId" @change="fetchActivity(true)" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option :value="null">All staff</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div v-if="activityLoading && activity.length === 0" class="px-6 py-10 text-center text-sm text-neutral-400">Loading…</div>
        <p v-else-if="activityError" class="px-6 py-4 text-sm text-red-700">{{ activityError }}</p>
        <p v-else-if="activity.length === 0" class="px-6 py-10 text-center text-sm text-neutral-400">
          Nothing recorded yet{{ activityUserId ? ' for this user' : '' }}.
        </p>
        <ul v-else class="divide-y divide-neutral-100">
          <li v-for="a in activity" :key="a.id" class="px-6 py-3 flex items-start gap-3 text-sm">
            <span :class="activityDot(a.kind)" class="mt-1.5 w-2 h-2 rounded-full shrink-0"></span>
            <div class="min-w-0 flex-1">
              <p class="text-neutral-900">
                <span :class="a.kind === 'auth' ? 'font-semibold' : ''">{{ a.label }}</span>
                <span v-if="!activityUserId && a.user_name" class="text-neutral-500"> · {{ a.user_name }}</span>
                <span v-if="a.via_support" class="ml-1 inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-50 text-indigo-700" :title="'Done by ' + a.via_support + ' during a support session'">support</span>
              </p>
              <p v-if="a.detail" class="text-xs text-neutral-400 truncate">{{ a.detail }}</p>
            </div>
            <span class="text-xs text-neutral-400 whitespace-nowrap" :title="formatDateTime(a.at)">{{ formatDateTime(a.at) }}</span>
          </li>
        </ul>
        <div v-if="activity.length >= activityLimit && activityLimit < 200" class="px-6 py-3 border-t border-neutral-100 text-center">
          <button @click="loadMoreActivity" :disabled="activityLoading" class="text-sm text-primary-600 hover:underline font-medium disabled:opacity-50">
            {{ activityLoading ? 'Loading…' : 'Show more' }}
          </button>
        </div>
      </div>

      <!-- Support access history -->
      <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200">
          <h3 class="font-semibold text-neutral-900">Support access history</h3>
          <p class="text-xs text-neutral-500 mt-0.5">The last 20 "sign in as" sessions for this workspace.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">When</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Signed in as</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Reason</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="sessions.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-neutral-400">No support sessions yet.</td>
              </tr>
              <tr v-for="s in sessions" :key="s.id">
                <td class="px-4 py-3 text-neutral-500 whitespace-nowrap">{{ formatDateTime(s.started_at ?? s.created_at) }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ s.landlord_email }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ s.target_name }}</td>
                <td class="px-4 py-3 text-neutral-600 min-w-48">{{ s.reason }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <button v-if="s.status === 'active'" @click="endSession(s)" class="text-xs text-red-600 hover:underline font-medium mr-2">End now</button>
                  <span :class="sessionBadge(s.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ s.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAdd" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Add User</h3>
          <button @click="showAdd = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-neutral-600">They'll get an email invitation to set their own password.</p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input v-model="addForm.name" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input v-model="addForm.email" type="email" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
            <input v-model="addForm.phone" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Role <span class="text-red-500">*</span></label>
            <select v-model="addForm.role" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <p v-if="addError" class="text-sm text-red-600">{{ addError }}</p>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="showAdd = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="submitAdd" :disabled="adding" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ adding ? 'Sending…' : 'Send Invitation' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Suspend / Reactivate Modal -->
    <div v-if="actionTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-neutral-900 mb-2">{{ userAction === 'suspend' ? 'Suspend' : 'Reactivate' }} {{ actionTarget.name }}?</h3>
        <p class="text-sm text-neutral-600 mb-4">
          <template v-if="userAction === 'suspend'">They'll be signed out straight away and won't be able to log in until reactivated.</template>
          <template v-else>They'll be able to log in again with their existing password.</template>
        </p>
        <p v-if="actionError" class="text-sm text-red-600 mb-4">{{ actionError }}</p>
        <div class="flex justify-end gap-3">
          <button @click="actionTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button
            @click="executeUserAction"
            :disabled="actioning"
            :class="userAction === 'suspend' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700'"
            class="px-4 py-2 text-sm text-white rounded-lg font-semibold disabled:opacity-50"
          >
            {{ actioning ? 'Please wait…' : userAction === 'suspend' ? 'Suspend' : 'Reactivate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Verify Email Modal -->
    <div v-if="showVerify" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-neutral-900 mb-2">Verify {{ tenant.name }}?</h3>
        <p class="text-sm text-neutral-600 mb-4">
          Only do this once you're sure the owner controls
          <template v-if="tenant.admin_email"><span class="font-medium">{{ tenant.admin_email }}</span></template>
          <template v-else>this workspace</template>.
          Its staff will be able to log in straight away<template v-if="tenant.admin_email">, and the owner gets a welcome email</template>.
        </p>
        <p v-if="verifyError" class="text-sm text-red-600 mb-4">{{ verifyError }}</p>
        <div class="flex justify-end gap-3">
          <button @click="showVerify = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="verifyEmail" :disabled="verifying" class="px-4 py-2 text-sm bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ verifying ? 'Verifying…' : 'Verify email' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Send Email Modal -->
    <div v-if="showCompose" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Email {{ tenant.name }}</h3>
          <button @click="showCompose = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4 overflow-y-auto">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Start from</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="t in emailTemplates" :key="t.key" type="button" @click="applyTemplate(t)"
                :class="compose.template === t.key ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-neutral-300 text-neutral-600 hover:bg-neutral-50'"
                class="border text-xs font-medium px-3 py-1.5 rounded-lg"
              >{{ t.name }}</button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">To <span class="text-red-500">*</span></label>
            <div class="border border-neutral-200 rounded-lg divide-y divide-neutral-100 max-h-40 overflow-y-auto">
              <label v-for="r in recipientOptions" :key="r.email" class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-neutral-50">
                <input type="checkbox" :value="r.email" v-model="compose.recipients" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
                <span class="text-neutral-900">{{ r.email }}</span>
                <span class="text-xs text-neutral-400">{{ r.label }}</span>
              </label>
              <p v-if="recipientOptions.length === 0" class="px-3 py-2 text-sm text-neutral-400">No owner or staff email on file. Add an address below.</p>
            </div>
            <input v-model="compose.extra" type="text" placeholder="Other addresses, separated by commas" class="mt-2 w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Subject <span class="text-red-500">*</span></label>
            <input v-model="compose.subject" type="text" maxlength="150" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Message <span class="text-red-500">*</span></label>
            <textarea v-model="compose.body" rows="9" maxlength="10000" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"></textarea>
            <p class="text-xs text-neutral-400 mt-1">Plain text; line breaks are kept. Each person is greeted by their first name when they're on the staff list.</p>
          </div>

          <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-neutral-700">
              <input type="checkbox" v-model="compose.withButton" class="rounded border-neutral-300 text-primary-600 focus:ring-primary-500" />
              Add a button
            </label>
            <div v-if="compose.withButton" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <input v-model="compose.buttonLabel" type="text" maxlength="40" placeholder="Button text" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
              <input v-model="compose.buttonUrl" type="url" placeholder="https://…" class="sm:col-span-2 border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
          </div>

          <ul v-if="composeErrors.length" class="text-sm text-red-600 space-y-1">
            <li v-for="(e, i) in composeErrors" :key="i">{{ e }}</li>
          </ul>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex items-center justify-between gap-3">
          <p class="text-xs text-neutral-500">{{ composeRecipients.length }} recipient{{ composeRecipients.length === 1 ? '' : 's' }} · sent from LENDR</p>
          <div class="flex gap-3">
            <button @click="showCompose = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
            <button @click="sendCompose" :disabled="sendingCompose || composeRecipients.length === 0 || !compose.subject.trim() || !compose.body.trim()" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
              {{ sendingCompose ? 'Sending…' : 'Send email' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Email Modal -->
    <div v-if="viewing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[92vh] flex flex-col">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h3 class="font-semibold text-neutral-900 truncate">{{ viewing.subject }}</h3>
            <p class="text-xs text-neutral-500">To {{ viewing.to }} · {{ formatDateTime(viewing.created_at) }} · {{ viewing.status_label }}</p>
          </div>
          <button @click="viewing = null" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="flex-1 overflow-hidden bg-neutral-50">
          <div v-if="viewingLoading" class="py-16 text-center text-sm text-neutral-400">Loading…</div>
          <!-- sandbox="" : the stored email can't run scripts or reach this page -->
          <iframe v-else-if="viewing.html" :srcdoc="viewing.html" sandbox="" class="w-full h-[70vh] bg-white" title="Email preview"></iframe>
          <pre v-else class="p-6 text-sm text-neutral-800 whitespace-pre-wrap h-[70vh] overflow-auto">{{ viewing.text }}</pre>
        </div>
        <div class="px-6 py-3 border-t border-neutral-200 flex justify-end gap-3">
          <button v-if="viewing.resendable" @click="openLogResend(viewing); viewing = null" class="px-4 py-2 text-sm text-blue-600 hover:underline font-medium">Resend</button>
          <button @click="viewing = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Close</button>
        </div>
      </div>
    </div>

    <!-- Resend Modal -->
    <div v-if="resendTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="font-semibold text-neutral-900 mb-2">{{ resendTitle }}</h3>
        <p class="text-sm text-neutral-600 mb-4">{{ resendExplanation }}</p>
        <label class="block text-sm font-medium text-neutral-700 mb-1">Send to</label>
        <input v-model="resendTarget.to" type="email" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        <p class="text-xs text-neutral-400 mt-1 mb-4">Change it if the address was mistyped.</p>
        <p v-if="resendError" class="text-sm text-red-600 mb-4">{{ resendError }}</p>
        <div class="flex justify-end gap-3">
          <button @click="resendTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="submitResend" :disabled="resendingEmail || !resendTarget.to" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ resendingEmail ? 'Sending…' : 'Send' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Sign In As Modal -->
    <div v-if="impersonateTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Sign in as {{ impersonateTarget.name }}</h3>
          <button @click="impersonateTarget = null" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div v-if="activeUsers.length > 1">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Staff member</label>
            <select v-model="impersonateTargetId" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option v-for="u in activeUsers" :key="u.id" :value="u.id">{{ u.name }} · {{ u.role_label ?? 'No role' }}</option>
            </select>
          </div>
          <p class="text-sm text-neutral-600">
            Opens {{ tenant.name }}'s workspace in a new tab with this user's access, for up to 30 minutes.
            Anything you do there is real and is recorded against both you and this user.
          </p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea
              v-model="impersonateReason"
              rows="3"
              placeholder="e.g. Ticket #142: can't see the disbursement screen."
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
            <p class="text-xs text-neutral-400 mt-1">This workspace's admins can read this in their audit log.</p>
          </div>
          <p v-if="impersonateError" class="text-sm text-red-600">{{ impersonateError }}</p>
          <a v-if="impersonateUrl" :href="impersonateUrl" target="_blank" rel="noopener noreferrer" class="block text-sm text-primary-600 hover:underline">
            Your browser blocked the new tab. Click here to open the session (link works for 60 seconds).
          </a>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="impersonateTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="startImpersonation" :disabled="impersonating || impersonateReason.trim().length < 5" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ impersonating ? 'Starting…' : 'Start Session' }}
          </button>
        </div>
      </div>
    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const props = defineProps({
  tenantId: { type: String, required: true },
})

const auth = useLandlordAuth()
const api  = `/api/v1/landlord/tenants/${props.tenantId}`

// ─── State ───────────────────────────────────────────────────────────────────
const loading   = ref(true)
const loadError = ref('')
const notice    = ref('')
const tenant    = ref(null)
const stats     = ref(null)
const roles     = ref([])
const users     = ref([])
const sessions  = ref([])
const resending = ref(null)

// Verify email
const showVerify  = ref(false)
const verifying   = ref(false)
const verifyError = ref('')

// Add user
const showAdd  = ref(false)
const adding   = ref(false)
const addError = ref('')
const addForm  = reactive({ name: '', email: '', phone: '', role: 'loan_officer' })

// Suspend / reactivate
const actionTarget = ref(null)
const userAction   = ref('')
const actioning    = ref(false)
const actionError  = ref('')

// Sign in as
const impersonateTarget = ref(null)
const impersonateReason = ref('')
const impersonateError  = ref('')
const impersonateUrl    = ref('')
const impersonating     = ref(false)

// Emails
const emails           = ref([])
const emailsError      = ref('')
const refreshingEmails = ref(false)
const viewing          = ref(null)
const viewingLoading   = ref(false)
const resendTarget     = ref(null) // { mode: 'log' | 'onboarding', log?, to }
const resendError      = ref('')
const resendingEmail   = ref(false)
let statusTimer = null

// Send email
const showCompose    = ref(false)
const sendingCompose = ref(false)
const composeErrors  = ref([])
const compose = reactive({ template: 'blank', recipients: [], extra: '', subject: '', body: '', withButton: false, buttonLabel: '', buttonUrl: '' })

// Activity
const activityCard    = ref(null)
const activity        = ref([])
const activityUserId  = ref(null)
const activityLimit   = ref(60)
const activityLoading = ref(false)
const activityError   = ref('')

// Only active staff can be signed in as (the server refuses anyone else).
const activeUsers = computed(() => users.value.filter(u => u.status === 'active'))
const impersonateTargetId = computed({
  get: () => impersonateTarget.value?.id,
  set: (id) => { impersonateTarget.value = activeUsers.value.find(u => u.id === id) ?? null },
})

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!auth.isAuthenticated) {
    router.visit(route('landlord.login'))
    return
  }
  try {
    await Promise.all([fetchOverview(), fetchUsers(), fetchSessions()])
  } catch (e) {
    if (e.response?.status === 401) {
      auth.clearAuth()
      router.visit(route('landlord.login'))
      return
    }
    loadError.value = e.response?.data?.message ?? 'Failed to load this tenant.'
  } finally {
    loading.value = false
  }

  // Secondary panels load after the page is up; one failing doesn't hide the rest.
  if (!loadError.value) {
    fetchActivity(true)
    fetchEmails().then(() => { if (emails.value.some(awaitingDelivery)) refreshEmails(true) })
  }
})

onBeforeUnmount(() => clearTimeout(statusTimer))

// ─── Data ─────────────────────────────────────────────────────────────────────
async function fetchOverview() {
  const { data } = await axios.get(`${api}/overview`)
  tenant.value = data.data.tenant
  stats.value  = data.data.stats
  roles.value  = data.data.roles
}

async function fetchUsers() {
  const { data } = await axios.get(`${api}/users`)
  users.value = data.data
}

async function fetchSessions() {
  const { data } = await axios.get(`${api}/impersonations`)
  sessions.value = data.data
}

// ─── Emails ───────────────────────────────────────────────────────────────────
const SETTLED = ['delivered', 'opened', 'clicked', 'bounced', 'complained', 'failed', 'canceled']
const awaitingDelivery = (m) => m.tracked && !SETTLED.includes(m.status)

async function fetchEmails() {
  try {
    const { data } = await axios.get(`${api}/emails`)
    emails.value = data.data
    emailsError.value = ''
  } catch (e) {
    emailsError.value = e.response?.data?.message ?? 'Could not load the email log.'
  }
}

/** Asks Resend what happened to recent emails. `quiet` = no error banner (background checks). */
async function refreshEmails(quiet = false) {
  refreshingEmails.value = true
  try {
    const { data } = await axios.post(`${api}/emails/refresh`)
    emails.value = data.data
    emailsError.value = ''
  } catch (e) {
    if (!quiet) emailsError.value = e.response?.data?.message ?? 'Could not check delivery right now.'
  } finally {
    refreshingEmails.value = false
  }
}

/** Resend usually settles within seconds: look again shortly after sending. */
function checkDeliverySoon() {
  clearTimeout(statusTimer)
  statusTimer = setTimeout(() => refreshEmails(true), 8000)
}

async function viewEmail(m) {
  viewing.value = { ...m, html: null, text: null }
  viewingLoading.value = true
  try {
    const { data } = await axios.get(`${api}/emails/${m.id}`)
    if (viewing.value?.id === m.id) viewing.value = data.data
  } catch (e) {
    viewing.value = null
    emailsError.value = e.response?.data?.message ?? 'Could not open that email.'
  } finally {
    viewingLoading.value = false
  }
}

function openLogResend(m) {
  resendError.value = ''
  resendTarget.value = { mode: 'log', log: m, to: m.to.includes(',') ? '' : m.to }
}

function openOnboardingResend() {
  resendError.value = ''
  resendTarget.value = { mode: 'onboarding', to: tenant.value.admin_email ?? '' }
}

const resendIsVerification = computed(() =>
  resendTarget.value?.mode === 'onboarding'
    ? !tenant.value?.email_verified_at
    : resendTarget.value?.log?.kind === 'Email verification',
)

const resendTitle = computed(() => {
  if (!resendTarget.value) return ''
  if (resendIsVerification.value) return 'Send a new verification link?'
  return resendTarget.value.mode === 'onboarding' ? 'Resend the welcome email?' : 'Resend this email?'
})

const resendExplanation = computed(() => {
  if (resendIsVerification.value) {
    return 'The owner gets a fresh link to verify their email and open the workspace. Links sent before stop working.'
  }
  if (resendTarget.value?.mode === 'onboarding') {
    return 'The owner gets the welcome email again, with their sign-in link and first steps.'
  }
  return `"${resendTarget.value?.log?.subject}" is sent again exactly as it first went out.`
})

async function submitResend() {
  resendingEmail.value = true
  resendError.value = ''
  const target = resendTarget.value
  try {
    const url = target.mode === 'onboarding' ? `${api}/emails/onboarding` : `${api}/emails/${target.log.id}/resend`
    const { data } = await axios.post(url, { to: target.to })
    emails.value = data.data.emails
    notice.value = data.message
    resendTarget.value = null
    checkDeliverySoon()
  } catch (e) {
    resendError.value = e.response?.data?.message ?? 'The email could not be sent.'
  } finally {
    resendingEmail.value = false
  }
}

// ─── Send email ───────────────────────────────────────────────────────────────
const recipientOptions = computed(() => {
  const seen = new Set()
  const options = []
  const add = (email, label) => {
    const key = (email ?? '').toLowerCase()
    if (!key || seen.has(key)) return
    seen.add(key)
    options.push({ email, label })
  }
  add(tenant.value?.admin_email, 'Owner')
  users.value.filter(u => u.status !== 'suspended').forEach(u => add(u.email, `${u.name} · ${u.role_label ?? 'Staff'}${u.status === 'invited' ? ' (invited)' : ''}`))
  return options
})

const composeRecipients = computed(() => {
  const extra = compose.extra.split(/[,;\s]+/).map(s => s.trim()).filter(Boolean)
  const all = [...compose.recipients, ...extra]
  return all.filter((email, i) => all.findIndex(o => o.toLowerCase() === email.toLowerCase()) === i)
})

const emailTemplates = computed(() => {
  const name = tenant.value?.name ?? 'your workspace'
  const login = tenant.value?.login_url ?? ''
  return [
    { key: 'blank', name: 'Blank', subject: '', body: '', button: null },
    {
      key: 'checkin', name: 'Checking in',
      subject: `How is ${name} getting on with LENDR?`,
      body: `We wanted to check in and see how you're finding LENDR so far.\n\nIf anything is unclear or not working the way you expect — loan products, adding your team, recording payments — just reply to this email and we'll help you sort it out.`,
      button: { label: 'Sign in to your workspace', url: login },
    },
    {
      key: 'setup', name: 'Finish setting up',
      subject: `A few steps left to set up ${name}`,
      body: `Your workspace is ready, but it isn't fully set up yet. To start lending, you'll want to:\n\n1. Add your branding (logo and colours)\n2. Create your loan products\n3. Invite your team\n4. Add your first borrower\n\nIt takes about 15 minutes. Reply to this email if you'd like us to walk you through it.`,
      button: { label: 'Continue setting up', url: login },
    },
    {
      key: 'trial', name: 'Trial ending',
      subject: `Your ${name} trial ends soon`,
      body: `Your LENDR trial ends on ${tenant.value?.trial_ends_at ? formatDate(tenant.value.trial_ends_at) : 'soon'}. To keep your loans, borrowers and records available without interruption, choose a plan from Billing in your workspace.\n\nIf you have questions about which plan fits, reply to this email.`,
      button: { label: 'Choose a plan', url: login },
    },
    {
      key: 'payment', name: 'Payment reminder',
      subject: `Your LENDR subscription payment for ${name}`,
      body: `This is a reminder that your LENDR subscription payment is due. You can pay from Billing in your workspace.\n\nIf you've already paid, thank you, and please ignore this message. If something went wrong with the payment, reply and we'll look into it.`,
      button: { label: 'Go to billing', url: login },
    },
    {
      key: 'login', name: 'Sign-in help',
      subject: `Signing in to ${name}`,
      body: `Here's how to sign in to your workspace:\n\n• Go to ${login}\n• Use the email address you registered with\n• If you've forgotten your password, use "Forgot password" on the sign-in page\n\nStill stuck? Reply to this email and we'll help.`,
      button: { label: 'Sign in', url: login },
    },
  ]
})

function applyTemplate(t) {
  compose.template = t.key
  compose.subject = t.subject
  compose.body = t.body
  compose.withButton = !!t.button?.url
  compose.buttonLabel = t.button?.label ?? ''
  compose.buttonUrl = t.button?.url ?? ''
}

function openCompose() {
  applyTemplate(emailTemplates.value[0])
  compose.recipients = tenant.value?.admin_email ? [tenant.value.admin_email] : []
  compose.extra = ''
  composeErrors.value = []
  showCompose.value = true
}

async function sendCompose() {
  sendingCompose.value = true
  composeErrors.value = []
  try {
    const { data } = await axios.post(`${api}/emails`, {
      recipients: composeRecipients.value,
      subject: compose.subject,
      body: compose.body,
      button_label: compose.withButton ? compose.buttonLabel : null,
      button_url: compose.withButton ? compose.buttonUrl : null,
    })
    emails.value = data.data.emails
    notice.value = data.message
    showCompose.value = false
    checkDeliverySoon()
  } catch (e) {
    const errors = e.response?.data?.errors
    composeErrors.value = errors
      ? Object.values(errors).flat()
      : [e.response?.data?.message ?? 'The email could not be sent.']
  } finally {
    sendingCompose.value = false
  }
}

// ─── Activity ─────────────────────────────────────────────────────────────────
async function fetchActivity(reset = false) {
  if (reset) activityLimit.value = 60
  activityLoading.value = true
  activityError.value = ''
  try {
    const { data } = await axios.get(`${api}/activity`, {
      params: { user_id: activityUserId.value ?? undefined, limit: activityLimit.value },
    })
    activity.value = data.data
  } catch (e) {
    activityError.value = e.response?.data?.message ?? 'Could not load activity.'
  } finally {
    activityLoading.value = false
  }
}

function loadMoreActivity() {
  activityLimit.value = Math.min(activityLimit.value + 60, 200)
  fetchActivity()
}

async function showUserActivity(userId) {
  activityUserId.value = userId
  await fetchActivity(true)
  await nextTick()
  activityCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// ─── Verify email ─────────────────────────────────────────────────────────────
function openVerify() {
  verifyError.value = ''
  showVerify.value  = true
}

async function verifyEmail() {
  verifying.value   = true
  verifyError.value = ''
  try {
    const { data } = await axios.post(`${api}/verify-email`)
    showVerify.value = false
    notice.value     = data.message
    await Promise.all([fetchOverview(), fetchUsers()])
  } catch (e) {
    verifyError.value = e.response?.data?.message ?? 'Failed to verify this workspace.'
  } finally {
    verifying.value = false
  }
}

// ─── Add user ─────────────────────────────────────────────────────────────────
function openAdd() {
  Object.assign(addForm, { name: '', email: '', phone: '', role: 'loan_officer' })
  addError.value = ''
  showAdd.value  = true
}

async function submitAdd() {
  adding.value   = true
  addError.value = ''
  try {
    const { data } = await axios.post(`${api}/users`, addForm)
    showAdd.value = false
    notice.value  = data.message
    await Promise.all([fetchUsers(), fetchOverview()])
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Failed to add this user.'
  } finally {
    adding.value = false
  }
}

async function resendInvite(user) {
  resending.value = user.id
  try {
    const { data } = await axios.post(`${api}/users/${user.id}/resend-invite`)
    notice.value = data.message
  } catch (e) {
    notice.value = e.response?.data?.message ?? 'Failed to resend the invitation.'
  } finally {
    resending.value = null
  }
}

// ─── Suspend / reactivate ─────────────────────────────────────────────────────
function openUserAction(user, action) {
  actionTarget.value = user
  userAction.value   = action
  actionError.value  = ''
}

async function executeUserAction() {
  actioning.value   = true
  actionError.value = ''
  try {
    const { data } = await axios.post(`${api}/users/${actionTarget.value.id}/${userAction.value}`)
    actionTarget.value = null
    notice.value       = data.message
    await Promise.all([fetchUsers(), fetchOverview()])
  } catch (e) {
    actionError.value = e.response?.data?.message ?? 'That did not work. Please try again.'
  } finally {
    actioning.value = false
  }
}

// ─── Sign in as ───────────────────────────────────────────────────────────────
function openImpersonate(user) {
  impersonateTarget.value = user
  impersonateReason.value = ''
  impersonateError.value  = ''
  impersonateUrl.value    = ''
}

async function startImpersonation() {
  impersonating.value    = true
  impersonateError.value = ''
  impersonateUrl.value   = ''

  // Opened before the request: browsers block tabs opened after an await.
  const tab = window.open('', '_blank')

  try {
    const { data } = await axios.post(`${api}/users/${impersonateTarget.value.id}/impersonate`, {
      reason: impersonateReason.value,
    })

    if (tab) {
      tab.opener = null
      tab.location.href = data.data.url
      impersonateTarget.value = null
    } else {
      impersonateUrl.value = data.data.url
    }
    await fetchSessions()
  } catch (e) {
    tab?.close()
    impersonateError.value = e.response?.data?.message ?? 'Failed to start the session.'
  } finally {
    impersonating.value = false
  }
}

async function endSession(session) {
  try {
    const { data } = await axios.post(`/api/v1/landlord/impersonations/${session.id}/end`)
    notice.value = data.message
    await fetchSessions()
  } catch (e) {
    notice.value = e.response?.data?.message ?? 'Failed to end the session.'
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function timeAgo(iso) {
  if (!iso) return '—'
  const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]]
  for (const [unit, size] of units) {
    const n = Math.floor(seconds / size)
    if (n >= 1) return `${n} ${unit}${n === 1 ? '' : 's'} ago`
  }
  return 'just now'
}

function emailBadge(status) {
  return {
    delivered: 'bg-emerald-100 text-emerald-800',
    opened:    'bg-emerald-100 text-emerald-800',
    clicked:   'bg-emerald-100 text-emerald-800',
    sent:      'bg-blue-100 text-blue-800',
    sending:   'bg-blue-50 text-blue-700',
    queued:    'bg-blue-50 text-blue-700',
    delivery_delayed: 'bg-amber-100 text-amber-800',
    bounced:   'bg-red-100 text-red-800',
    complained: 'bg-red-100 text-red-800',
    failed:    'bg-red-100 text-red-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function emailStatusHint(m) {
  return {
    sent: m.tracked ? 'Accepted by Resend; waiting for the receiving server to confirm.' : 'Handed to the mail server. Delivery is only tracked when sending through Resend.',
    delivered: "Accepted by the recipient's mail server.",
    delivery_delayed: "The recipient's server is deferring it; Resend keeps retrying.",
    bounced: 'The address rejected it. Check the address and resend to a corrected one.',
    complained: 'The recipient marked it as spam.',
    failed: 'It never left LENDR. Check Platform Settings → Email.',
  }[m.status] ?? ''
}

function activityDot(kind) {
  return { auth: 'bg-emerald-500', navigation: 'bg-neutral-300', change: 'bg-primary-500' }[kind] ?? 'bg-neutral-300'
}

function formatMoney(amount) {
  return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusBadge(status) {
  return {
    active:    'bg-emerald-100 text-emerald-800',
    trial:     'bg-amber-100 text-amber-800',
    suspended: 'bg-red-100 text-red-800',
    expired:   'bg-orange-100 text-orange-800',
    cancelled: 'bg-neutral-100 text-neutral-600',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function planBadge(plan) {
  return {
    starter:    'bg-blue-100 text-blue-800',
    growth:     'bg-violet-100 text-violet-800',
    enterprise: 'bg-neutral-800 text-white',
  }[plan] ?? 'bg-neutral-100 text-neutral-600'
}

function userBadge(status) {
  return {
    active:    'bg-emerald-100 text-emerald-800',
    invited:   'bg-blue-100 text-blue-800',
    suspended: 'bg-red-100 text-red-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function sessionBadge(status) {
  return {
    active:  'bg-red-100 text-red-800',
    pending: 'bg-amber-100 text-amber-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}
</script>
