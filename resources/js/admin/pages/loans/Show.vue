<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <Link :href="route('loans.index')" class="text-neutral-400 hover:text-neutral-600">← Loans</Link>
          <span class="text-neutral-300">/</span>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-bold text-neutral-900 font-mono">{{ loan.loan_number }}</h1>
              <LoanStatusBadge :status="loan.status" :label="loan.status_label" />
            </div>
            <p class="text-sm text-neutral-500 mt-0.5">
              <Link :href="route('borrowers.show', loan.borrower.id)" class="hover:underline text-primary-600">
                {{ loan.borrower.name }}
              </Link>
              · {{ loan.borrower.borrower_number }}
            </p>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-wrap gap-2">
          <button
            v-if="can.approve && loan.status === 'submitted'"
            @click="openAction('approve')"
            class="btn-success text-sm"
          >Approve</button>
          <button
            v-if="can.deny && ['submitted', 'approved'].includes(loan.status)"
            @click="openAction('deny')"
            class="btn-danger text-sm"
          >Deny</button>
          <button
            v-if="can.disburse && loan.status === 'approved'"
            @click="showDisburse = true"
            class="btn-primary text-sm"
          >Disburse</button>
          <button
            v-if="can.record_payment && ['active', 'disbursed', 'defaulted'].includes(loan.status)"
            @click="showPaymentForm = true"
            class="btn-primary text-sm"
          >Record Payment</button>
          <button
            v-if="can.freeze && ['active', 'disbursed'].includes(loan.status)"
            @click="openAction('freeze')"
            class="btn-secondary text-sm"
          >Freeze</button>
          <button
            v-if="can.freeze && loan.status === 'frozen'"
            @click="openAction('unfreeze')"
            class="btn-secondary text-sm"
          >Unfreeze</button>
          <button
            v-if="can.write_off && ['frozen', 'defaulted'].includes(loan.status)"
            @click="openAction('write_off')"
            class="btn-danger text-sm"
          >Write Off</button>
          <button
            v-if="can.restructure && ['active', 'disbursed', 'frozen', 'defaulted'].includes(loan.status)"
            @click="showRestructure = true"
            class="btn-secondary text-sm"
          >Restructure</button>
          <button
            v-if="can.topup && ['active', 'disbursed'].includes(loan.status)"
            @click="showTopup = true"
            class="btn-secondary text-sm"
          >Top Up</button>

          <!-- PDF Downloads -->
          <div class="relative" ref="pdfDropdownRef">
            <button
              @click="showPdfMenu = !showPdfMenu"
              class="btn-secondary text-sm flex items-center gap-1.5"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              PDF
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div v-if="showPdfMenu" class="absolute right-0 mt-1 w-48 bg-white border border-neutral-200 rounded-xl shadow-lg z-50 py-1">
              <a :href="route('loans.pdf.agreement', loan.id)" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-neutral-700 hover:bg-neutral-50">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Loan Agreement
              </a>
              <a :href="route('loans.pdf.schedule', loan.id)" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-neutral-700 hover:bg-neutral-50">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Repayment Schedule
              </a>
              <a :href="route('borrowers.pdf.statement', loan.borrower.id)" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm text-neutral-700 hover:bg-neutral-50">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Account Statement
              </a>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 mb-1">Principal</p>
        <p class="text-xl font-bold text-neutral-900">K {{ loan.principal_amount }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 mb-1">Total Payable</p>
        <p class="text-xl font-bold text-neutral-900">K {{ loan.total_payable }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 mb-1">Total Paid</p>
        <p class="text-xl font-bold text-green-700">K {{ loan.total_paid }}</p>
      </div>
      <div class="lendr-card p-4 text-center">
        <p class="text-xs text-neutral-500 mb-1">Outstanding</p>
        <p class="text-xl font-bold" :class="outstandingNum > 0 ? 'text-amber-700' : 'text-green-700'">
          K {{ loan.outstanding_balance }}
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-neutral-200 mb-5">
      <nav class="flex gap-6 -mb-px">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="pb-3 text-sm font-medium border-b-2 transition"
          :class="activeTab === tab.key
            ? 'border-primary-600 text-primary-600'
            : 'border-transparent text-neutral-500 hover:text-neutral-700'"
        >
          {{ tab.label }}
          <span v-if="tab.count" class="ml-1.5 bg-neutral-100 text-neutral-600 px-1.5 py-0.5 rounded text-xs">{{ tab.count }}</span>
        </button>
      </nav>
    </div>

    <!-- Tab: Overview -->
    <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Loan Terms -->
      <div class="lendr-card p-5">
        <h3 class="font-semibold text-neutral-800 mb-4">Loan Terms</h3>
        <dl class="space-y-3 text-sm">
          <DetailRow label="Loan Type" :value="loan.loan_type?.name ?? loan.loan_type" />
          <DetailRow label="Loan Plan" :value="loan.loan_plan?.name ?? loan.loan_plan" />
          <DetailRow label="Interest Rate" :value="`${loan.interest_rate}% ${loan.interest_type}`" />
          <DetailRow label="Tenure" :value="`${loan.tenure} ${loan.tenure_type}`" />
          <DetailRow label="Repayment" :value="loan.repayment_schedule" />
          <DetailRow label="Interest (ZMW)" :value="`K ${loan.interest_amount}`" />
          <DetailRow label="Processing Fee" :value="`K ${loan.processing_fee}`" />
          <DetailRow label="Insurance Fee" :value="`K ${loan.insurance_fee}`" />
          <DetailRow label="Penalty Balance" :value="`K ${loan.penalty_balance}`" />
        </dl>
      </div>

      <!-- Dates & People -->
      <div class="lendr-card p-5">
        <h3 class="font-semibold text-neutral-800 mb-4">Dates & Officers</h3>
        <dl class="space-y-3 text-sm">
          <DetailRow label="Application Date" :value="loan.application_date" />
          <DetailRow label="Approval Date" :value="loan.approval_date || '—'" />
          <DetailRow label="Disbursement Date" :value="loan.disbursement_date || '—'" />
          <DetailRow label="First Repayment" :value="loan.first_repayment_date || '—'" />
          <DetailRow label="Maturity Date" :value="loan.maturity_date || '—'" />
          <DetailRow label="Created By" :value="loan.created_by" />
          <DetailRow label="Approved By" :value="loan.approved_by || '—'" />
          <DetailRow label="Disbursed By" :value="loan.disbursed_by || '—'" />
        </dl>
      </div>

      <!-- Disbursement -->
      <div v-if="loan.disbursement_method" class="lendr-card p-5">
        <h3 class="font-semibold text-neutral-800 mb-4">Disbursement</h3>
        <dl class="space-y-3 text-sm">
          <DetailRow label="Method" :value="loan.disbursement_method" />
          <DetailRow label="Account / Number" :value="loan.disbursement_account || '—'" />
          <DetailRow label="Reference" :value="loan.disbursement_reference || '—'" />
        </dl>
      </div>

      <!-- Purpose & Notes -->
      <div class="lendr-card p-5">
        <h3 class="font-semibold text-neutral-800 mb-4">Purpose & Notes</h3>
        <dl class="space-y-3 text-sm">
          <DetailRow label="Loan Purpose" :value="loan.loan_purpose || '—'" />
          <DetailRow v-if="loan.guarantor_name" label="Guarantor" :value="`${loan.guarantor_name} (${loan.guarantor_relationship || 'N/A'}) · ${loan.guarantor_phone || ''}`" />
          <DetailRow v-if="loan.collateral_description" label="Collateral" :value="loan.collateral_description" />
          <DetailRow v-if="loan.notes" label="Notes" :value="loan.notes" />
        </dl>
      </div>
    </div>

    <!-- Tab: Schedule -->
    <div v-else-if="activeTab === 'schedule'">
      <div v-if="!loan.schedule.length" class="lendr-card p-8 text-center text-neutral-400">
        Schedule will be generated upon disbursement.
      </div>
      <div v-else class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">#</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Due Date</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Total Due</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Paid</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Outstanding</th>
                <th class="text-center px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr
                v-for="row in loan.schedule"
                :key="row.id"
                :class="[
                  row.is_paid ? 'bg-green-50' : (row.days_overdue > 0 ? 'bg-red-50' : ''),
                ]"
              >
                <td class="px-5 py-3 text-neutral-500">{{ row.instalment_number }}</td>
                <td class="px-5 py-3 font-medium text-neutral-900">{{ row.due_date }}</td>
                <td class="px-5 py-3 text-right">K {{ row.total_due }}</td>
                <td class="px-5 py-3 text-right text-green-700">K {{ row.total_paid }}</td>
                <td class="px-5 py-3 text-right font-medium" :class="parseFloat(row.outstanding.replace(/,/g,'')) > 0 ? 'text-amber-700' : 'text-neutral-400'">
                  K {{ row.outstanding }}
                </td>
                <td class="px-5 py-3 text-center">
                  <span v-if="row.is_paid" class="lendr-badge-success text-xs">Paid</span>
                  <span v-else-if="row.days_overdue > 0" class="lendr-badge-danger text-xs">{{ row.days_overdue }}d overdue</span>
                  <span v-else class="lendr-badge-neutral text-xs">Pending</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Payments -->
    <div v-else-if="activeTab === 'payments'">
      <div v-if="!loan.payments.length" class="lendr-card p-8 text-center text-neutral-400">
        No payments recorded yet.
      </div>
      <div v-else class="lendr-card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Receipt</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Date</th>
                <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase">Amount</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden md:table-cell">Method</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden lg:table-cell">Reference</th>
                <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase hidden lg:table-cell">Recorded By</th>
                <th class="px-5 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="p in loan.payments" :key="p.id">
                <td class="px-5 py-3 font-mono text-xs text-neutral-600">{{ p.receipt_number }}</td>
                <td class="px-5 py-3 text-neutral-800">{{ p.payment_date }}</td>
                <td class="px-5 py-3 text-right font-semibold text-green-700">K {{ p.amount }}</td>
                <td class="px-5 py-3 text-neutral-600 hidden md:table-cell">{{ p.payment_method }}</td>
                <td class="px-5 py-3 text-neutral-500 hidden lg:table-cell">{{ p.reference || '—' }}</td>
                <td class="px-5 py-3 text-neutral-500 hidden lg:table-cell">{{ p.recorded_by }}</td>
                <td class="px-5 py-3 text-right">
                  <a :href="route('payments.pdf.receipt', p.id)" target="_blank" class="text-xs text-emerald-600 hover:underline font-medium">Receipt PDF</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: History -->
    <div v-else-if="activeTab === 'history'">
      <ol class="relative border-l border-neutral-200 ml-3 space-y-6">
        <li v-for="log in loan.status_logs" :key="log.created_at" class="ml-6">
          <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full bg-white border-2 border-primary-400">
            <svg class="w-2.5 h-2.5 text-primary-600" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
          </span>
          <div class="lendr-card p-4">
            <div class="flex items-center gap-2 mb-1">
              <LoanStatusBadge :status="log.to_status" :label="log.to_label" />
              <span class="text-xs text-neutral-400">{{ log.created_at }}</span>
              <span class="text-xs text-neutral-500">by {{ log.changed_by || 'System' }}</span>
            </div>
            <p v-if="log.notes" class="text-sm text-neutral-600">{{ log.notes }}</p>
          </div>
        </li>
      </ol>
    </div>

    <!-- Tab: Documents -->
    <div v-else-if="activeTab === 'documents'">
      <div class="lendr-card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-neutral-800">Loan Documents</h3>
          <label class="btn-primary text-sm cursor-pointer">
            <input type="file" class="sr-only" @change="uploadDocument" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" />
            + Upload Document
          </label>
        </div>

        <!-- Upload form -->
        <div v-if="uploadFile" class="mb-4 p-4 bg-neutral-50 rounded-lg space-y-3 border border-neutral-200">
          <p class="text-sm font-medium text-neutral-700">{{ uploadFile.name }}</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label">Document Type *</label>
              <select v-model="uploadForm.document_type" class="input w-full">
                <option value="">Select type…</option>
                <option value="national_id">National ID</option>
                <option value="payslip">Payslip</option>
                <option value="bank_statement">Bank Statement</option>
                <option value="contract">Employment Contract</option>
                <option value="collateral">Collateral Evidence</option>
                <option value="signed_agreement">Signed Loan Agreement</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="label">Title (optional)</label>
              <input v-model="uploadForm.title" type="text" class="input w-full" placeholder="Document title" />
            </div>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="cancelUpload" class="btn-secondary text-sm">Cancel</button>
            <button @click="confirmUpload" :disabled="uploading || !uploadForm.document_type" class="btn-primary text-sm">
              {{ uploading ? 'Uploading…' : 'Upload' }}
            </button>
          </div>
        </div>

        <!-- Document list -->
        <div v-if="!documents.length && !uploadFile" class="text-sm text-neutral-400 py-6 text-center">
          No documents uploaded yet.
        </div>
        <ul v-else class="divide-y divide-neutral-100">
          <li v-for="doc in documents" :key="doc.id" class="py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-neutral-800 truncate">{{ doc.title }}</p>
                <p class="text-xs text-neutral-400">{{ doc.document_type }} · {{ doc.uploaded_by }} · {{ doc.created_at }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <a :href="doc.file_path" target="_blank" class="text-xs text-primary-600 hover:underline">Download</a>
              <button @click="deleteDocument(doc)" class="text-xs text-red-500 hover:text-red-700">Delete</button>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Tab: Guarantors -->
    <div v-else-if="activeTab === 'guarantors'">
      <div class="lendr-card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-neutral-800">Guarantors</h3>
          <button @click="openGuarantorForm()" class="btn-primary text-sm">+ Add Guarantor</button>
        </div>
        <div v-if="!guarantors.length" class="text-sm text-neutral-400 py-6 text-center">No guarantors recorded yet.</div>
        <ul v-else class="divide-y divide-neutral-100">
          <li v-for="g in guarantors" :key="g.id" class="py-4">
            <div class="flex items-start justify-between gap-4">
              <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-neutral-900">{{ g.name }}</span>
                  <span
                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="{
                      'bg-emerald-100 text-emerald-800': g.status === 'approved',
                      'bg-red-100 text-red-800':         g.status === 'rejected',
                      'bg-amber-100 text-amber-800':     g.status === 'pending',
                    }"
                  >{{ g.status_badge?.label }}</span>
                </div>
                <p class="text-sm text-neutral-500">
                  {{ [g.relationship, g.phone, g.email].filter(Boolean).join(' · ') || '—' }}
                </p>
                <p v-if="g.employer" class="text-sm text-neutral-500">
                  {{ g.employer }}{{ g.monthly_income ? ` · K ${Number(g.monthly_income).toLocaleString()} /mo` : '' }}
                </p>
                <p v-if="g.notes" class="text-sm text-neutral-400 italic">{{ g.notes }}</p>
              </div>
              <div class="flex gap-2 shrink-0">
                <button @click="openGuarantorForm(g)" class="text-xs text-primary-600 hover:underline">Edit</button>
                <button @click="deleteGuarantor(g)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Tab: Collateral -->
    <div v-else-if="activeTab === 'collateral'">
      <div class="lendr-card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-neutral-800">Collateral Items</h3>
          <button @click="openCollateralForm()" class="btn-primary text-sm">+ Add Item</button>
        </div>
        <div v-if="!collateralItems.length" class="text-sm text-neutral-400 py-6 text-center">No collateral items recorded yet.</div>
        <ul v-else class="divide-y divide-neutral-100">
          <li v-for="c in collateralItems" :key="c.id" class="py-4">
            <div class="flex items-start justify-between gap-4">
              <div class="space-y-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-neutral-900">{{ c.description }}</span>
                  <span class="text-xs bg-neutral-100 text-neutral-600 px-2 py-0.5 rounded-full">{{ c.type_label }}</span>
                  <span
                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="{
                      'bg-emerald-100 text-emerald-800': c.status === 'verified',
                      'bg-neutral-100 text-neutral-600': c.status === 'released',
                      'bg-amber-100 text-amber-800':     c.status === 'pending',
                    }"
                  >{{ c.status_badge?.label }}</span>
                </div>
                <p class="text-sm text-neutral-500">
                  <template v-if="c.estimated_value">Est. K {{ Number(c.estimated_value).toLocaleString() }}</template>
                  <template v-if="c.assessed_value"> · Assessed K {{ Number(c.assessed_value).toLocaleString() }}</template>
                  <template v-if="c.assessment_date"> · {{ c.assessment_date }}</template>
                </p>
                <p v-if="c.location" class="text-sm text-neutral-500">{{ c.location }}</p>
                <p v-if="c.notes" class="text-sm text-neutral-400 italic">{{ c.notes }}</p>
              </div>
              <div class="flex gap-2 shrink-0">
                <button @click="openCollateralForm(c)" class="text-xs text-primary-600 hover:underline">Edit</button>
                <button @click="deleteCollateral(c)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Tab: Top Ups -->
    <div v-else-if="activeTab === 'topups'">
      <div class="lendr-card p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-neutral-800">Top-Up Requests</h3>
          <button
            v-if="can.topup && ['active', 'disbursed'].includes(loan.status)"
            @click="showTopup = true"
            class="btn-primary text-sm"
          >+ Request Top Up</button>
        </div>
        <div v-if="!topups.length" class="text-sm text-neutral-400 py-6 text-center">No top-up requests yet.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-100">
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Date</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Amount</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">New Tenure</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Requested By</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-neutral-500 uppercase">Approved By</th>
                <th v-if="can.topup" class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-50">
              <tr v-for="t in topups" :key="t.id">
                <td class="px-4 py-3 text-neutral-500">{{ t.created_at }}</td>
                <td class="px-4 py-3 text-right font-semibold text-neutral-900">K {{ t.topup_amount }}</td>
                <td class="px-4 py-3 text-right text-neutral-600">{{ t.new_tenure ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                  <span
                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="{
                      'bg-amber-100 text-amber-800':   t.status === 'pending',
                      'bg-emerald-100 text-emerald-800': t.status === 'approved',
                      'bg-red-100 text-red-800':       t.status === 'rejected',
                    }"
                  >{{ t.status }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-600">{{ t.requested_by ?? '—' }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ t.approved_by ?? '—' }}</td>
                <td v-if="can.topup" class="px-4 py-3 text-right">
                  <template v-if="t.status === 'pending'">
                    <button @click="approveTopup(t)" class="text-xs text-emerald-600 hover:underline mr-3">Approve</button>
                    <button @click="openRejectTopup(t)" class="text-xs text-red-500 hover:underline">Reject</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ─── Modals ──────────────────────────────────────────────────────────── -->

    <!-- Generic action modal (approve / deny / freeze / write_off / unfreeze) -->
    <div v-if="actionModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-1 capitalize">{{ actionModal.title }}</h3>
        <p class="text-sm text-neutral-500 mb-4">{{ actionModal.desc }}</p>
        <textarea
          v-model="actionModal.notes"
          :placeholder="actionModal.required ? 'Reason is required…' : 'Optional notes…'"
          rows="3"
          class="input w-full mb-4"
        ></textarea>
        <div class="flex gap-3 justify-end">
          <button @click="actionModal.show = false" class="btn-secondary">Cancel</button>
          <button @click="confirmAction" :disabled="actionModal.submitting" class="btn-primary">
            {{ actionModal.submitting ? 'Processing…' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Disburse Modal -->
    <div v-if="showDisburse" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4">Disburse Loan</h3>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Disbursement Method <span class="text-red-500">*</span></label>
              <select v-model="disburseForm.disbursement_method" class="input w-full">
                <option value="">Select…</option>
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="zamtel_kwacha">Zamtel Kwacha</option>
              </select>
            </div>
            <div>
              <label class="label">Disbursement Date <span class="text-red-500">*</span></label>
              <input v-model="disburseForm.disbursement_date" type="date" class="input w-full" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Account / Phone Number</label>
              <input v-model="disburseForm.disbursement_account" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Reference Number</label>
              <input v-model="disburseForm.disbursement_reference" type="text" class="input w-full" />
            </div>
          </div>
          <div>
            <label class="label">First Repayment Date (optional)</label>
            <input v-model="disburseForm.first_repayment_date" type="date" class="input w-full" />
          </div>
          <div>
            <label class="label">Notes</label>
            <input v-model="disburseForm.notes" type="text" class="input w-full" />
          </div>
        </div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="showDisburse = false" class="btn-secondary">Cancel</button>
          <button @click="confirmDisburse" :disabled="disburseSubmitting" class="btn-primary">
            {{ disburseSubmitting ? 'Processing…' : 'Disburse & Activate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Restructure Modal -->
    <div v-if="showRestructure" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-1">Restructure Loan</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Outstanding balance <strong>K {{ loan.outstanding_balance }}</strong> will be recalculated over the new tenure.
          All unpaid installments will be regenerated.
        </p>
        <div class="space-y-4">
          <div>
            <label class="label">New Tenure <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-2">
              <input v-model.number="restructureForm.tenure" type="number" min="1" class="input w-32" placeholder="e.g. 12" />
              <span class="text-sm text-neutral-500">{{ loan.tenure_type ?? 'months' }}</span>
            </div>
          </div>
          <div>
            <label class="label">Reason <span class="text-red-500">*</span></label>
            <textarea v-model="restructureForm.reason" rows="3" class="input w-full resize-none" placeholder="Reason for restructuring…"></textarea>
          </div>
        </div>
        <div v-if="restructureError" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {{ restructureError }}
        </div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="showRestructure = false; restructureError = ''" class="btn-secondary">Cancel</button>
          <button @click="confirmRestructure" :disabled="restructureSubmitting" class="btn-primary">
            {{ restructureSubmitting ? 'Processing…' : 'Confirm Restructure' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Record Payment Modal -->
    <div v-if="showPaymentForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4">Record Payment</h3>
        <div class="space-y-4">
          <div>
            <label class="label">Amount (ZMW) <span class="text-red-500">*</span></label>
            <input v-model="paymentForm.amount" type="number" step="0.01" min="0" class="input w-full" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Payment Method <span class="text-red-500">*</span></label>
              <select v-model="paymentForm.payment_method" class="input w-full">
                <option value="">Select…</option>
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="airtel_money">Airtel Money</option>
                <option value="mtn_momo">MTN MoMo</option>
                <option value="zamtel_kwacha">Zamtel Kwacha</option>
                <option value="cheque">Cheque</option>
              </select>
            </div>
            <div>
              <label class="label">Payment Date <span class="text-red-500">*</span></label>
              <input v-model="paymentForm.payment_date" type="date" class="input w-full" />
            </div>
          </div>
          <div>
            <label class="label">Reference</label>
            <input v-model="paymentForm.reference" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Notes</label>
            <input v-model="paymentForm.notes" type="text" class="input w-full" />
          </div>
        </div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="showPaymentForm = false" class="btn-secondary">Cancel</button>
          <button @click="confirmPayment" :disabled="paymentSubmitting" class="btn-primary">
            {{ paymentSubmitting ? 'Recording…' : 'Record Payment' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Guarantor Modal -->
    <div v-if="guarantorModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4">{{ guarantorModal.editing ? 'Edit Guarantor' : 'Add Guarantor' }}</h3>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="label">Full Name <span class="text-red-500">*</span></label>
            <input v-model="guarantorForm.name" type="text" class="input w-full" placeholder="Full legal name" />
          </div>
          <div>
            <label class="label">National ID</label>
            <input v-model="guarantorForm.national_id" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Phone</label>
            <input v-model="guarantorForm.phone" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="guarantorForm.email" type="email" class="input w-full" />
          </div>
          <div>
            <label class="label">Relationship</label>
            <input v-model="guarantorForm.relationship" type="text" class="input w-full" placeholder="e.g. Spouse, Employer" />
          </div>
          <div>
            <label class="label">Employer</label>
            <input v-model="guarantorForm.employer" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Monthly Income (ZMW)</label>
            <input v-model="guarantorForm.monthly_income" type="number" step="0.01" min="0" class="input w-full" />
          </div>
          <div v-if="guarantorModal.editing">
            <label class="label">Status</label>
            <select v-model="guarantorForm.status" class="input w-full">
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="label">Address</label>
            <textarea v-model="guarantorForm.address" rows="2" class="input w-full resize-none"></textarea>
          </div>
          <div class="col-span-2">
            <label class="label">Notes</label>
            <textarea v-model="guarantorForm.notes" rows="2" class="input w-full resize-none"></textarea>
          </div>
        </div>
        <div v-if="guarantorModal.error" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ guarantorModal.error }}</div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="guarantorModal.show = false" class="btn-secondary">Cancel</button>
          <button @click="saveGuarantor" :disabled="guarantorModal.submitting" class="btn-primary">
            {{ guarantorModal.submitting ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Collateral Modal -->
    <div v-if="collateralModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-4">{{ collateralModal.editing ? 'Edit Collateral Item' : 'Add Collateral Item' }}</h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Type <span class="text-red-500">*</span></label>
            <select v-model="collateralForm.type" class="input w-full">
              <option value="property">Property</option>
              <option value="vehicle">Vehicle</option>
              <option value="equipment">Equipment</option>
              <option value="land">Land</option>
              <option value="savings">Savings/Deposit</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div v-if="collateralModal.editing">
            <label class="label">Status</label>
            <select v-model="collateralForm.status" class="input w-full">
              <option value="pending">Pending</option>
              <option value="verified">Verified</option>
              <option value="released">Released</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="label">Description <span class="text-red-500">*</span></label>
            <input v-model="collateralForm.description" type="text" class="input w-full" placeholder="e.g. 2020 Toyota Hilux, Reg ABC123" />
          </div>
          <div>
            <label class="label">Estimated Value (ZMW)</label>
            <input v-model="collateralForm.estimated_value" type="number" step="0.01" min="0" class="input w-full" />
          </div>
          <div>
            <label class="label">Assessed Value (ZMW)</label>
            <input v-model="collateralForm.assessed_value" type="number" step="0.01" min="0" class="input w-full" />
          </div>
          <div>
            <label class="label">Assessment Date</label>
            <input v-model="collateralForm.assessment_date" type="date" class="input w-full" />
          </div>
          <div>
            <label class="label">Location</label>
            <input v-model="collateralForm.location" type="text" class="input w-full" />
          </div>
          <div class="col-span-2">
            <label class="label">Notes</label>
            <textarea v-model="collateralForm.notes" rows="2" class="input w-full resize-none"></textarea>
          </div>
        </div>
        <div v-if="collateralModal.error" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ collateralModal.error }}</div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="collateralModal.show = false" class="btn-secondary">Cancel</button>
          <button @click="saveCollateral" :disabled="collateralModal.submitting" class="btn-primary">
            {{ collateralModal.submitting ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Top-Up Request Modal -->
    <div v-if="showTopup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-1">Request Loan Top-Up</h3>
        <p class="text-sm text-neutral-500 mb-4">
          Current outstanding balance: <strong>K {{ loan.outstanding_balance }}</strong>
        </p>
        <div class="space-y-4">
          <div>
            <label class="label">Top-Up Amount (ZMW) <span class="text-red-500">*</span></label>
            <input v-model.number="topupForm.topup_amount" type="number" step="0.01" min="1" class="input w-full" placeholder="e.g. 5000" />
          </div>
          <div>
            <label class="label">New Tenure (optional)</label>
            <div class="flex items-center gap-2">
              <input v-model.number="topupForm.new_tenure" type="number" min="1" class="input w-32" placeholder="e.g. 12" />
              <span class="text-sm text-neutral-500">{{ loan.tenure_type ?? 'months' }} (leave blank to keep current)</span>
            </div>
          </div>
          <div>
            <label class="label">Notes</label>
            <textarea v-model="topupForm.notes" rows="3" class="input w-full resize-none" placeholder="Purpose of top-up…"></textarea>
          </div>
        </div>
        <div v-if="topupError" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {{ topupError }}
        </div>
        <div class="flex gap-3 justify-end mt-5">
          <button @click="showTopup = false; topupError = ''" class="btn-secondary">Cancel</button>
          <button @click="submitTopup" :disabled="topupSubmitting" class="btn-primary">
            {{ topupSubmitting ? 'Submitting…' : 'Submit Request' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Top-Up Reject Modal -->
    <div v-if="rejectTopupModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-neutral-900 mb-1">Reject Top-Up</h3>
        <p class="text-sm text-neutral-500 mb-4">Provide a reason for rejecting this top-up request.</p>
        <textarea
          v-model="rejectTopupModal.reason"
          rows="3"
          class="input w-full mb-4"
          placeholder="Rejection reason…"
        ></textarea>
        <div class="flex gap-3 justify-end">
          <button @click="rejectTopupModal.show = false" class="btn-secondary">Cancel</button>
          <button @click="confirmRejectTopup" :disabled="rejectTopupModal.submitting" class="btn-danger">
            {{ rejectTopupModal.submitting ? 'Rejecting…' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import LoanStatusBadge from '@/admin/components/loans/LoanStatusBadge.vue'

// Inline detail row component
const DetailRow = {
  props: { label: String, value: String },
  template: `<div class="flex justify-between gap-4">
    <dt class="text-neutral-500 shrink-0">{{ label }}</dt>
    <dd class="text-neutral-900 font-medium text-right">{{ value }}</dd>
  </div>`,
}

const props = defineProps({
  loan: Object,
  can: Object,
})

const activeTab = ref('overview')
const outstandingNum = computed(() => parseFloat((props.loan.outstanding_balance || '0').replace(/,/g, '')))

const tabs = computed(() => [
  { key: 'overview',    label: 'Overview' },
  { key: 'schedule',    label: 'Schedule',      count: props.loan.schedule?.length },
  { key: 'payments',   label: 'Payments',       count: props.loan.payments?.length },
  { key: 'history',    label: 'Status History', count: props.loan.status_logs?.length },
  { key: 'documents',  label: 'Documents',      count: documents.value.length || undefined },
  { key: 'guarantors', label: 'Guarantors',     count: guarantors.value.length || undefined },
  { key: 'collateral', label: 'Collateral',     count: collateralItems.value.length || undefined },
  { key: 'topups',     label: 'Top Ups',        count: topups.value.length || undefined },
])

// ─── Action Modal ────────────────────────────────────────────────────────────
const actionModal = ref({ show: false, type: '', title: '', desc: '', notes: '', required: false, submitting: false })
const showDisburse = ref(false)
const showPaymentForm = ref(false)
const showPdfMenu = ref(false)
const pdfDropdownRef = ref(null)

const actionConfig = {
  approve:   { title: 'Approve Loan', desc: 'Confirm loan approval.', required: false, url: 'api.v1.loans.approve' },
  deny:      { title: 'Deny Loan', desc: 'Provide a reason for denial.', required: true, url: 'api.v1.loans.deny', field: 'reason' },
  freeze:    { title: 'Freeze Loan', desc: 'Provide a reason for freezing.', required: true, url: 'api.v1.loans.freeze', field: 'reason' },
  unfreeze:  { title: 'Unfreeze Loan', desc: 'Confirm to restore loan to active.', required: false, url: 'api.v1.loans.unfreeze' },
  write_off: { title: 'Write Off Loan', desc: 'This action cannot be undone. Provide a reason.', required: true, url: 'api.v1.loans.write-off', field: 'reason' },
}

function openAction(type) {
  const cfg = actionConfig[type]
  actionModal.value = { show: true, type, title: cfg.title, desc: cfg.desc, notes: '', required: cfg.required, submitting: false }
}

async function confirmAction() {
  const cfg = actionConfig[actionModal.value.type]
  if (cfg.required && !actionModal.value.notes.trim()) {
    alert('Please provide a reason.')
    return
  }
  actionModal.value.submitting = true
  try {
    const payload = cfg.field
      ? { [cfg.field]: actionModal.value.notes, notes: actionModal.value.notes }
      : { notes: actionModal.value.notes }

    await axios.post(route(cfg.url, props.loan.id), payload)
    actionModal.value.show = false
    router.reload({ only: ['loan'] })
  } catch (e) {
    alert(e.response?.data?.message ?? 'Action failed.')
    actionModal.value.submitting = false
  }
}

// ─── Disburse ────────────────────────────────────────────────────────────────
const disburseForm = ref({
  disbursement_method: '',
  disbursement_date: new Date().toISOString().slice(0, 10),
  disbursement_account: '',
  disbursement_reference: '',
  first_repayment_date: '',
  notes: '',
})
const disburseSubmitting = ref(false)

async function confirmDisburse() {
  if (!disburseForm.value.disbursement_method || !disburseForm.value.disbursement_date) {
    alert('Disbursement method and date are required.')
    return
  }
  disburseSubmitting.value = true
  try {
    await axios.post(route('api.v1.loans.disburse', props.loan.id), disburseForm.value)
    showDisburse.value = false
    router.reload({ only: ['loan'] })
  } catch (e) {
    alert(e.response?.data?.message ?? 'Disbursement failed.')
    disburseSubmitting.value = false
  }
}

// ─── Restructure ─────────────────────────────────────────────────────────────
const showRestructure      = ref(false)
const restructureSubmitting = ref(false)
const restructureError     = ref('')
const restructureForm      = ref({ tenure: '', reason: '' })

async function confirmRestructure() {
  restructureError.value = ''
  if (!restructureForm.value.tenure || restructureForm.value.tenure < 1) {
    restructureError.value = 'Please enter a valid tenure.'
    return
  }
  if (!restructureForm.value.reason.trim()) {
    restructureError.value = 'Please provide a reason.'
    return
  }
  restructureSubmitting.value = true
  try {
    await axios.post(route('api.v1.loans.restructure', props.loan.id), restructureForm.value)
    showRestructure.value = false
    restructureForm.value = { tenure: '', reason: '' }
    router.reload({ only: ['loan'] })
  } catch (e) {
    restructureError.value = e.response?.data?.message ?? 'Restructuring failed.'
    restructureSubmitting.value = false
  }
}

// ─── Payment ─────────────────────────────────────────────────────────────────
const paymentForm = ref({
  loan_id: props.loan.id,
  amount: '',
  payment_method: 'cash',
  payment_date: new Date().toISOString().slice(0, 10),
  reference: '',
  notes: '',
})
const paymentSubmitting = ref(false)

// ─── Documents ───────────────────────────────────────────────────────────────
const documents   = ref([])
const uploadFile  = ref(null)
const uploading   = ref(false)
const uploadForm  = ref({ document_type: '', title: '' })

function handleOutsideClick(e) {
  if (pdfDropdownRef.value && !pdfDropdownRef.value.contains(e.target)) {
    showPdfMenu.value = false
  }
}

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
})

function uploadDocument(e) {
  uploadFile.value = e.target.files[0] ?? null
  uploadForm.value = { document_type: '', title: '' }
  e.target.value = ''
}

function cancelUpload() {
  uploadFile.value = null
}

async function confirmUpload() {
  if (!uploadFile.value || !uploadForm.value.document_type) return
  uploading.value = true
  const fd = new FormData()
  fd.append('file', uploadFile.value)
  fd.append('document_type', uploadForm.value.document_type)
  if (uploadForm.value.title) fd.append('title', uploadForm.value.title)
  try {
    const { data } = await axios.post(route('api.v1.loans.documents.store', props.loan.id), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    documents.value.unshift(data.data)
    uploadFile.value = null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Upload failed.')
  } finally {
    uploading.value = false
  }
}

async function deleteDocument(doc) {
  if (!confirm(`Delete "${doc.title}"?`)) return
  try {
    await axios.delete(route('api.v1.loans.documents.destroy', { loan: props.loan.id, document: doc.id }))
    documents.value = documents.value.filter(d => d.id !== doc.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Delete failed.')
  }
}

async function confirmPayment() {
  if (!paymentForm.value.amount || !paymentForm.value.payment_method) {
    alert('Amount and payment method are required.')
    return
  }
  paymentSubmitting.value = true
  try {
    await axios.post(route('api.v1.payments.store'), paymentForm.value)
    showPaymentForm.value = false
    paymentForm.value.amount = ''
    paymentForm.value.reference = ''
    router.reload({ only: ['loan'] })
  } catch (e) {
    alert(e.response?.data?.message ?? 'Payment failed.')
    paymentSubmitting.value = false
  }
}

// ─── Guarantors ───────────────────────────────────────────────────────────────
const guarantors     = ref([])
const guarantorModal = ref({ show: false, editing: null, submitting: false, error: '' })
const guarantorForm  = ref({})

const blankGuarantor = () => ({
  name: '', national_id: '', phone: '', email: '',
  address: '', relationship: '', employer: '', monthly_income: '', notes: '',
})

onMounted(async () => {
  document.addEventListener('click', handleOutsideClick)
  try {
    const [docs, guar, coll, tups] = await Promise.all([
      axios.get(route('api.v1.loans.documents.index', props.loan.id)),
      axios.get(route('api.v1.loans.guarantors.index', props.loan.id)),
      axios.get(route('api.v1.loans.collateral.index', props.loan.id)),
      axios.get(route('api.v1.loans.topups.index', props.loan.id)),
    ])
    documents.value       = docs.data.data  ?? []
    guarantors.value      = guar.data.data  ?? []
    collateralItems.value = coll.data.data  ?? []
    topups.value          = tups.data.data  ?? []
  } catch { /* silent */ }
})

function openGuarantorForm(g = null) {
  guarantorForm.value  = g ? { ...g } : blankGuarantor()
  guarantorModal.value = { show: true, editing: g, submitting: false, error: '' }
}

async function saveGuarantor() {
  if (!guarantorForm.value.name?.trim()) {
    guarantorModal.value.error = 'Name is required.'
    return
  }
  guarantorModal.value.submitting = true
  guarantorModal.value.error = ''
  try {
    if (guarantorModal.value.editing) {
      const { data } = await axios.put(route('api.v1.guarantors.update', guarantorModal.value.editing.id), guarantorForm.value)
      const idx = guarantors.value.findIndex(g => g.id === guarantorModal.value.editing.id)
      if (idx !== -1) guarantors.value[idx] = data.data
    } else {
      const { data } = await axios.post(route('api.v1.loans.guarantors.store', props.loan.id), guarantorForm.value)
      guarantors.value.push(data.data)
    }
    guarantorModal.value.show = false
  } catch (e) {
    guarantorModal.value.error = e.response?.data?.message ?? 'Save failed.'
    guarantorModal.value.submitting = false
  }
}

async function deleteGuarantor(g) {
  if (!confirm(`Remove guarantor "${g.name}"?`)) return
  try {
    await axios.delete(route('api.v1.guarantors.destroy', g.id))
    guarantors.value = guarantors.value.filter(x => x.id !== g.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Delete failed.')
  }
}

// ─── Collateral ───────────────────────────────────────────────────────────────
const collateralItems  = ref([])
const collateralModal  = ref({ show: false, editing: null, submitting: false, error: '' })
const collateralForm   = ref({})

const blankCollateral = () => ({
  type: 'other', description: '', estimated_value: '',
  assessed_value: '', assessment_date: '', location: '', notes: '',
})

function openCollateralForm(c = null) {
  collateralForm.value  = c ? { ...c } : blankCollateral()
  collateralModal.value = { show: true, editing: c, submitting: false, error: '' }
}

async function saveCollateral() {
  if (!collateralForm.value.description?.trim()) {
    collateralModal.value.error = 'Description is required.'
    return
  }
  collateralModal.value.submitting = true
  collateralModal.value.error = ''
  try {
    if (collateralModal.value.editing) {
      const { data } = await axios.put(route('api.v1.collateral.update', collateralModal.value.editing.id), collateralForm.value)
      const idx = collateralItems.value.findIndex(c => c.id === collateralModal.value.editing.id)
      if (idx !== -1) collateralItems.value[idx] = data.data
    } else {
      const { data } = await axios.post(route('api.v1.loans.collateral.store', props.loan.id), collateralForm.value)
      collateralItems.value.push(data.data)
    }
    collateralModal.value.show = false
  } catch (e) {
    collateralModal.value.error = e.response?.data?.message ?? 'Save failed.'
    collateralModal.value.submitting = false
  }
}

async function deleteCollateral(c) {
  if (!confirm(`Remove collateral item "${c.description}"?`)) return
  try {
    await axios.delete(route('api.v1.collateral.destroy', c.id))
    collateralItems.value = collateralItems.value.filter(x => x.id !== c.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Delete failed.')
  }
}

// ─── Top Ups ─────────────────────────────────────────────────────────────────
const topups           = ref([])
const showTopup        = ref(false)
const topupSubmitting  = ref(false)
const topupError       = ref('')
const topupForm        = ref({ topup_amount: '', new_tenure: '', notes: '' })
const rejectTopupModal = ref({ show: false, topup: null, reason: '', submitting: false })

async function submitTopup() {
  topupError.value = ''
  if (!topupForm.value.topup_amount || topupForm.value.topup_amount < 1) {
    topupError.value = 'Please enter a valid top-up amount.'
    return
  }
  topupSubmitting.value = true
  try {
    const payload = {
      topup_amount: topupForm.value.topup_amount,
      notes: topupForm.value.notes || undefined,
    }
    if (topupForm.value.new_tenure) payload.new_tenure = topupForm.value.new_tenure
    const { data } = await axios.post(route('api.v1.loans.topups.store', props.loan.id), payload)
    topups.value.unshift(data.data)
    showTopup.value = false
    topupForm.value = { topup_amount: '', new_tenure: '', notes: '' }
  } catch (e) {
    topupError.value = e.response?.data?.message ?? 'Submission failed.'
    topupSubmitting.value = false
  } finally {
    topupSubmitting.value = false
  }
}

async function approveTopup(t) {
  if (!confirm(`Approve top-up of K ${t.topup_amount}? This will increase the loan principal and regenerate the schedule.`)) return
  try {
    const { data } = await axios.post(route('api.v1.loans.topups.approve', { loan: props.loan.id, topup: t.id }))
    const idx = topups.value.findIndex(x => x.id === t.id)
    if (idx !== -1) topups.value[idx] = data.data
    router.reload({ only: ['loan'] })
  } catch (e) {
    alert(e.response?.data?.message ?? 'Approval failed.')
  }
}

function openRejectTopup(t) {
  rejectTopupModal.value = { show: true, topup: t, reason: '', submitting: false }
}

async function confirmRejectTopup() {
  if (!rejectTopupModal.value.reason.trim()) {
    alert('Please provide a rejection reason.')
    return
  }
  rejectTopupModal.value.submitting = true
  try {
    const { data } = await axios.post(
      route('api.v1.loans.topups.reject', { loan: props.loan.id, topup: rejectTopupModal.value.topup.id }),
      { reason: rejectTopupModal.value.reason }
    )
    const idx = topups.value.findIndex(x => x.id === rejectTopupModal.value.topup.id)
    if (idx !== -1) topups.value[idx] = data.data
    rejectTopupModal.value.show = false
  } catch (e) {
    alert(e.response?.data?.message ?? 'Rejection failed.')
    rejectTopupModal.value.submitting = false
  }
}
</script>
