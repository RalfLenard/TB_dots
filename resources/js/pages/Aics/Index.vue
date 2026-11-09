<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps({
    certificates: Object,   // Laravel paginator { data, links, ... }
    filters: Object,        // { search }
    physicians: Array,      // [{ name, position, license_number }]
    today: String,          // YYYY-MM-DD
    flash: Object,          // { success, print_id }
})

const BASE = route('aics.index')
const MAX_ITEMS = 10

const breadcrumbs = [
    {
        title: 'AICS',
        href: BASE,
    },
]

/* ---------- Search (debounced) ---------- */
const search = ref(props.filters?.search ?? '')
let timer = null
watch(search, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
        router.get(BASE, value ? { search: value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

/* ---------- Modal + form ---------- */
const showModal = ref(false)

const form = useForm({
    certificate_date: props.today,
    full_name: '',
    age: '',
    barangay: '',
    diagnosis: '',
    recommendations: [''],
    physician_name: props.physicians[0]?.name ?? '',
})

const selectedPhysician = computed(
    () => props.physicians.find((p) => p.name === form.physician_name) ?? null
)
const position = computed(() => selectedPhysician.value?.position ?? '')
const licenseNumber = computed(() => selectedPhysician.value?.license_number ?? '')

function addItem() {
    if (form.recommendations.length < MAX_ITEMS) {
        form.recommendations.push('')
    }
}

function removeItem(index) {
    form.recommendations.splice(index, 1)
    if (form.recommendations.length === 0) {
        form.recommendations.push('')
    }
}

// Shows "recommendations.0" style errors too
const itemErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([key]) => key.startsWith('recommendations'))
        .map(([, message]) => message)
)

function openModal() {
    form.clearErrors()
    showModal.value = true
}

function closeModal() {
    showModal.value = false
}

function submit() {
    form.post(BASE, {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            form.reset()
        },
    })
}

/* ---------- Delete ---------- */
function destroy(certificate) {
    if (confirm(`Delete the AICS certificate for ${certificate.full_name}?`)) {
        router.delete(`${BASE}/${certificate.id}`, { preserveScroll: true })
    }
}

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/30'
</script>

