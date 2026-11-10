<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const props = defineProps({
    certificates: Object,     // Laravel paginator { data, links, from, to, total, ... }
    filters: Object,          // { search, per_page }
    perPageOptions: Array,    // [10, 25, 50]
    physicians: Array,        // [{ name, position, license_number, affiliation }]
    sexes: Object,            // { Male: 'Male', Female: 'Female' }
    civilStatuses: Object,    // { Single: 'Single', ... }
    bloodTypes: Array,        // ['A+', ...]
    fitStatuses: Object,      // { fit: 'Fit', unfit: 'Unfit' }
    examOptions: Object,      // { blood: 'Blood Test', ... }
    defaults: Object,         // { municipality, agency_name, agency_address, proposed_position, exams }
    today: String,            // YYYY-MM-DD
    flash: Object,            // { success, print_id }
})

// Built by Ziggy so it also works in a subfolder / with a route prefix
const BASE = route('employment-medical-certificates.index')

const breadcrumbs = [
    {
        title: 'Medical Certificate (Employment)',
        href: BASE,
    },
]

/* ---------- Search + rows per page ---------- */
const search = ref(props.filters?.search ?? '')
const perPage = ref(props.filters?.per_page ?? props.perPageOptions?.[0] ?? 10)

function reload() {
    const query = {}
    if (search.value) query.search = search.value
    if (perPage.value !== props.perPageOptions?.[0]) query.per_page = perPage.value

    router.get(BASE, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

let timer = null
watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(reload, 300)
})
watch(perPage, reload)

/* ---------- Modal + form (create & edit) ---------- */
const showModal = ref(false)
const editingId = ref(null)
const isEditing = computed(() => editingId.value !== null)

const form = useForm({
    date_examined: props.today,
    last_name: '',
    first_name: '',
    name_extension: '',
    middle_name: '',
    address: '',
    municipality: props.defaults.municipality,
    agency_name: props.defaults.agency_name,
    agency_address: props.defaults.agency_address,
    age: '',
    sex: Object.keys(props.sexes)[0] ?? '',
    civil_status: 'Single',
    proposed_position: props.defaults.proposed_position,
    exams: [...props.defaults.exams],
    fit_status: 'fit',
    height: '',
    weight: '',
    blood_type: '',
    physician_name: props.physicians[0]?.name ?? '',
})

// Position, license number and affiliation follow the selected physician automatically
const selectedPhysician = computed(
    () => props.physicians.find((p) => p.name === form.physician_name) ?? null
)
const position = computed(() => selectedPhysician.value?.position ?? '')
const licenseNumber = computed(() => selectedPhysician.value?.license_number ?? '')
const affiliation = computed(() => selectedPhysician.value?.affiliation ?? '')

function openCreate() {
    editingId.value = null
    form.reset()
    form.clearErrors()
    showModal.value = true
}

function openEdit(c) {
    editingId.value = c.id
    form.clearErrors()
    form.date_examined = c.date_examined_raw
    form.last_name = c.last_name
    form.first_name = c.first_name
    form.name_extension = c.name_extension ?? ''
    form.middle_name = c.middle_name ?? ''
    form.address = c.address
    form.municipality = c.municipality
    form.agency_name = c.agency_name
    form.agency_address = c.agency_address
    form.age = c.age
    form.sex = c.sex
    form.civil_status = c.civil_status
    form.proposed_position = c.proposed_position
    form.exams = [...(c.exams ?? [])]
    form.fit_status = c.fit_status
    form.height = c.height
    form.weight = c.weight
    form.blood_type = c.blood_type ?? ''
    form.physician_name = c.physician_name
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
            form.reset()
        },
    }

    if (isEditing.value) {
        form.put(`${BASE}/${editingId.value}`, options)
    } else {
        form.post(BASE, options)
    }
}

function onKeydown(e) {
    if (e.key === 'Escape' && showModal.value) closeModal()
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown)
    clearTimeout(timer)
})

/* ---------- Delete ---------- */
function destroy(c) {
    if (confirm(`Delete the medical certificate for ${c.full_name}?`)) {
        router.delete(`${BASE}/${c.id}`, { preserveScroll: true })
    }
}

const barangays = [
    "Alfonso",
    "Balutu",
    "Cafe",
    "Calius Gueco",
    "Caluluan",
    "Castillo",
    "Corazon de Jesus",
    "Culatingan",
    "Dungan",
    "Dutung-A-Matas",
    "Green Village",
    "Lilibangan",
    "Mabilog",
    "Magao",
    "Malupa",
    "Minane",
    "Panalicsican",
    "Pando",
    "Parang",
    "Parulung",
    "Pitabunan",
    "San Agustin",
    "San Antonio",
    "San Bartolome",
    "San Francisco",
    "San Isidro",
    "San Jose",
    "San Juan",
    "San Martin",
    "San Nicolas Poblacion",
    "San Nicolas Balas",
    "San Vicente",
    "Sta. Cruz",
    "Sta. Maria",
    "Sta. Monica",
    "Sta. Rita",
    "Sta. Rosa",
    "Santiago",
    "Sto. Cristo",
    "Sto. Niño",
    "Sto. Rosario",
    "Talimunduc Marimla",
    "Talimunduc San Miguel",
    "Telabanca",
    "Tinang",
];

