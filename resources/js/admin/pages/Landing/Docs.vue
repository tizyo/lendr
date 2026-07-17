<template>
  <LandingLayout>

    <!-- Header -->
    <section class="bg-[#0A1628] text-white py-16 px-4">
      <div class="max-w-5xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <span class="inline-block bg-blue-600/20 text-blue-400 text-xs font-bold px-3 py-1 rounded-full mb-3 uppercase tracking-wider">API Docs</span>
          <h1 class="text-4xl font-black mb-2">LENDR REST API</h1>
          <p class="text-white/60">Version 1.0 · Base URL: <code class="text-blue-300 bg-white/10 px-1.5 py-0.5 rounded text-sm">https://{tenant}.lendr.app/api/v1</code></p>
        </div>
        <div class="flex gap-3">
          <a href="/onboarding" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition">Get API key</a>
          <a href="/contact" class="border border-white/20 hover:border-white/40 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Contact support</a>
        </div>
      </div>
    </section>

    <div class="flex max-w-6xl mx-auto px-4 py-10 gap-8">

      <!-- Sidebar nav -->
      <aside class="hidden lg:block w-56 shrink-0">
        <nav class="sticky top-24 space-y-1">
          <p class="text-xs font-bold text-neutral-400 uppercase tracking-wider mb-3">Reference</p>
          <a v-for="section in sections" :key="section.id"
            :href="`#${section.id}`"
            class="block px-3 py-1.5 rounded-lg text-sm text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition">
            {{ section.title }}
          </a>
        </nav>
      </aside>

      <!-- Content -->
      <main class="flex-1 min-w-0 space-y-14">

        <!-- Authentication -->
        <section id="authentication" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-4">Authentication</h2>
          <p class="text-neutral-600 text-sm leading-relaxed mb-4">The LENDR API uses Bearer token authentication. Include your API token in the <code class="bg-neutral-100 px-1.5 py-0.5 rounded text-xs font-mono">Authorization</code> header of every request.</p>
          <div class="bg-neutral-900 rounded-xl p-5 text-sm font-mono text-green-300 overflow-x-auto">
            <span class="text-white/40"># Example request</span><br/>
            curl -X GET "https://acme.lendr.app/api/v1/borrowers" \<br/>
            &nbsp;&nbsp;-H "Authorization: Bearer <span class="text-yellow-300">YOUR_API_TOKEN</span>" \<br/>
            &nbsp;&nbsp;-H "Accept: application/json"
          </div>
          <p class="text-sm text-neutral-500 mt-3">API tokens can be generated from <strong>Settings → API Clients</strong> in your LENDR dashboard. Tokens are plan-gated — API access requires the Growth or Enterprise plan.</p>
        </section>

        <!-- Rate Limiting -->
        <section id="rate-limiting" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-4">Rate Limiting</h2>
          <p class="text-neutral-600 text-sm leading-relaxed mb-4">API requests are rate-limited per token. Exceeding the limit returns a <code class="bg-neutral-100 px-1.5 py-0.5 rounded text-xs font-mono">429 Too Many Requests</code> response with a <code class="bg-neutral-100 px-1.5 py-0.5 rounded text-xs font-mono">Retry-After</code> header.</p>
          <div class="border border-neutral-200 rounded-xl overflow-hidden text-sm">
            <table class="w-full">
              <thead class="bg-neutral-50 text-left">
                <tr>
                  <th class="px-4 py-2.5 font-semibold text-neutral-700">Plan</th>
                  <th class="px-4 py-2.5 font-semibold text-neutral-700">Limit</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-100">
                <tr><td class="px-4 py-2.5 text-neutral-600">Growth</td><td class="px-4 py-2.5 text-neutral-600">600 requests / minute</td></tr>
                <tr><td class="px-4 py-2.5 text-neutral-600">Enterprise</td><td class="px-4 py-2.5 text-neutral-600">2,000 requests / minute</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Endpoints -->
        <section id="endpoints" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-6">Endpoints</h2>
          <div class="space-y-4">
            <div v-for="group in endpointGroups" :key="group.name">
              <p class="text-sm font-bold text-neutral-500 uppercase tracking-wider mb-3">{{ group.name }}</p>
              <div class="space-y-2">
                <div v-for="ep in group.endpoints" :key="ep.path"
                  class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 bg-white border border-neutral-200 rounded-xl px-4 py-3">
                  <span :class="['text-xs font-bold px-2 py-0.5 rounded font-mono w-14 text-center shrink-0', ep.method === 'GET' ? 'bg-green-100 text-green-700' : ep.method === 'POST' ? 'bg-blue-100 text-blue-700' : ep.method === 'PUT' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
                    {{ ep.method }}
                  </span>
                  <code class="text-sm font-mono text-neutral-700 flex-1">{{ ep.path }}</code>
                  <span class="text-xs text-neutral-400">{{ ep.description }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Responses -->
        <section id="responses" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-4">Response Format</h2>
          <p class="text-neutral-600 text-sm leading-relaxed mb-4">All responses are JSON. Paginated lists follow Laravel's default pagination structure.</p>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <p class="text-xs font-bold text-green-600 mb-2">Success (200)</p>
              <div class="bg-neutral-900 rounded-xl p-4 text-xs font-mono text-green-300 overflow-x-auto">
                {<br/>
                &nbsp;&nbsp;"data": { ... },<br/>
                &nbsp;&nbsp;"message": "OK"<br/>
                }
              </div>
            </div>
            <div>
              <p class="text-xs font-bold text-red-600 mb-2">Error (4xx / 5xx)</p>
              <div class="bg-neutral-900 rounded-xl p-4 text-xs font-mono text-red-300 overflow-x-auto">
                {<br/>
                &nbsp;&nbsp;"message": "Unauthenticated.",<br/>
                &nbsp;&nbsp;"errors": { ... }<br/>
                }
              </div>
            </div>
          </div>
        </section>

        <!-- Webhooks -->
        <section id="webhooks" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-4">Webhooks</h2>
          <p class="text-neutral-600 text-sm leading-relaxed mb-4">LENDR can notify your system of events via HTTP webhooks. Configure webhook URLs in <strong>Settings → Webhooks</strong>.</p>
          <div class="space-y-3">
            <div v-for="event in webhookEvents" :key="event.name" class="flex items-start gap-3 bg-neutral-50 rounded-xl px-4 py-3">
              <code class="text-xs font-mono bg-white border border-neutral-200 px-2 py-0.5 rounded text-blue-700 shrink-0">{{ event.name }}</code>
              <span class="text-sm text-neutral-600">{{ event.description }}</span>
            </div>
          </div>
        </section>

        <!-- SDKs -->
        <section id="sdks" class="scroll-mt-24">
          <h2 class="text-2xl font-black text-neutral-900 mb-4">SDKs &amp; Tools</h2>
          <p class="text-neutral-600 text-sm leading-relaxed mb-4">Official SDKs are in progress. In the meantime, use any HTTP client with your API token.</p>
          <div class="grid sm:grid-cols-3 gap-4">
            <div v-for="sdk in sdks" :key="sdk.lang" class="border border-neutral-200 rounded-xl p-4 text-center">
              <div class="text-2xl mb-2">{{ sdk.icon }}</div>
              <p class="font-semibold text-sm text-neutral-700">{{ sdk.lang }}</p>
              <span :class="['text-xs px-2 py-0.5 rounded-full mt-1 inline-block font-medium', sdk.status === 'Available' ? 'bg-green-100 text-green-700' : 'bg-neutral-100 text-neutral-500']">{{ sdk.status }}</span>
            </div>
          </div>
        </section>

      </main>
    </div>

  </LandingLayout>
</template>

<script setup>
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const sections = [
  { id: 'authentication', title: 'Authentication' },
  { id: 'rate-limiting',  title: 'Rate Limiting' },
  { id: 'endpoints',      title: 'Endpoints' },
  { id: 'responses',      title: 'Response Format' },
  { id: 'webhooks',       title: 'Webhooks' },
  { id: 'sdks',           title: 'SDKs & Tools' },
]

const endpointGroups = [
  {
    name: 'Borrowers',
    endpoints: [
      { method: 'GET',    path: '/borrowers',          description: 'List all borrowers (paginated)' },
      { method: 'POST',   path: '/borrowers',          description: 'Create a new borrower' },
      { method: 'GET',    path: '/borrowers/{id}',     description: 'Get a single borrower' },
      { method: 'PUT',    path: '/borrowers/{id}',     description: 'Update borrower details' },
      { method: 'DELETE', path: '/borrowers/{id}',     description: 'Archive a borrower' },
    ],
  },
  {
    name: 'Loans',
    endpoints: [
      { method: 'GET',  path: '/loans',                description: 'List loans with filters' },
      { method: 'POST', path: '/loans',                description: 'Create a loan application' },
      { method: 'GET',  path: '/loans/{id}',           description: 'Get loan details + schedule' },
      { method: 'POST', path: '/loans/{id}/approve',   description: 'Approve a pending loan' },
      { method: 'POST', path: '/loans/{id}/disburse',  description: 'Mark loan as disbursed' },
    ],
  },
  {
    name: 'Payments',
    endpoints: [
      { method: 'GET',  path: '/payments',             description: 'List all payments' },
      { method: 'POST', path: '/payments',             description: 'Record a manual payment' },
      { method: 'GET',  path: '/payments/{id}',        description: 'Get payment details' },
    ],
  },
  {
    name: 'Reports',
    endpoints: [
      { method: 'GET',  path: '/reports/{type}',                     description: 'Run a report (loans, payments, PAR, etc.)' },
      { method: 'GET',  path: '/reports/{type}/export?format=xlsx',  description: 'Export report as PDF / Excel / CSV' },
    ],
  },
]

const webhookEvents = [
  { name: 'loan.approved',       description: 'Fired when a loan is approved by a staff member or auto-approval rule.' },
  { name: 'loan.disbursed',      description: 'Fired when a loan is marked as disbursed.' },
  { name: 'payment.received',    description: 'Fired when a repayment is recorded (manual or mobile money).' },
  { name: 'loan.overdue',        description: 'Fired daily for loans that have crossed their due date without full repayment.' },
  { name: 'borrower.created',    description: 'Fired when a new borrower profile is created.' },
  { name: 'kyc.approved',        description: 'Fired when a borrower\'s KYC documents are approved.' },
]

const sdks = [
  { icon: '🐘', lang: 'PHP',        status: 'Available' },
  { icon: '🟨', lang: 'JavaScript', status: 'Coming soon' },
  { icon: '🐍', lang: 'Python',     status: 'Coming soon' },
]
</script>