<template>
    <Head title="AICS" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div>
            <div class="w-full space-y-6 px-6">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">AICS certificates</h1>
                        <p class="text-sm text-slate-500">
                            Office of the Municipal Health Officer, Concepcion, Tarlac
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md bg-teal-800 px-4 py-2 text-sm font-medium text-white hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2"
                        @click="openModal"
                    >
                        New AICS certificate
                    </button>
                </div>

                <!-- Flash -->
                <div
                    v-if="flash?.success"
                    class="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900"
                >
                    <span>{{ flash.success }}</span>
                    <span v-if="flash.print_id" class="flex gap-2">
                        <a
                            :href="`${BASE}/${flash.print_id}`"
                            target="_blank"
                            class="rounded bg-teal-800 px-3 py-1 font-medium text-white hover:bg-teal-900"
                        >
                            View / print PDF
                        </a>
                        <a
                            :href="`${BASE}/${flash.print_id}/download`"
                            class="rounded border border-teal-800 px-3 py-1 font-medium text-teal-900 hover:bg-teal-100"
                        >
                            Download
                        </a>
                    </span>
                </div>

                <!-- Search -->
                <div class="mt-4 max-w-sm">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search name, barangay or diagnosis"
                        :class="inputClass"
                    />
                </div>

                <!-- Table -->
                <div class="mt-4 overflow-x-auto rounded-md border border-slate-200 bg-white text-slate-900">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-100 text-left text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Age</th>
                                <th class="px-4 py-3 font-medium">Barangay</th>
                                <th class="px-4 py-3 font-medium">Diagnosis</th>
                                <th class="px-4 py-3 font-medium">Physician</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in certificates.data" :key="c.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3">{{ c.certificate_date }}</td>
                                <td class="px-4 py-3 font-medium">{{ c.full_name }}</td>
                                <td class="px-4 py-3">{{ c.age }}</td>
                                <td class="px-4 py-3">{{ c.barangay }}</td>
                                <td class="px-4 py-3">{{ c.diagnosis }}</td>
                                <td class="px-4 py-3">{{ c.physician_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <a :href="`${BASE}/${c.id}`" target="_blank" class="font-medium text-teal-800 hover:underline">Print</a>
                                    <a :href="`${BASE}/${c.id}/download`" class="ml-3 font-medium text-slate-600 hover:underline">Download</a>
                                    <button type="button" class="ml-3 font-medium text-red-700 hover:underline" @click="destroy(c)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!certificates.data.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                    No AICS certificates yet. Select “New AICS certificate” to add one.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav v-if="certificates.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <template v-for="(link, i) in certificates.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded border px-3 py-1 text-sm"
                            :class="link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-300 bg-white text-slate-900 hover:bg-slate-100'"
                            v-html="link.label"
                        />
                        <span v-else class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400" v-html="link.label" />
                    </template>
                </nav>
            </div>

            <!-- ============ New AICS certificate modal ============ -->
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
                @mousedown.self="closeModal"
                @keydown.esc="closeModal"
            >
                <form
                    class="my-8 w-full max-w-2xl rounded-lg bg-white text-slate-900 shadow-xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="modal-title"
                    @submit.prevent="submit"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 id="modal-title" class="text-lg font-semibold">New AICS certificate</h2>
                        <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close" @click="closeModal">
                            &times;
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-6">
                        <!-- Date -->
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Date</span>
                            <input v-model="form.certificate_date" type="date" required :class="inputClass" />
                            <p v-if="form.errors.certificate_date" class="mt-1 text-xs text-red-700">{{ form.errors.certificate_date }}</p>
                        </label>
                        <div class="hidden sm:col-span-4 sm:block"></div>

                        <!-- Name -->
                        <label class="sm:col-span-6">
                            <span class="text-sm font-medium">Full name</span>
                            <input v-model="form.full_name" type="text" required placeholder="e.g. Juan D. Dela Cruz" :class="inputClass" />
                            <p v-if="form.errors.full_name" class="mt-1 text-xs text-red-700">{{ form.errors.full_name }}</p>
                        </label>

                        <!-- Age + barangay -->
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Age</span>
                            <input v-model="form.age" type="number" min="0" max="120" required :class="inputClass" />
                            <p v-if="form.errors.age" class="mt-1 text-xs text-red-700">{{ form.errors.age }}</p>
                        </label>
                        <label class="sm:col-span-4">
                            <span class="text-sm font-medium">Barangay</span>
                            <input v-model="form.barangay" type="text" required placeholder="e.g. San Jose" :class="inputClass" />
                            <p v-if="form.errors.barangay" class="mt-1 text-xs text-red-700">{{ form.errors.barangay }}</p>
                        </label>

                        <!-- Diagnosis -->
                        <label class="sm:col-span-6">
                            <span class="text-sm font-medium">Diagnosis</span>
                            <input
                                v-model="form.diagnosis"
                                type="text"
                                required
                                placeholder="e.g. Hypertensive Cardiovascular Disease; Diabetes Mellitus II"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.diagnosis" class="mt-1 text-xs text-red-700">{{ form.errors.diagnosis }}</p>
                        </label>

                        <!-- Recommended management -->
                        <div class="sm:col-span-6">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Recommended management</span>
                                <span class="text-xs text-slate-500">{{ form.recommendations.length }} / {{ MAX_ITEMS }}</span>
                            </div>

                            <div v-for="(item, i) in form.recommendations" :key="i" class="mt-2 flex items-center gap-2">
                                <span class="text-slate-400">-</span>
                                <input
                                    v-model="form.recommendations[i]"
                                    type="text"
                                    maxlength="100"
                                    :placeholder="i === 0 ? 'e.g. Amlodipine 5mg (1 tab once a day)' : ''"
                                    :class="inputClass.replace('mt-1 ', '')"
                                />
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-2 py-2 text-sm text-red-700 hover:bg-red-50"
                                    aria-label="Remove"
                                    @click="removeItem(i)"
                                >
                                    &times;
                                </button>
                            </div>

                            <button
                                type="button"
                                class="mt-3 rounded-md border border-teal-800 px-3 py-1.5 text-sm font-medium text-teal-900 hover:bg-teal-50 disabled:opacity-50"
                                :disabled="form.recommendations.length >= MAX_ITEMS"
                                @click="addItem"
                            >
                                + Add item
                            </button>

                            <p class="mt-1 text-xs text-slate-500">Leave empty to print no recommendations. Empty lines are ignored.</p>
                            <p v-for="(message, n) in itemErrors" :key="n" class="mt-1 text-xs text-red-700">{{ message }}</p>
                        </div>

                        <!-- Physician (position + license number are automatic) -->
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Physician</span>
                            <select v-model="form.physician_name" required :class="inputClass">
                                <option v-for="p in physicians" :key="p.name" :value="p.name">{{ p.name.toUpperCase() }}, MD</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ position }}</p>
                            <p v-if="form.errors.physician_name" class="mt-1 text-xs text-red-700">{{ form.errors.physician_name }}</p>
                        </label>
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">License no.</span>
                            <input
                                :value="licenseNumber"
                                type="text"
                                readonly
                                tabindex="-1"
                                :class="[inputClass, 'bg-slate-100 text-slate-600']"
                            />
                            <p class="mt-1 text-xs text-slate-500">Filled in automatically</p>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                        <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-100" @click="closeModal">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-teal-800 px-4 py-2 text-sm font-medium text-white hover:bg-teal-900 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving…' : 'Save and generate' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>