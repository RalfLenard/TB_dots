<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps({
    certificates: Object,     // Laravel paginator { data, links, from, to, total, ... }
    filters: Object,          // { search, per_page }
    perPageOptions: Array,    // [10, 25, 50]
    physicians: Array,        // [{ name, position, license_number }]
    today: String,            // YYYY-MM-DD
    flash: Object,            // { success, print_id }
})

const BASE = route('aics.index')
const MAX_ITEMS = 10

const breadcrumbs = [{ title: 'AICS', href: BASE }]

/* ---------- Search + per-page (debounced search, keeps both in the URL) ---------- */
const search = ref(props.filters?.search ?? '')
const perPage = ref(props.filters?.per_page ?? 10)

function visit() {
    const query = {}
    if (search.value) query.search = search.value
    if (perPage.value !== 10) query.per_page = perPage.value

    router.get(BASE, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

let timer = null
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(visit, 300)
})
watch(perPage, visit)

/* ---------- Pagination ---------- */
const hasPages = computed(() => props.certificates.links.length > 3)

/* ---------- Modal + form (create & edit share one modal) ---------- */
const showModal = ref(false)
const editingId = ref(null)
const isEditing = computed(() => editingId.value !== null)

const blank = () => ({
    certificate_date: props.today,
    full_name: '',
    age: '',
    barangay: '',
    diagnosis: '',
    recommendations: [''],
    physician_name: props.physicians[0]?.name ?? '',
})

const form = useForm(blank())

const selectedPhysician = computed(
    () => props.physicians.find((p) => p.name === form.physician_name) ?? null
)
const position = computed(() => selectedPhysician.value?.position ?? '')
const licenseNumber = computed(() => selectedPhysician.value?.license_number ?? '')

/** Turns whatever the server sent (array, object, JSON string) into a clean list of strings. */
function toList(value) {
    let v = value

    if (typeof v === 'string') {
        try {
            v = JSON.parse(v)
        } catch {
            v = [v]
        }
    }
    if (v && typeof v === 'object' && !Array.isArray(v)) {
        v = Object.values(v)
    }
    if (!Array.isArray(v)) {
        return []
    }

    return v.filter((x) => typeof x === 'string' && x.trim() !== '').map((x) => x.trim())
}

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

function openCreate() {
    editingId.value = null
    form.defaults(blank())
    form.reset()
    form.clearErrors()
    showModal.value = true
}

function openEdit(c) {
    const recs = toList(c.recommendations)

    const values = {
        certificate_date: c.date_raw,
        full_name: c.full_name,
        age: c.age,
        barangay: c.barangay,
        diagnosis: c.diagnosis,
        recommendations: recs.length ? recs : [''],
        physician_name: c.physician_name,
    }

    editingId.value = c.id
    form.clearErrors()
    form.defaults(values)
    form.reset()

    // Set the values directly as well, so the fields are guaranteed to be filled in.
    form.certificate_date = values.certificate_date
    form.full_name = values.full_name
    form.age = values.age
    form.barangay = values.barangay
    form.diagnosis = values.diagnosis
    form.recommendations = [...values.recommendations]
    form.physician_name = values.physician_name

    showModal.value = true
}

function closeModal() {
    showModal.value = false
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            editingId.value = null
            form.defaults(blank())
            form.reset()
        },
    }

    if (isEditing.value) {
        form.put(`${BASE}/${editingId.value}`, options)
    } else {
        form.post(BASE, options)
    }
}

// Close the modal with the Esc key
function onKeydown(e) {
    if (e.key === 'Escape' && showModal.value) closeModal()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    clearTimeout(timer)
})

/* ---------- Delete ---------- */
function destroy(certificate) {
    if (confirm(`Delete the AICS certificate for ${certificate.full_name}?`)) {
        router.delete(`${BASE}/${certificate.id}`, { preserveScroll: true })
    }
}


