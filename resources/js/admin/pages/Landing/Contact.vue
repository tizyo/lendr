<template>
  <LandingLayout>

    <!-- Header -->
    <section class="bg-[#0A1628] text-white py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <h1 class="text-4xl font-black mb-4">Get in Touch</h1>
        <p class="text-white/60 text-lg">Have questions about LENDR? Want a personalised demo? Our team is ready to help.</p>
      </div>
    </section>

    <section class="py-16 px-4 bg-white">
      <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-12">

        <!-- Contact form -->
        <div>
          <h2 class="text-2xl font-black text-neutral-900 mb-6">Send us a message</h2>
          <form @submit.prevent="submit" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">First name</label>
                <input v-model="form.first_name" type="text" required placeholder="Mulenga"
                  class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-sm font-medium text-neutral-700 mb-1">Last name</label>
                <input v-model="form.last_name" type="text" required placeholder="Banda"
                  class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Email address</label>
              <input v-model="form.email" type="email" required placeholder="mulenga@sacconame.co.zm"
                class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Organisation</label>
              <input v-model="form.organisation" type="text" placeholder="Sunrise SACCO"
                class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Subject</label>
              <select v-model="form.subject" class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select a subject…</option>
                <option>Product demo request</option>
                <option>Pricing enquiry</option>
                <option>Technical support</option>
                <option>Partnership / integration</option>
                <option>General enquiry</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Message</label>
              <textarea v-model="form.message" rows="4" required placeholder="Tell us how we can help…"
                class="w-full border border-neutral-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
            </div>
            <button type="submit" :disabled="submitted"
              class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition text-sm">
              {{ submitted ? 'Message sent!' : 'Send message' }}
            </button>
          </form>
        </div>

        <!-- Contact details -->
        <div class="space-y-8">
          <div>
            <h2 class="text-2xl font-black text-neutral-900 mb-6">Contact details</h2>
            <div class="space-y-4">
              <div v-for="item in contactDetails" :key="item.label" class="flex items-start gap-4">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 shrink-0 text-lg">{{ item.icon }}</div>
                <div>
                  <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-0.5">{{ item.label }}</p>
                  <p class="text-neutral-800 text-sm font-medium">{{ item.value }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-neutral-50 rounded-2xl p-6 border border-neutral-200">
            <p class="font-bold text-neutral-800 mb-2">Book a Demo</p>
            <p class="text-sm text-neutral-600 mb-4">Want to see LENDR live? Book a 30-minute demo with our team and we'll walk you through the platform tailored to your lending use case.</p>
            <a href="mailto:support@lendr.app?subject=Demo%20Request"
              class="inline-block bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold px-4 py-2 rounded-lg transition">
              Request a demo
            </a>
          </div>

          <div class="bg-blue-50 rounded-2xl p-6 border border-blue-100">
            <p class="font-bold text-blue-900 mb-2">Support hours</p>
            <p class="text-sm text-blue-800">Monday – Friday: 08:00 – 17:00 CAT<br>Saturday: 09:00 – 13:00 CAT<br>Sunday: Closed</p>
          </div>
        </div>
      </div>
    </section>

  </LandingLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import LandingLayout from '@/admin/components/layout/LandingLayout.vue'

const submitted = ref(false)
const form = reactive({ first_name: '', last_name: '', email: '', organisation: '', subject: '', message: '' })

function submit() {
  // In production this would POST to a contact endpoint
  submitted.value = true
}

const contactDetails = [
  { icon: '📧', label: 'Email',    value: 'support@lendr.app' },
  { icon: '📍', label: 'Address',  value: 'Lusaka, Zambia' },
  { icon: '🏢', label: 'Company',  value: 'RISKCAPE Enterprises Limited' },
  { icon: '🌍', label: 'Region',   value: 'Sub-Saharan Africa' },
]
</script>
