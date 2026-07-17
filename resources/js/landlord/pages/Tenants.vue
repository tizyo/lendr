<template>
  <LandlordLayout title="Tenants">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Search by name or ID…"
        class="flex-1 border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
        @input="debouncedFetch"
      />
      <select v-model="filters.status" @change="fetchTenants()" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        <option value="">All Statuses</option>
        <option value="trial">Trial</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
        <option value="expired">Expired</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <select v-model="filters.plan" @change="fetchTenants()" class="border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        <option value="">All Plans</option>
        <option value="starter">Starter</option>
        <option value="growth">Growth</option>
        <option value="enterprise">Enterprise</option>
      </select>
      <button @click="showCreate = true" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
        + New Tenant
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-neutral-50 border-b border-neutral-200">
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Plan</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Currency</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Created</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="tenants.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-neutral-400">No tenants found.</td>
          </tr>
          <tr v-for="t in tenants" :key="t.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5">
                <p class="font-medium text-neutral-900">{{ t.name }}</p>
                <!-- Gold verification badge -->
                <span v-if="t.verification_badge === 'gold'" title="Gold Verified" class="inline-flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-yellow-500">
                    <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0112 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 013.498 1.307 4.491 4.491 0 011.307 3.497A4.49 4.49 0 0121.75 12a4.49 4.49 0 01-1.549 3.397 4.491 4.491 0 01-1.307 3.497 4.491 4.491 0 01-3.497 1.307A4.49 4.49 0 0112 21.75a4.49 4.49 0 01-3.397-1.549 4.491 4.491 0 01-3.497-1.307 4.491 4.491 0 01-1.307-3.497A4.49 4.49 0 012.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 011.307-3.497 4.49 4.49 0 013.497-1.307zm7.007 6.387a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                  </svg>
                </span>
              </div>
              <p class="text-xs text-neutral-400 font-mono">{{ t.slug }}</p>
            </td>
            <td class="px-4 py-3">
              <span :class="planBadge(t.plan)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ t.plan }}</span>
            </td>
            <td class="px-4 py-3">
              <span :class="statusBadge(t.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ t.status }}</span>
              <p v-if="t.trial_ends_at && t.status === 'trial'" class="text-xs text-amber-600 mt-0.5">
                Ends {{ t.trial_ends_at }}
              </p>
              <p v-if="t.status === 'expired'" class="text-xs text-red-500 mt-0.5">
                Ended {{ t.trial_ends_at ?? '—' }}
              </p>
            </td>
            <td class="px-4 py-3 text-neutral-600">{{ t.currency }}</td>
            <td class="px-4 py-3 text-neutral-500">{{ formatDate(t.created_at) }}</td>
            <td class="px-4 py-3 text-right space-x-2">
              <!-- Send reminders -->
              <span v-if="reminderCooldowns[t.id]" class="text-xs text-neutral-400 font-medium">
                Sent · {{ reminderCooldowns[t.id] }}
              </span>
              <button
                v-else
                @click="confirmReminder(t)"
                :disabled="reminderSending === t.id"
                class="text-xs text-violet-600 hover:underline font-medium disabled:opacity-40"
              >
                {{ reminderSending === t.id ? 'Sending…' : 'Send Reminders' }}
              </button>
              <button
                v-if="t.status !== 'suspended'"
                @click="confirmAction(t, 'suspend')"
                class="text-xs text-amber-600 hover:underline font-medium"
              >Suspend</button>
              <button
                v-if="t.status === 'suspended'"
                @click="confirmAction(t, 'activate')"
                class="text-xs text-emerald-600 hover:underline font-medium"
              >Activate</button>
              <!-- Verify / Unverify (only for non-enterprise) -->
              <template v-if="t.plan !== 'enterprise'">
                <button
                  v-if="!t.is_verified"
                  @click="openVerify(t)"
                  class="text-xs text-yellow-600 hover:underline font-medium"
                >Verify</button>
                <button
                  v-else
                  @click="revokeVerification(t)"
                  :disabled="verifying === t.id"
                  class="text-xs text-neutral-400 hover:underline font-medium disabled:opacity-40"
                >Unverify</button>
              </template>
              <button
                v-if="t.plan === 'enterprise'"
                @click="openWallet(t)"
                class="text-xs text-indigo-600 hover:underline font-medium"
              >Wallet</button>
              <button
                @click="openEdit(t)"
                class="text-xs text-blue-600 hover:underline font-medium"
              >Edit</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-neutral-200 text-sm text-neutral-500">
        <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} — {{ pagination.total }} tenants</span>
        <div class="flex gap-2">
          <button
            :disabled="pagination.current_page === 1"
            @click="changePage(pagination.current_page - 1)"
            class="px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 hover:bg-neutral-50"
          >Prev</button>
          <button
            :disabled="pagination.current_page === pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
            class="px-3 py-1 rounded border border-neutral-300 disabled:opacity-40 hover:bg-neutral-50"
          >Next</button>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">New Tenant</h3>
          <button @click="closeCreate" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Company Name <span class="text-red-500">*</span></label>
            <input v-model="createForm.name" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Plan <span class="text-red-500">*</span></label>
            <select v-model="createForm.plan" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option value="starter">Starter</option>
              <option value="growth">Growth</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Subdomain (optional)</label>
            <input v-model="createForm.subdomain" type="text" placeholder="e.g. acme" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Currency</label>
              <input v-model="createForm.currency" type="text" placeholder="ZMW" maxlength="3" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Timezone</label>
              <input v-model="createForm.timezone" type="text" placeholder="Africa/Lusaka" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
          </div>
          <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="closeCreate" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="submitCreate" :disabled="creating" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ creating ? 'Provisioning…' : 'Create Tenant' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEdit" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Edit Tenant</h3>
          <button @click="showEdit = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Name</label>
            <input v-model="editForm.name" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Plan</label>
            <select v-model="editForm.plan" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option value="starter">Starter</option>
              <option value="growth">Growth</option>
              <option value="enterprise">Enterprise</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Status</label>
            <select v-model="editForm.status" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option value="trial">Trial</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="expired">Expired</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div v-if="editForm.status === 'trial'">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Trial Ends At</label>
            <input v-model="editForm.trial_ends_at" type="date"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div v-if="editTarget?.admin_email">
            <label class="block text-sm font-medium text-neutral-700 mb-1">Admin Email</label>
            <p class="text-sm text-neutral-500 bg-neutral-50 border border-neutral-200 rounded-lg px-3 py-2">{{ editTarget.admin_email }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Currency</label>
              <input v-model="editForm.currency" type="text" maxlength="3" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Timezone</label>
              <input v-model="editForm.timezone" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
          </div>
          <p v-if="editError" class="text-sm text-red-600">{{ editError }}</p>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="showEdit = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="submitEdit" :disabled="editing" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ editing ? 'Saving…' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Action Modal -->
    <div v-if="confirmTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-neutral-900 mb-2 capitalize">{{ confirmAction_ }} Tenant?</h3>
        <p class="text-sm text-neutral-600 mb-6">
          Are you sure you want to <strong>{{ confirmAction_ }}</strong> <strong>{{ confirmTarget.name }}</strong>?
        </p>
        <div class="flex justify-end gap-3">
          <button @click="confirmTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button
            @click="executeAction"
            :disabled="actioning"
            :class="confirmAction_ === 'suspend' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700'"
            class="px-4 py-2 text-sm text-white rounded-lg font-semibold disabled:opacity-50"
          >
            {{ actioning ? 'Please wait…' : confirmAction_ === 'suspend' ? 'Suspend' : 'Activate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Verify Tenant Modal -->
    <div v-if="showVerify" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Grant Gold Badge</h3>
          <button @click="showVerify = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-neutral-600">
            Grant the gold verified badge to <strong>{{ verifyTarget?.name }}</strong>?
            This badge will be visible on the marketplace and public pages.
          </p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Note (optional)</label>
            <textarea
              v-model="verifyNote"
              rows="3"
              placeholder="e.g. Verified business registration documents."
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
          </div>
          <p v-if="verifyError" class="text-sm text-red-600">{{ verifyError }}</p>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="showVerify = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button
            @click="submitVerify"
            :disabled="verifying === verifyTarget?.id"
            class="px-4 py-2 text-sm bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold disabled:opacity-50"
          >
            {{ verifying === verifyTarget?.id ? 'Granting…' : 'Grant Badge' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Send Reminders Confirm Modal -->
    <div v-if="reminderTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-neutral-900 mb-2">Send Payment Reminders?</h3>
        <p class="text-sm text-neutral-600 mb-2">
          This will send an in-app notification and SMS to every borrower of
          <strong>{{ reminderTarget.name }}</strong> who has an unpaid overdue instalment.
        </p>
        <p class="text-xs text-neutral-400 mb-6">This action can only be triggered once per hour per tenant.</p>
        <p v-if="reminderError" class="text-sm text-red-600 mb-4">{{ reminderError }}</p>
        <div class="flex justify-end gap-3">
          <button @click="reminderTarget = null; reminderError = ''" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button
            @click="executeReminder"
            :disabled="reminderSending === reminderTarget?.id"
            class="px-4 py-2 text-sm bg-violet-600 hover:bg-violet-700 text-white rounded-lg font-semibold disabled:opacity-50"
          >
            {{ reminderSending === reminderTarget?.id ? 'Sending…' : 'Yes, Send Reminders' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Wallet Config Modal -->
    <div v-if="showWallet" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between shrink-0">
          <div>
            <h3 class="font-semibold text-neutral-900">Payment Wallet — {{ walletTarget?.name }}</h3>
            <p class="text-xs text-neutral-400 mt-0.5">Configure auto-disbursement &amp; auto-debit credentials</p>
          </div>
          <button @click="closeWallet" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>

        <div v-if="walletLoading" class="flex justify-center items-center py-12">
          <div class="w-7 h-7 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else class="p-6 space-y-4 overflow-y-auto">
          <!-- Existing wallet badge -->
          <div v-if="existingWallet" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-sm text-emerald-800">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            <span>Wallet configured · last updated {{ existingWallet.updated_at ?? '—' }}</span>
          </div>
          <div v-else class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>No wallet configured yet — auto-disbursement &amp; auto-debit are disabled.</span>
          </div>

          <!-- Gateway + Environment -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Gateway <span class="text-red-500">*</span></label>
              <select v-model="walletForm.gateway" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="flutterwave">Flutterwave</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="pawapay">PawaPay</option>
                <option value="zamtel_kwacha">Zamtel Kwacha</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Environment <span class="text-red-500">*</span></label>
              <select v-model="walletForm.environment" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="sandbox">Sandbox</option>
                <option value="production">Production</option>
              </select>
            </div>
          </div>

          <!-- Wallet ID -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Wallet / Account ID</label>
            <input v-model="walletForm.wallet_id" type="text" placeholder="Provider wallet ID (if required)"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- API Key -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">
              API Key <span class="text-red-500">*</span>
              <span v-if="existingWallet?.api_key_set" class="ml-1 text-xs text-neutral-400 font-normal">(currently set — leave blank to keep)</span>
            </label>
            <input v-model="walletForm.api_key" type="password" :placeholder="existingWallet?.api_key_set ? '••••••••••••' : 'Enter API key'"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- API Secret -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">
              API Secret
              <span v-if="existingWallet?.api_secret_set" class="ml-1 text-xs text-neutral-400 font-normal">(currently set — leave blank to keep)</span>
            </label>
            <input v-model="walletForm.api_secret" type="password" :placeholder="existingWallet?.api_secret_set ? '••••••••••••' : 'Enter API secret (optional)'"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Webhook Secret -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">
              Webhook Secret
              <span v-if="existingWallet?.webhook_secret_set" class="ml-1 text-xs text-neutral-400 font-normal">(currently set — leave blank to keep)</span>
            </label>
            <input v-model="walletForm.webhook_secret" type="password" :placeholder="existingWallet?.webhook_secret_set ? '••••••••••••' : 'Enter webhook secret (optional)'"
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Toggles -->
          <div class="grid grid-cols-3 gap-3 pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="walletForm.is_active" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm font-medium text-neutral-700">Active</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="walletForm.disburse_enabled" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm font-medium text-neutral-700">Auto-Disburse</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="walletForm.debit_enabled" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <span class="text-sm font-medium text-neutral-700">Auto-Debit</span>
            </label>
          </div>

          <p v-if="walletError" class="text-sm text-red-600">{{ walletError }}</p>
        </div>

        <div class="px-6 py-4 border-t border-neutral-200 flex items-center justify-between shrink-0">
          <button
            v-if="existingWallet"
            @click="destroyWallet"
            :disabled="walletSaving"
            class="px-4 py-2 text-sm text-red-600 hover:text-red-700 font-medium disabled:opacity-40"
          >Remove Wallet</button>
          <div v-else></div>
          <div class="flex gap-3">
            <button @click="closeWallet" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
            <button
              @click="saveWallet"
              :disabled="walletSaving || walletLoading"
              class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold disabled:opacity-50"
            >
              {{ walletSaving ? 'Saving…' : (existingWallet ? 'Update Wallet' : 'Save Wallet') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const auth = useLandlordAuth()

// ─── State ───────────────────────────────────────────────────────────────────
const tenants    = ref([])
const loading    = ref(true)
const pagination = ref({ total: 0, per_page: 20, current_page: 1, last_page: 1 })
const filters    = reactive({ search: '', status: '', plan: '', page: 1 })

// Create
const showCreate  = ref(false)
const creating    = ref(false)
const createError = ref('')
const createForm  = reactive({ name: '', plan: 'starter', subdomain: '', currency: 'ZMW', timezone: 'Africa/Lusaka' })

// Edit
const showEdit    = ref(false)
const editing     = ref(false)
const editError   = ref('')
const editTarget  = ref(null)
const editForm    = reactive({ name: '', plan: '', status: '', currency: '', timezone: '', trial_ends_at: '' })

// Action confirm
const confirmTarget  = ref(null)
const confirmAction_ = ref('')
const actioning      = ref(false)

// Verify / Unverify
const showVerify   = ref(false)
const verifyTarget = ref(null)
const verifyNote   = ref('')
const verifyError  = ref('')
const verifying    = ref(null)

// Send reminders
const reminderTarget   = ref(null)
const reminderSending  = ref(null)
const reminderError    = ref('')
const reminderCooldowns = ref({})   // tenantId → "~X min" label

// Wallet config
const showWallet    = ref(false)
const walletTarget  = ref(null)
const walletLoading = ref(false)
const walletSaving  = ref(false)
const walletError   = ref('')
const existingWallet = ref(null)
const walletForm    = reactive({
  gateway: 'flutterwave',
  environment: 'sandbox',
  wallet_id: '',
  api_key: '',
  api_secret: '',
  webhook_secret: '',
  is_active: true,
  disburse_enabled: false,
  debit_enabled: false,
})

// Debounce
let debounceTimer = null
function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchTenants(), 350)
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!auth.isAuthenticated) {
    router.visit(route('landlord.login'))
    return
  }
  await fetchTenants()
})

// ─── Data ─────────────────────────────────────────────────────────────────────
async function fetchTenants() {
  loading.value = true
  try {
    const params = {}
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.plan)   params.plan   = filters.plan
    params.page = filters.page

    const { data } = await axios.get('/api/v1/landlord/tenants', { params })
    tenants.value    = data.data?.data       ?? []
    pagination.value = data.data?.pagination ?? pagination.value
  } catch {
    auth.clearAuth()
    router.visit(route('landlord.login'))
  } finally {
    loading.value = false
  }
}

function changePage(page) {
  filters.page = page
  fetchTenants()
}

// ─── Create ───────────────────────────────────────────────────────────────────
function closeCreate() {
  showCreate.value  = false
  createError.value = ''
  Object.assign(createForm, { name: '', plan: 'starter', subdomain: '', currency: 'ZMW', timezone: 'Africa/Lusaka' })
}

async function submitCreate() {
  if (!createForm.name.trim()) { createError.value = 'Name is required.'; return }
  creating.value    = true
  createError.value = ''
  try {
    await axios.post('/api/v1/landlord/tenants', createForm)
    closeCreate()
    filters.page = 1
    await fetchTenants()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Failed to create tenant.'
  } finally {
    creating.value = false
  }
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
function openEdit(tenant) {
  editTarget.value = tenant
  Object.assign(editForm, {
    name:          tenant.name,
    plan:          tenant.plan,
    status:        tenant.status,
    currency:      tenant.currency,
    timezone:      tenant.timezone,
    trial_ends_at: tenant.trial_ends_at ?? '',
  })
  editError.value = ''
  showEdit.value  = true
}

async function submitEdit() {
  editing.value   = true
  editError.value = ''
  try {
    await axios.put(`/api/v1/landlord/tenants/${editTarget.value.id}`, editForm)
    showEdit.value = false
    await fetchTenants()
  } catch (e) {
    editError.value = e.response?.data?.message ?? 'Failed to update tenant.'
  } finally {
    editing.value = false
  }
}

// ─── Send Reminders ───────────────────────────────────────────────────────────
function confirmReminder(tenant) {
  reminderError.value  = ''
  reminderTarget.value = tenant
}

async function executeReminder() {
  const tenant = reminderTarget.value
  reminderSending.value = tenant.id
  reminderError.value   = ''
  try {
    const { data } = await axios.post(`/api/v1/landlord/tenants/${tenant.id}/push-reminders`)
    reminderTarget.value = null

    // Show cooldown label — parse ISO expires time
    const until = new Date(data.data?.cooldown_until)
    const minsLeft = Math.ceil((until - Date.now()) / 60000)
    reminderCooldowns.value[tenant.id] = `~${minsLeft}m cooldown`

    // Clear label after cooldown expires
    setTimeout(() => { delete reminderCooldowns.value[tenant.id] }, minsLeft * 60 * 1000)
  } catch (e) {
    reminderError.value = e.response?.data?.message ?? 'Failed to send reminders.'
  } finally {
    reminderSending.value = null
  }
}

// ─── Verify / Unverify ────────────────────────────────────────────────────────
function openVerify(tenant) {
  verifyTarget.value = tenant
  verifyNote.value   = ''
  verifyError.value  = ''
  showVerify.value   = true
}

async function submitVerify() {
  verifying.value   = verifyTarget.value.id
  verifyError.value = ''
  try {
    await axios.post(`/api/v1/landlord/tenants/${verifyTarget.value.id}/verify`, { note: verifyNote.value || null })
    showVerify.value = false
    await fetchTenants()
  } catch (e) {
    verifyError.value = e.response?.data?.message ?? 'Failed to grant badge.'
  } finally {
    verifying.value = null
  }
}

async function revokeVerification(tenant) {
  verifying.value = tenant.id
  try {
    await axios.delete(`/api/v1/landlord/tenants/${tenant.id}/verify`)
    await fetchTenants()
  } catch {
    // silent
  } finally {
    verifying.value = null
  }
}

// ─── Wallet Config ────────────────────────────────────────────────────────────
async function openWallet(tenant) {
  walletTarget.value  = tenant
  walletError.value   = ''
  existingWallet.value = null
  walletLoading.value  = true
  showWallet.value     = true

  try {
    const { data } = await axios.get(`/api/v1/landlord/tenants/${tenant.id}/wallet`)
    const w = data.data
    existingWallet.value = w

    if (w) {
      Object.assign(walletForm, {
        gateway:          w.gateway,
        environment:      w.environment,
        wallet_id:        w.wallet_id ?? '',
        api_key:          '',
        api_secret:       '',
        webhook_secret:   '',
        is_active:        w.is_active,
        disburse_enabled: w.disburse_enabled,
        debit_enabled:    w.debit_enabled,
      })
    } else {
      Object.assign(walletForm, {
        gateway: 'flutterwave', environment: 'sandbox', wallet_id: '',
        api_key: '', api_secret: '', webhook_secret: '',
        is_active: true, disburse_enabled: false, debit_enabled: false,
      })
    }
  } catch {
    walletError.value = 'Failed to load wallet configuration.'
  } finally {
    walletLoading.value = false
  }
}

function closeWallet() {
  showWallet.value     = false
  walletTarget.value   = null
  existingWallet.value = null
  walletError.value    = ''
}

async function saveWallet() {
  walletSaving.value = true
  walletError.value  = ''

  // Build payload — omit blank secret fields so server keeps existing values
  const payload = {
    gateway:          walletForm.gateway,
    environment:      walletForm.environment,
    wallet_id:        walletForm.wallet_id || null,
    is_active:        walletForm.is_active,
    disburse_enabled: walletForm.disburse_enabled,
    debit_enabled:    walletForm.debit_enabled,
  }
  // Only include secret fields if provided (server keeps existing value when absent)
  if (walletForm.api_key)        payload.api_key        = walletForm.api_key
  if (walletForm.api_secret)     payload.api_secret     = walletForm.api_secret
  if (walletForm.webhook_secret) payload.webhook_secret = walletForm.webhook_secret

  // If no existing wallet, api_key is required
  if (!existingWallet.value && !walletForm.api_key) {
    walletError.value = 'API Key is required.'
    walletSaving.value = false
    return
  }

  try {
    await axios.put(`/api/v1/landlord/tenants/${walletTarget.value.id}/wallet`, payload)
    closeWallet()
  } catch (e) {
    walletError.value = e.response?.data?.message ?? 'Failed to save wallet configuration.'
  } finally {
    walletSaving.value = false
  }
}

async function destroyWallet() {
  if (!confirm(`Remove wallet configuration for ${walletTarget.value.name}?`)) return
  walletSaving.value = true
  try {
    await axios.delete(`/api/v1/landlord/tenants/${walletTarget.value.id}/wallet`)
    closeWallet()
  } catch {
    walletError.value = 'Failed to remove wallet.'
  } finally {
    walletSaving.value = false
  }
}

// ─── Suspend / Activate ───────────────────────────────────────────────────────
function confirmAction(tenant, action) {
  confirmTarget.value  = tenant
  confirmAction_.value = action
}

async function executeAction() {
  actioning.value = true
  try {
    await axios.post(`/api/v1/landlord/tenants/${confirmTarget.value.id}/${confirmAction_.value}`)
    confirmTarget.value = null
    await fetchTenants()
  } catch {
    confirmTarget.value = null
  } finally {
    actioning.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
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
</script>