const barangays = [
    "Alfonso, Concepcion, Tarlac",
    "Balutu, Concepcion, Tarlac",
    "Cafe, Concepcion, Tarlac",
    "Calius Gueco, Concepcion, Tarlac",
    "Caluluan, Concepcion, Tarlac",
    "Castillo, Concepcion, Tarlac",
    "Corazon de Jesus, Concepcion, Tarlac",
    "Culatingan, Concepcion, Tarlac",
    "Dungan, Concepcion, Tarlac",
    "Dutung-A-Matas, Concepcion, Tarlac",
    "Green Village, Concepcion, Tarlac",
    "Lilibangan, Concepcion, Tarlac",
    "Mabilog, Concepcion, Tarlac",
    "Magao, Concepcion, Tarlac",
    "Malupa, Concepcion, Tarlac",
    "Minane, Concepcion, Tarlac",
    "Panalicsican, Concepcion, Tarlac",
    "Pando, Concepcion, Tarlac",
    "Parang, Concepcion, Tarlac",
    "Parulung, Concepcion, Tarlac",
    "Pitabunan, Concepcion, Tarlac",
    "San Agustin, Concepcion, Tarlac",
    "San Antonio, Concepcion, Tarlac",
    "San Bartolome, Concepcion, Tarlac",
    "San Francisco, Concepcion, Tarlac",
    "San Isidro, Concepcion, Tarlac",
    "San Jose, Concepcion, Tarlac",
    "San Juan, Concepcion, Tarlac",
    "San Martin, Concepcion, Tarlac",
    "San Nicolas Poblacion, Concepcion, Tarlac",
    "San Nicolas Balas, Concepcion, Tarlac",
    "San Vicente, Concepcion, Tarlac",
    "Sta. Cruz, Concepcion, Tarlac",
    "Sta. Maria, Concepcion, Tarlac",
    "Sta. Monica, Concepcion, Tarlac",
    "Sta. Rita, Concepcion, Tarlac",
    "Sta. Rosa, Concepcion, Tarlac",
    "Santiago, Concepcion, Tarlac",
    "Sto. Cristo, Concepcion, Tarlac",
    "Sto. Niño, Concepcion, Tarlac",
    "Sto. Rosario, Concepcion, Tarlac",
    "Talimunduc Marimla, Concepcion, Tarlac",
    "Talimunduc San Miguel, Concepcion, Tarlac",
    "Telabanca, Concepcion, Tarlac",
    "Tinang, Concepcion, Tarlac",
];


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
                        @click="openCreate"
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

                <!-- Search + per page -->
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <div class="w-full max-w-sm">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search name, barangay or diagnosis"
                            :class="inputClass.replace('mt-1 ', '')"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        Rows per page
                        <select
                            v-model.number="perPage"
                            class="rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/30"
                        >
                            <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </label>
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
                                    <button type="button" class="ml-3 font-medium text-blue-700 hover:underline" @click="openEdit(c)">
                                        Edit
                                    </button>
                                    <button type="button" class="ml-3 font-medium text-red-700 hover:underline" @click="destroy(c)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!certificates.data.length">
                                <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                    {{ search ? 'No certificates match your search.' : 'No AICS certificates yet. Select “New AICS certificate” to add one.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="certificates.total > 0"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-sm text-slate-600">
                        Showing
                        <span class="font-medium">{{ certificates.from }}</span>
                        to
                        <span class="font-medium">{{ certificates.to }}</span>
                        of
                        <span class="font-medium">{{ certificates.total }}</span>
                        results
                    </p>

                    <nav v-if="hasPages" class="flex flex-wrap gap-1" aria-label="Pagination">
                        <template v-for="(link, i) in certificates.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="rounded border px-3 py-1 text-sm"
                                :class="link.active ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-300 bg-white text-slate-900 hover:bg-slate-100'"
                                v-html="link.label"
                            />
                            <span v-else class="rounded border border-slate-200 px-3 py-1 text-sm text-slate-400" v-html="link.label" />
                        </template>
                    </nav>
                </div>
            </div>

            <!-- ============ Create / Edit AICS certificate modal ============ -->
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
                @mousedown.self="closeModal"
            >
                <form
                    class="my-8 w-full max-w-2xl rounded-lg bg-white text-slate-900 shadow-xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="modal-title"
                    @submit.prevent="submit"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 id="modal-title" class="text-lg font-semibold">
                            {{ isEditing ? 'Edit AICS certificate' : 'New AICS certificate' }}
                        </h2>
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

                        <select
                            v-model="form.barangay"
                            required
                            :class="inputClass">
                            <option value="" disabled>Select Barangay</option>
                            <option
                                v-for="barangay in barangays"
                                :key="barangay"
                                :value="barangay">
                                {{ barangay }}
                            </option>
                        </select>

                        <p v-if="form.errors.barangay" class="mt-1 text-xs text-red-700">
                            {{ form.errors.barangay }}
                        </p>
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
                            {{ form.processing ? 'Saving…' : isEditing ? 'Save changes' : 'Save and generate' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>