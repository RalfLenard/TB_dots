<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    records: { type: Array, default: () => [] },
    record: { type: Object, default: null },
})

const CASE_RATES = ['89221', '89222']
const CHARGES = ['2925.00', '4875.00']
const PROCEDURES = ['INTENSIVE PHASE', 'MAINTENANCE PHASE']

const blankRows = () => [0, 1, 2].map(() => ({ diagnosis: '', icd10: '', procedure: '', rvs: '', date: '' }))

const blankData = () => ({
    member_pin: '',
    member_last_name: '',
    member_first_name: '',
    member_extension: '',
    member_middle_name: '',
    member_birthdate: '',
    dependent_pin: '',
    patient_last_name: '',
    patient_first_name: '',
    patient_extension: '',
    patient_middle_name: '',
    relationship_to_member: '',
    date_admitted: '',
    date_discharged: '',
    patient_birthdate: '',

    // Part III - consent
    has_representative: 'no',
    representative_name: '',
    representative_relationship: '',
    representative_relationship_other: '',
    representative_reason: '',
    representative_reason_other: '',

    // Part IV - health care professional (date signed = discharge month/year, day blank)
    professional_accreditation: '1501-1638387-1',
    professional_name: 'GLADYS LOURDES B. BENGCO, M.D',

    // Part V - provider
    hci_rep_name: 'CRISTEL C. SINIO',
    hci_rep_designation: 'PHILHEALTH COORDINATOR',

    facility_pan: 'T03009674',
    facility_name: 'CONCEPCION RURAL HEALTH UNIT I CONCEPCION TARLAC',
    facility_street: 'L. CORTEZ STREET SAN NICOLAS POBLACION',
    facility_city: 'CONCEPCION',
    facility_province: 'TARLAC',
    time_admitted: '',
    time_discharged: '',
    admission_diagnosis: '',
    dx_a: blankRows(),
    dx_b: blankRows(),

    // Case rate (CSF Part V, CF-2 item 7a RVS and item 9): 89221 or 89222
    first_case_rate: '89221',
    second_case_rate: '',

    // CF-2 item 7a procedure text and item 8d box: 'intensive' or 'maintenance'
    phase: 'intensive',

    // CF-2 page 3: institution fees 2925.00 or 4875.00 (professional fees = 0, grand total = same as fees)
    hci_charges: '2925.00',
})

// Saved empty strings should not wipe the fixed defaults.
const withDefaults = (saved) => {
    const base = blankData()
    const merged = { ...base, ...(saved ?? {}) }

    // CF-2 item 7: always three rows per group; old single-diagnosis records become row 1 of group a
    for (const key of ['dx_a', 'dx_b']) {
        const rows = Array.isArray(saved?.[key]) ? saved[key] : []
        merged[key] = [0, 1, 2].map((i) => {
            const row = { ...blankRows()[0], ...(rows[i] ?? {}) }
            // procedure and RVS are dropdowns now; drop old free-text values
            if (!PROCEDURES.includes(row.procedure)) row.procedure = ''
            if (!CASE_RATES.includes(String(row.rvs))) row.rvs = ''
            return row
        })
    }
    if (saved && !Array.isArray(saved.dx_a) && saved.discharge_diagnosis) {
        merged.dx_a[0] = { ...blankRows()[0], diagnosis: saved.discharge_diagnosis }
    }

    for (const key of ['professional_accreditation', 'professional_name', 'hci_rep_name', 'hci_rep_designation', 'facility_pan', 'facility_name', 'facility_street', 'facility_city', 'facility_province']) {
        if (!merged[key]) merged[key] = base[key]
    }

    // Only the allowed choices (older records may hold other values)
    if (!CASE_RATES.includes(String(merged.first_case_rate))) merged.first_case_rate = base.first_case_rate
    if (!['intensive', 'maintenance'].includes(merged.phase)) merged.phase = base.phase
    if (!CHARGES.includes(String(merged.hci_charges))) merged.hci_charges = base.hci_charges

    return merged
}