const inputClass =
    'mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-700/30'
</script>

<template>
    <Head title="Medical Certificate (Employment)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div>
            <div class="w-full space-y-6 px-6">
                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">Medical certificate (for employment)</h1>
                        <p class="text-sm text-slate-500">
                            CS Form No. 211 · Office of the Municipal Health Officer, Concepcion, Tarlac
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md bg-teal-800 px-4 py-2 text-sm font-medium text-white hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:ring-offset-2"
                        @click="openCreate"
                    >
                        New certificate
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

                <!-- Search + rows per page -->
                <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
                    <div class="w-full max-w-sm">
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search name, address or position"
                            :class="inputClass"
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
                                <th class="px-4 py-3 font-medium">Date examined</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Age / Sex</th>
                                <th class="px-4 py-3 font-medium">Address</th>
                                <th class="px-4 py-3 font-medium">Position</th>
                                <th class="px-4 py-3 font-medium">Result</th>
                                <th class="px-4 py-3 font-medium">Physician</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="c in certificates.data" :key="c.id" class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3">{{ c.date_examined }}</td>
                                <td class="px-4 py-3 font-medium">{{ c.full_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ c.age }} / {{ c.sex }}</td>
                                <td class="px-4 py-3">{{ c.address }}</td>
                                <td class="px-4 py-3">{{ c.proposed_position }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="c.fit_status === 'fit' ? 'bg-teal-50 text-teal-800' : 'bg-red-50 text-red-700'"
                                    >
                                        {{ c.fit_label }}
                                    </span>
                                </td>
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
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                    No certificates found. Select “New certificate” to add one.
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
                        Showing <span class="font-medium">{{ certificates.from }}</span>
                        to <span class="font-medium">{{ certificates.to }}</span>
                        of <span class="font-medium">{{ certificates.total }}</span> results
                    </p>

                    <nav v-if="certificates.links.length > 3" class="flex flex-wrap gap-1">
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
            </div>

            <!-- ============ Create / edit certificate modal ============ -->
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4"
                @mousedown.self="closeModal"
            >
                <form
                    class="my-8 w-full max-w-3xl rounded-lg bg-white text-slate-900 shadow-xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="modal-title"
                    @submit.prevent="submit"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h2 id="modal-title" class="text-lg font-semibold">
                            {{ isEditing ? 'Edit medical certificate (for employment)' : 'New medical certificate (for employment)' }}
                        </h2>
                        <button type="button" class="text-2xl leading-none text-slate-400 hover:text-slate-700" aria-label="Close" @click="closeModal">
                            &times;
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-6">
                        <!-- ===== Proposed appointee ===== -->
                        <h3 class="text-sm font-semibold text-teal-900 sm:col-span-6">For the proposed appointee</h3>

                        <!-- Name -->
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Last name</span>
                            <input v-model="form.last_name" type="text" required placeholder="e.g. Miranda" :class="inputClass" />
                            <p v-if="form.errors.last_name" class="mt-1 text-xs text-red-700">{{ form.errors.last_name }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">First name</span>
                            <input v-model="form.first_name" type="text" required placeholder="e.g. Jenella" :class="inputClass" />
                            <p v-if="form.errors.first_name" class="mt-1 text-xs text-red-700">{{ form.errors.first_name }}</p>
                        </label>
                        <label class="sm:col-span-1">
                            <span class="text-sm font-medium">Ext.</span>
                            <input v-model="form.name_extension" type="text" placeholder="Jr." :class="inputClass" />
                            <p v-if="form.errors.name_extension" class="mt-1 text-xs text-red-700">{{ form.errors.name_extension }}</p>
                        </label>
                        <label class="sm:col-span-1">
                            <span class="text-sm font-medium">Middle name</span>
                            <input v-model="form.middle_name" type="text" placeholder="Calara" :class="inputClass" />
                            <p v-if="form.errors.middle_name" class="mt-1 text-xs text-red-700">{{ form.errors.middle_name }}</p>
                        </label>

                        <!-- Address -->
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Barangay</span>
                            <select
                                v-model="form.address"
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
                            <p v-if="form.errors.address" class="mt-1 text-xs text-red-700">
                                {{ form.errors.address }}
                            </p>
                        </label>
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Municipality, province</span>
                            <input v-model="form.municipality" type="text" required :class="inputClass" />
                            <p v-if="form.errors.municipality" class="mt-1 text-xs text-red-700">{{ form.errors.municipality }}</p>
                        </label>

                        <!-- Agency -->
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Agency</span>
                            <input v-model="form.agency_name" type="text" required :class="inputClass" />
                            <p v-if="form.errors.agency_name" class="mt-1 text-xs text-red-700">{{ form.errors.agency_name }}</p>
                        </label>
                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Agency address</span>
                            <input v-model="form.agency_address" type="text" required :class="inputClass" />
                            <p v-if="form.errors.agency_address" class="mt-1 text-xs text-red-700">{{ form.errors.agency_address }}</p>
                        </label>

                        <!-- Age, sex, civil status, position -->
                        <label class="sm:col-span-1">
                            <span class="text-sm font-medium">Age</span>
                            <input v-model="form.age" type="number" min="0" max="120" required :class="inputClass" />
                            <p v-if="form.errors.age" class="mt-1 text-xs text-red-700">{{ form.errors.age }}</p>
                        </label>
                        <label class="sm:col-span-1">
                            <span class="text-sm font-medium">Sex</span>
                            <select v-model="form.sex" required :class="inputClass">
                                <option v-for="(label, value) in sexes" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <p v-if="form.errors.sex" class="mt-1 text-xs text-red-700">{{ form.errors.sex }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Civil status</span>
                            <select v-model="form.civil_status" required :class="inputClass">
                                <option v-for="(label, value) in civilStatuses" :key="value" :value="value">{{ label }}</option>
                            </select>
                            <p v-if="form.errors.civil_status" class="mt-1 text-xs text-red-700">{{ form.errors.civil_status }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Proposed position</span>
                            <input v-model="form.proposed_position" type="text" required :class="inputClass" />
                            <p v-if="form.errors.proposed_position" class="mt-1 text-xs text-red-700">{{ form.errors.proposed_position }}</p>
                        </label>

                        <!-- Attached exam results -->
                        <fieldset class="sm:col-span-6">
                            <legend class="text-sm font-medium">Exam results attached</legend>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label v-for="(label, key) in examOptions" :key="key" class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="form.exams"
                                        type="checkbox"
                                        :value="key"
                                        class="h-4 w-4 rounded border-slate-300 text-teal-800 focus:ring-teal-700"
                                    />
                                    {{ label }}
                                </label>
                            </div>
                            <p v-if="form.errors.exams" class="mt-1 text-xs text-red-700">{{ form.errors.exams }}</p>
                        </fieldset>

                        <!-- ===== Physician ===== -->
                        <h3 class="mt-2 border-t border-slate-200 pt-4 text-sm font-semibold text-teal-900 sm:col-span-6">
                            For the licensed government physician
                        </h3>

                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Date examined</span>
                            <input v-model="form.date_examined" type="date" required :class="inputClass" />
                            <p v-if="form.errors.date_examined" class="mt-1 text-xs text-red-700">{{ form.errors.date_examined }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Physically and medically</span>
                            <select v-model="form.fit_status" required :class="inputClass">
                                <option v-for="(label, value) in fitStatuses" :key="value" :value="value">{{ label.toUpperCase() }} for employment</option>
                            </select>
                            <p v-if="form.errors.fit_status" class="mt-1 text-xs text-red-700">{{ form.errors.fit_status }}</p>
                        </label>
                        <div class="hidden sm:col-span-2 sm:block"></div>

                        <label class="sm:col-span-3">
                            <span class="text-sm font-medium">Physician</span>
                            <select v-model="form.physician_name" required :class="inputClass">
                                <option v-for="p in physicians" :key="p.name" :value="p.name">{{ p.name.toUpperCase() }}, M.D.</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ position }} · {{ affiliation }}</p>
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

                        <!-- Other information -->
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Height (m)</span>
                            <input v-model="form.height" type="number" step="0.01" min="0.3" max="2.8" required placeholder="1.60" :class="inputClass" />
                            <p v-if="form.errors.height" class="mt-1 text-xs text-red-700">{{ form.errors.height }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Weight (kg)</span>
                            <input v-model="form.weight" type="number" step="0.1" min="1" max="500" required placeholder="48" :class="inputClass" />
                            <p v-if="form.errors.weight" class="mt-1 text-xs text-red-700">{{ form.errors.weight }}</p>
                        </label>
                        <label class="sm:col-span-2">
                            <span class="text-sm font-medium">Blood type</span>
                            <select v-model="form.blood_type" :class="inputClass">
                                <option value="">—</option>
                                <option v-for="b in bloodTypes" :key="b" :value="b">{{ b }}</option>
                            </select>
                            <p v-if="form.errors.blood_type" class="mt-1 text-xs text-red-700">{{ form.errors.blood_type }}</p>
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
                            {{ form.processing ? 'Saving…' : (isEditing ? 'Save changes' : 'Save and generate') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>