const form = useForm({
    patient_name: props.record?.patient_name ?? '',
    form_data: withDefaults(props.record?.form_data),
})

// ---- Modal -------------------------------------------------------------
// Open automatically when a record is loaded (edit / after save); closed when none.
const showModal = ref(!!props.record)

watch(() => props.record, (record) => {
    form.patient_name = record?.patient_name ?? ''
    form.form_data = withDefaults(record?.form_data)
    form.clearErrors()
    showModal.value = !!record
})

watch(showModal, (open) => {
    document.body.style.overflow = open ? 'hidden' : ''
})

function openNew() {
    if (props.record) {
        // leave the edit URL first; the modal is reopened blank when the page has loaded
        router.get('/pdf-records', {}, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = true },
        })
        return
    }
    form.patient_name = ''
    form.form_data = withDefaults(null)
    form.clearErrors()
    showModal.value = true
}

function openRecord(id) {
    router.get(`/pdf-records/${id}/edit`, {}, { preserveScroll: true })
}

function closeModal() {
    showModal.value = false
    if (props.record) {
        router.get('/pdf-records', {}, { preserveScroll: true })
    }
}

const onKey = (e) => { if (e.key === 'Escape' && showModal.value) closeModal() }
onMounted(() => {
    window.addEventListener('keydown', onKey)
    if (showModal.value) document.body.style.overflow = 'hidden'
})
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey)
    document.body.style.overflow = ''
})

// ---- Records table -------------------------------------------------------
const search = ref('')
const filteredRecords = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.records
    return props.records.filter((r) =>
        `${r.record_no ?? ''} ${r.patient_name ?? ''}`.toLowerCase().includes(q)
    )
})

const formatDate = (value) => {
    if (!value) return '—'
    const d = new Date(value)
    return Number.isNaN(d.getTime())
        ? '—'
        : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' })
}

const isMember = computed(() => form.form_data.relationship_to_member === 'Member')
const byRep = computed(() => form.form_data.has_representative === 'yes')

// Changes on every page load and every save, so the iframe never shows a stale PDF
// (updated_at does not change when only the PDF layout code changed).
const stamp = ref(Date.now())

const previewUrl = computed(() =>
    props.record?.id
        ? `/pdf-records/${props.record.id}/preview?v=${encodeURIComponent(props.record.updated_at ?? '')}-${stamp.value}`
        : null
)

function save() {
    if (props.record?.id) {
        form.put(`/pdf-records/${props.record.id}`, { onSuccess: () => { stamp.value = Date.now() } })
    } else {
        form.post('/pdf-records')
    }
}
</script>

<template>
    <Head title="PhilHealth CSF + CF-2 Generator" />

    <main class="mx-auto max-w-7xl space-y-6 p-5">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">PhilHealth CSF + CF-2 Generator</h1>
                <p class="text-sm text-gray-500">Standalone PDF form system</p>
            </div>
            <button type="button" @click="openNew" class="rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800">
                + New record
            </button>
        </header>

        <!-- RECORDS TABLE -->
        <section class="rounded-xl border">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                <h2 class="font-semibold">Saved records <span class="text-sm font-normal text-gray-500">({{ filteredRecords.length }})</span></h2>
                <input v-model="search" type="search" placeholder="Search record no. or patient…" class="w-full rounded border p-2 text-sm sm:w-72" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Record no.</th>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in filteredRecords" :key="item.id"
                            class="border-t hover:bg-gray-50" :class="{ 'bg-blue-50': record?.id === item.id }">
                            <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ item.record_no || `Record #${item.id}` }}</td>
                            <td class="px-4 py-3">{{ item.patient_name || 'Unnamed patient' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ formatDate(item.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openRecord(item.id)" class="rounded border px-3 py-1 text-blue-700 hover:bg-blue-50">
                                        Edit
                                    </button>
                                    <a :href="`/pdf-records/${item.id}/preview`" target="_blank" class="rounded bg-gray-800 px-3 py-1 text-white hover:bg-gray-900">
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredRecords.length">
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                {{ records.length ? 'No records match your search.' : 'No records yet. Click "New record" to create one.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-3 sm:p-6"
             @mousedown.self="closeModal">
            <div class="flex max-h-[95vh] w-full max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h2 class="text-lg font-bold">
                        {{ record ? `Edit ${record.record_no || `record #${record.id}`}` : 'New record' }}
                    </h2>
                    <button type="button" @click="closeModal" class="rounded px-3 py-1 text-xl leading-none text-gray-500 hover:bg-gray-100" aria-label="Close">&times;</button>
                </div>

                <div class="overflow-y-auto p-5">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
            <form @submit.prevent="save" class="space-y-6 rounded-xl border p-5">
                <!-- CSF PART I : member -->
                <section class="space-y-3">
                    <h2 class="text-lg font-semibold">CSF Part I — Member information</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Member PIN
                            <input v-model="form.form_data.member_pin" placeholder="12 digits" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Member birthdate
                            <input v-model="form.form_data.member_birthdate" type="date" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Member last name
                            <input v-model="form.form_data.member_last_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Member first name
                            <input v-model="form.form_data.member_first_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Name extension
                            <input v-model="form.form_data.member_extension" placeholder="JR / SR / III" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Member middle name
                            <input v-model="form.form_data.member_middle_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                    </div>
                </section>

                <!-- CSF PART I : patient -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CSF Part I — Patient information</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm sm:col-span-2">Patient is the member's…
                            <select v-model="form.form_data.relationship_to_member" class="mt-1 w-full rounded border p-2">
                                <option value="">Select</option>
                                <option value="Member">Member (patient is the member)</option>
                                <option value="Child">Child</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Parent">Parent</option>
                            </select>
                        </label>

                        <p v-if="isMember" class="rounded bg-blue-50 p-3 text-sm text-blue-800 sm:col-span-2">
                            The patient is the member, so the patient name, birthdate and dependent PIN are
                            taken from the member details above. No need to enter them.
                        </p>

                        <template v-else>
                            <label class="text-sm">Dependent PIN
                                <input v-model="form.form_data.dependent_pin" placeholder="12 digits" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Patient birthdate
                                <input v-model="form.form_data.patient_birthdate" type="date" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Patient last name
                                <input v-model="form.form_data.patient_last_name" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Patient first name
                                <input v-model="form.form_data.patient_first_name" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Patient name extension
                                <input v-model="form.form_data.patient_extension" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Patient middle name
                                <input v-model="form.form_data.patient_middle_name" class="mt-1 w-full rounded border p-2" />
                            </label>
                        </template>
                    </div>
                </section>

                <!-- CSF confinement dates -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">Confinement period</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Date admitted
                            <input v-model="form.form_data.date_admitted" type="date" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Date discharged
                            <input v-model="form.form_data.date_discharged" type="date" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Time admitted (CF-2)
                            <input v-model="form.form_data.time_admitted" type="time" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Time discharged (CF-2)
                            <input v-model="form.form_data.time_discharged" type="time" class="mt-1 w-full rounded border p-2" />
                        </label>
                    </div>
                    <p class="text-xs text-gray-500">
                        Every "Date signed" (CSF and CF-2 page 3) is filled automatically from the DATE DISCHARGED
                        month and year (day left blank).
                    </p>
                </section>

                <section class="space-y-1 border-t pt-5">
                    <h2 class="text-lg font-semibold">CSF Part II — Employer's certification</h2>
                    <p class="text-sm text-gray-500">Left blank. Nothing to enter.</p>
                </section>

                <!-- CSF PART III -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CSF Part III — Consent to access patient records</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <p class="rounded bg-blue-50 p-3 text-sm text-blue-800 sm:col-span-2">
                            With no representative, the patient signs (and when the patient is the member, the
                            member signs). The middle name is printed as an initial. This also applies to
                            Part I item 9 and to CF-2 page 3.
                        </p>
                        <label class="text-sm sm:col-span-2">Does a representative sign?
                            <select v-model="form.form_data.has_representative" class="mt-1 w-full rounded border p-2">
                                <option value="no">No — patient / member signs</option>
                                <option value="yes">Yes — representative signs</option>
                            </select>
                        </label>

                        <template v-if="byRep">
                            <label class="text-sm sm:col-span-2">Representative full name
                                <input v-model="form.form_data.representative_name" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Relationship to the patient
                                <select v-model="form.form_data.representative_relationship" class="mt-1 w-full rounded border p-2">
                                    <option value="">Select</option>
                                    <option>Spouse</option><option>Child</option><option>Parent</option>
                                    <option>Sibling</option><option>Others</option>
                                </select>
                            </label>
                            <label v-if="form.form_data.representative_relationship === 'Others'" class="text-sm">Others, specify
                                <input v-model="form.form_data.representative_relationship_other" class="mt-1 w-full rounded border p-2" />
                            </label>
                            <label class="text-sm">Reason for signing on behalf of the patient
                                <select v-model="form.form_data.representative_reason" class="mt-1 w-full rounded border p-2">
                                    <option value="">Select</option>
                                    <option value="incapacitated">Patient is incapacitated</option>
                                    <option value="other">Other reasons</option>
                                </select>
                            </label>
                            <label v-if="form.form_data.representative_reason === 'other'" class="text-sm">Other reasons
                                <input v-model="form.form_data.representative_reason_other" class="mt-1 w-full rounded border p-2" />
                            </label>
                        </template>
                    </div>
                </section>

                <!-- CSF PART IV -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CSF Part IV — Health care professional</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Accreditation No.
                            <input v-model="form.form_data.professional_accreditation" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Signature over printed name
                            <input v-model="form.form_data.professional_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                    </div>
                </section>

                <!-- CSF PART V -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CSF Part V — Provider information</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">ICD 10 / RVS code — First case rate
                            <select v-model="form.form_data.first_case_rate" class="mt-1 w-full rounded border p-2">
                                <option v-for="code in CASE_RATES" :key="code" :value="code">{{ code }}</option>
                            </select>
                        </label>
                        <label class="text-sm">Second case rate
                            <input v-model="form.form_data.second_case_rate" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Authorized HCI representative
                            <input v-model="form.form_data.hci_rep_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Official capacity / designation
                            <input v-model="form.form_data.hci_rep_designation" class="mt-1 w-full rounded border p-2" />
                        </label>
                    </div>
                </section>

                <!-- CF-2 -->
                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CF-2 — Facility and confinement</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Facility PAN
                            <input v-model="form.form_data.facility_pan" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Facility name
                            <input v-model="form.form_data.facility_name" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Street address
                            <input v-model="form.form_data.facility_street" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">City / municipality
                            <input v-model="form.form_data.facility_city" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Province
                            <input v-model="form.form_data.facility_province" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <label class="text-sm">Phase (item 7a procedure and item 8 box)
                            <select v-model="form.form_data.phase" class="mt-1 w-full rounded border p-2">
                                <option value="intensive">Intensive phase</option>
                                <option value="maintenance">Maintenance phase</option>
                            </select>
                        </label>
                        <label class="text-sm sm:col-span-2">Admission diagnosis
                            <textarea v-model="form.form_data.admission_diagnosis" rows="2" class="mt-1 w-full rounded border p-2" />
                        </label>
                        <div v-for="g in [{ key: 'dx_a', title: '7a — Discharge diagnoses (ICD is always A15.0 and the procedure date is the date admitted; first line falls back to the selected phase and case rate)', auto: true }, { key: 'dx_b', title: '7b — Discharge diagnoses (no automatic values)', auto: false }]"
                             :key="g.key" class="space-y-2 sm:col-span-2">
                            <h3 class="text-sm font-semibold">{{ g.title }}</h3>
                            <div v-for="(row, i) in form.form_data[g.key]" :key="i" class="grid gap-2 sm:grid-cols-5">
                                <input v-model="row.diagnosis" :placeholder="`Diagnosis ${i + 1}`" class="w-full rounded border p-2 text-sm sm:col-span-2" />

                                <!-- ICD-10: always A15.0 in group a -->
                                <input v-if="g.auto" value="A15.0" readonly title="Always A15.0" class="w-full rounded border bg-gray-100 p-2 text-sm" />
                                <input v-else v-model="row.icd10" placeholder="ICD-10" class="w-full rounded border p-2 text-sm" />

                                <!-- Related procedure dropdown -->
                                <select v-model="row.procedure" class="w-full rounded border p-2 text-sm">
                                    <option value="">{{ g.auto && i === 0 ? 'Auto (selected phase)' : 'Related procedure' }}</option>
                                    <option v-for="p in PROCEDURES" :key="p" :value="p">{{ p }}</option>
                                </select>

                                <!-- RVS code dropdown -->
                                <select v-model="row.rvs" class="w-full rounded border p-2 text-sm">
                                    <option value="">{{ g.auto && i === 0 ? `Auto (${form.form_data.first_case_rate})` : 'RVS code' }}</option>
                                    <option v-for="code in CASE_RATES" :key="code" :value="code">{{ code }}</option>
                                </select>

                                <!-- Date of procedure: group a = date admitted automatically -->
                                <input v-if="g.auto" :value="form.form_data.date_admitted" type="date" readonly title="Always the date admitted" class="w-full rounded border bg-gray-100 p-2 text-sm sm:col-span-2" />
                                <input v-else v-model="row.date" type="date" title="Date of procedure" class="w-full rounded border p-2 text-sm sm:col-span-2" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-3 border-t pt-5">
                    <h2 class="text-lg font-semibold">CF-2 — Charges (page 3, Part III-A)</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Total health care institution fees
                            <select v-model="form.form_data.hci_charges" class="mt-1 w-full rounded border p-2">
                                <option value="2925.00">P 2,925.00</option>
                                <option value="4875.00">P 4,875.00</option>
                            </select>
                        </label>
                        <p class="rounded bg-blue-50 p-3 text-sm text-blue-800">
                            Professional fees are always 0.00. Grand total equals the institution fees.
                        </p>
                    </div>
                </section>

                <div v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
                    Please check the fields and try again.
                </div>
                <div class="flex flex-wrap gap-2">
                    <button :disabled="form.processing" class="rounded bg-blue-700 px-5 py-2 font-semibold text-white disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : (record ? 'Update record' : 'Save record') }}
                    </button>
                    <button type="button" @click="closeModal" class="rounded border px-5 py-2">Close</button>
                </div>
            </form>

            <aside class="space-y-4 lg:sticky lg:top-0 lg:self-start">
                <section v-if="previewUrl" class="space-y-3 rounded-xl border p-4">
                    <h2 class="font-semibold">PDF preview</h2>
                    <iframe :src="previewUrl" title="CSF and CF-2 PDF preview" class="w-full border" style="height: 65vh"></iframe>
                    <a :href="previewUrl" target="_blank" class="inline-block rounded bg-gray-800 px-4 py-2 text-white">
                        Open PDF to print
                    </a>
                </section>
                <section v-else class="rounded-xl border p-4 text-sm text-gray-600">
                    Save the record first. The PDF preview will appear here.
                </section>
            </aside>
        </div>
                </div>
            </div>
        </div>
    </main>
</template>