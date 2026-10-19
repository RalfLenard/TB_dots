<?php

namespace App\Http\Controllers;

use App\Models\PdfRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfRecordController extends Controller
{
    /** All coordinates are "pixels" of a page rendered 893px wide (measured from csf_cf2.pdf). */
    private const REF_W = 893;

    /** When the patient IS the member: copy member PIN into the dependent PIN boxes? */
    private const FILL_DEPENDENT_PIN_WHEN_MEMBER = true;

    /** Fixed values used when the field is left empty. */
    private const DEFAULTS = [
        'professional_accreditation' => '1501-1638387-1',
        'professional_name'          => 'GLADYS LOURDES B. BENGCO, M.D',

        'hci_rep_name'               => 'CRISTEL C. SINIO',
        'hci_rep_designation'        => 'PHILHEALTH COORDINATOR',
        'facility_pan'               => 'T03009674',
        'facility_name'              => 'CONCEPCION RURAL HEALTH UNIT I CONCEPCION TARLAC',
        'facility_street'            => 'L. CORTEZ STREET SAN NICOLAS POBLACION',
        'facility_city'              => 'CONCEPCION',
        'facility_province'          => 'TARLAC',
    ];

    /** Allowed case rate codes (CSF Part V and CF-2 item 9, also the RVS code in item 7a). */
    private const CASE_RATES = ['89221', '89222'];

    /** CF-2 item 7a procedure text, by selected phase. */
    private const PHASE_LABEL = [
        'intensive'   => 'INTENSIVE PHASE',
        'maintenance' => 'MAINTENANCE PHASE',
    ];

    /** CF-2 item 7a: ICD-10 is ALWAYS this value for every group-a row that has a diagnosis. */
    private const DX_A_ICD10 = 'A15.0';

    /** Font size (pt) of the patient name on CF-2 Part II-1. */
    private const CF2_NAME_SIZE = 7.5;

    /* ---------------------------------------------------------------
     |  EXTRA COORDINATES (measured from csf_cf2.pdf, px @ 893 wide)
     * ------------------------------------------------------------- */

    /** CF-2 front, item 8d (TB DOTS): Intensive / Maintenance box centres (x, y). */
    private const PHASE_BOX = ['intensive' => [199, 1131], 'maintenance' => [338, 1131]];

    /** CF-2 back (page 3). Box left-edges, same layout as the CSF accreditation boxes. */
    private const CF2B_ACCRED        = [[145, 163, 181, 199], [228, 246, 263, 281, 299, 317, 335], [364]];
    private const CF2B_ACCRED_Y      = 104;                      // first accreditation row
    private const CF2B_ACCRED_SIZE   = 10;                        // font pt (boxes are only ~8pt tall)
    private const CF2B_ACCRED_DY     = -2;                       // + moves down, - moves up (px)
    private const CF2B_DATE_SIZE     = 10;                        // font pt for ALL date-signed boxes on page 3
    private const CF2B_DATE_DY       = -3;                       // + moves down, - moves up (px)
    private const CF2B_NAME_DY       = 1;                       // signature names / capacity on page 3 (px)
    private const CF2B_PROF_NAME     = [83, 126, 340];           // x, y, maxW (signature line, row 1)
    private const CF2B_PROF_DATE_Y   = 170;
    private const CF2B_SIGNED        = ['m' => [183, 201], 'd' => [229, 247], 'y' => [276, 294, 311, 329]];

    private const CF2B_BENEFIT_BOX   = [60, 460];                // Part III-A: FIRST box only

    private const CF2B_PATIENT_NAME  = [47, 1058, 345];          // Part III-B signature line
    private const CF2B_PATIENT_DATE_Y = 1107;

    private const CF2B_HCI_NAME      = [47, 1291, 300];          // Part IV signature line
    private const CF2B_HCI_DESIG     = [360, 1291, 240];         // Part IV official capacity
    private const CF2B_HCI_DATE_Y    = 1298;
    private const CF2B_SIGNED_IV     = ['m' => [686, 704], 'd' => [732, 750], 'y' => [778, 796, 814, 832]];

    /** CF-2 back, Part III-B: checkbox centres (x, y). The boxes are 13px. */
    private const CF2B_REL_BOX = [
        'spouse'  => [240.5, 1144.5],
        'child'   => [306.5, 1144.5],
        'parent'  => [367.5, 1144.5],
        'sibling' => [240.5, 1161.5],
        'others'  => [306.5, 1161.5],
    ];
    private const CF2B_REASON_BOX = [
        'incapacitated' => [240.5, 1180.5],   // Patient is Incapacitated
        'other'         => [240.5, 1197.5],   // Other Reasons
    ];
    private const CF2B_WHO_BOX = [
        'patient'        => [527.5, 1182.5],  // right side: Patient
        'representative' => [527.5, 1198.5],  // right side: Representative
    ];

    private float $k = 1;

    /** Live nudges for page 3 (px). Override in the URL: ?acc_dy=-2&date_dy=-3&name_dy=-4 */
    private array $nudge = ['acc' => self::CF2B_ACCRED_DY, 'date' => self::CF2B_DATE_DY, 'name' => self::CF2B_NAME_DY];

    private function listing()
    {
        return PdfRecord::query()->latest()->limit(100)
            ->get(['id', 'record_no', 'patient_name', 'created_at']);
    }

    public function index()
    {
        return Inertia::render('PdfRecords/Index', [
            'records' => $this->listing(),
            'record'  => null,
        ]);
    }

    public function edit(PdfRecord $record)
    {
        return Inertia::render('PdfRecords/Index', [
            'records' => $this->listing(),
            'record'  => $record,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'patient_name' => ['nullable', 'string', 'max:255'],
            'form_data'    => ['required', 'array'],
        ]);

        $fd = $this->resolve($data['form_data']);
        $data['patient_name'] = $data['patient_name']
            ?: trim(($fd['patient_last_name'] ?? '') . ', ' . ($fd['patient_first_name'] ?? ''), ' ,') ?: null;

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $record = PdfRecord::create($data);
        $record->update([
            'record_no' => 'CSF-' . now()->format('Ym') . '-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT),
        ]);

        return redirect()->route('pdf-records.edit', $record);
    }

    public function update(Request $request, PdfRecord $record)
    {
        $record->update($this->validated($request));

        return redirect()->route('pdf-records.edit', $record);
    }

    /** Apply fixed defaults and the "patient is the member" rule. */
    private function resolve(array $d): array
    {
        foreach (self::DEFAULTS as $key => $value) {
            if (blank($d[$key] ?? null)) {
                $d[$key] = $value;
            }
        }

        // Case rate: only 89221 or 89222.
        if (!in_array((string) ($d['first_case_rate'] ?? ''), self::CASE_RATES, true)) {
            $d['first_case_rate'] = self::CASE_RATES[0];
        }

        // Phase: only intensive or maintenance.
        if (!isset(self::PHASE_LABEL[$d['phase'] ?? ''])) {
            $d['phase'] = 'intensive';
        }

        // Signer is automatic: a representative name means the representative signs,
        // otherwise the patient signs (and the patient is the member when relationship = Member).
        $hasRep = ($d['has_representative'] ?? 'no') === 'yes' && filled($d['representative_name'] ?? null);
        $d['part3_signer'] = $hasRep ? 'representative' : 'patient';
        if (!$hasRep) {
            // ignore any stale representative data
            foreach (['representative_name', 'representative_relationship', 'representative_relationship_other',
                      'representative_reason', 'representative_reason_other'] as $f) {
                $d[$f] = '';
            }
        }

        if (strtolower($d['relationship_to_member'] ?? '') === 'member') {
            foreach (['last_name', 'first_name', 'extension', 'middle_name', 'birthdate'] as $f) {
                $d["patient_{$f}"] = $d["member_{$f}"] ?? '';
            }
            $d['dependent_pin'] = self::FILL_DEPENDENT_PIN_WHEN_MEMBER ? ($d['member_pin'] ?? '') : '';
        }

        // CF-2 page 3 (Part III-A): professional fees always 0, grand total = institution fees.
        $d['professional_fees'] = '0';
        $d['grand_total']       = $d['hci_charges'] ?? '';

        $d['dx_a'] = $this->diagnosisRows($d, 'dx_a', true);
        $d['dx_b'] = $this->diagnosisRows($d, 'dx_b', false);

        return $d;
    }

    /**
     * CF-2 item 7: three rows per group (one printed underline each).
     * Procedure (dropdown) and RVS code (dropdown) come from each row; the first row of group a
     * falls back to the selected phase / first case rate when left on "auto".
     * Group a rows that have a diagnosis ALWAYS get ICD A15.0 and date of procedure = date admitted.
     * Old records (single discharge_diagnosis fields) are read as row 1 of group a.
     */
    private function diagnosisRows(array $d, string $key, bool $isGroupA): array
    {
        $rows = is_array($d[$key] ?? null) ? array_values($d[$key]) : [];

        if ($key === 'dx_a' && !$rows && filled($d['discharge_diagnosis'] ?? null)) {
            $rows[0] = ['diagnosis' => $d['discharge_diagnosis']];
        }

        $procedures = array_values(self::PHASE_LABEL);   // INTENSIVE PHASE / MAINTENANCE PHASE

        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $rows[$i] ?? [];

            $procedure = strtoupper(trim((string) ($r['procedure'] ?? '')));
            $rvs       = trim((string) ($r['rvs'] ?? ''));

            $row = [
                'diagnosis' => trim((string) ($r['diagnosis'] ?? '')),
                'icd10'     => trim((string) ($r['icd10'] ?? '')),
                'procedure' => in_array($procedure, $procedures, true) ? $procedure : '',
                'rvs'       => in_array($rvs, self::CASE_RATES, true) ? $rvs : '',
                'date'      => trim((string) ($r['date'] ?? '')),
            ];

            if ($isGroupA && $row['diagnosis'] !== '') {
                $row['icd10'] = self::DX_A_ICD10;                      // always A15.0
                $row['date']  = (string) ($d['date_admitted'] ?? '');  // always the date admitted

                if ($i === 0) {                                        // first underline: auto from selections
                    if ($row['procedure'] === '') $row['procedure'] = self::PHASE_LABEL[$d['phase'] ?? ''] ?? '';
                    if ($row['rvs'] === '')       $row['rvs']       = (string) ($d['first_case_rate'] ?? '');
                }
            }

            $out[] = $row;
        }

        return $out;
    }

    public function preview(Request $request, PdfRecord $record)
    {
        $template = collect([
            storage_path('app/pdf-templates/csf_cf2.pdf'),
            storage_path('app/pdf-template/csf_cf2.pdf'),
            storage_path('app/private/pdf-templates/csf_cf2.pdf'),
        ])->first(fn ($p) => is_file($p));

        abort_unless($template, 404, 'The PDF template was not found in storage/app/pdf-templates.');

        $d = $this->resolve($record->form_data ?? []);

        $this->nudge = [
            'acc'  => (float) $request->query('acc_dy',  self::CF2B_ACCRED_DY),
            'date' => (float) $request->query('date_dy', self::CF2B_DATE_DY),
            'name' => (float) $request->query('name_dy', self::CF2B_NAME_DY),
        ];

        $pdf = new Fpdi();
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);

        $pageCount = $pdf->setSourceFile($template);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl  = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tpl);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);

            $this->k = $size['width'] / self::REF_W;
            $pdf->SetTextColor(0, 0, 0);

            if ($request->boolean('grid')) {
                $this->grid($pdf, $size);
            }

            match ($pageNo) {
                1 => $this->pageCsf($pdf, $d),
                2 => $this->pageCf2Front($pdf, $d),
                3 => $this->pageCf2Back($pdf, $d),
                default => null,
            };
        }

        $content = $pdf->Output('csf-cf2-' . $record->id . '.pdf', 'S');

        return response($content, 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => 'inline; filename="csf-cf2-' . $record->id . '.pdf"',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ---------------------------------------------------------------
     |  PAGE MAPS  (x = left edge, y = vertical CENTER of the text, px @ 893 wide)
     |  Measured from the PDF's own boxes/labels. Use ?grid=1 to verify.
     * ------------------------------------------------------------- */

    /** Box left-edges (all boxes are 18px wide). */
    private const PIN_MEMBER = [[379, 397], [426, 444, 462, 480, 497, 515, 533, 551, 569], [597]];
    private const PIN_DEP    = [[395, 413], [442, 460, 478, 496, 514, 532, 550, 568, 586], [614]];

    private const DOB_MEMBER  = ['m' => [669, 686], 'd' => [715, 733], 'y' => [761, 779, 797, 815]];
    private const ADMITTED    = ['m' => [131, 149], 'd' => [177, 195], 'y' => [223, 241, 259, 277]];
    private const DISCHARGED  = ['m' => [428, 446], 'd' => [474, 492], 'y' => [520, 538, 556, 574]];
    private const DOB_PATIENT = ['m' => [669, 686], 'd' => [715, 733], 'y' => [761, 779, 797, 815]];
    private const SIGNED      = ['m' => [682, 700], 'd' => [728, 746], 'y' => [775, 793, 811, 829]];

    private const SIGNED_I9_MEMBER = ['m' => [180, 198], 'd' => [226, 244], 'y' => [272, 290, 308, 326]];
    private const SIGNED_I9_REP    = ['m' => [575, 593], 'd' => [622, 640], 'y' => [668, 686, 704, 722]];
    private const SIGNED_III       = ['m' => [583, 601], 'd' => [630, 648], 'y' => [676, 694, 712, 730]];

    private const ACCRED = [[123, 141, 159, 177], [206, 224, 242, 260, 277, 295, 313], [342]];

    private function pageCsf(Fpdi $pdf, array $d): void
    {
        // ---------- PART I ----------
        $this->pin($pdf, self::PIN_MEMBER, 247, $d['member_pin'] ?? '');

        // 2. Member name
        $this->txt($pdf, 40,  283, $d['member_last_name'] ?? '', 10, 150);
        $this->txt($pdf, 201, 283, $d['member_first_name'] ?? '', 10, 150);
        $this->txt($pdf, 361, 283, $d['member_extension'] ?? '', 10, 150);
        $this->txt($pdf, 522, 283, $d['member_middle_name'] ?? '', 10, 140);

        // 3. Member birthdate
        $this->date($pdf, self::DOB_MEMBER, 292, $d['member_birthdate'] ?? '');

        // 4. Dependent PIN (blank unless filled by resolve())
        $this->pin($pdf, self::PIN_DEP, 343, $d['dependent_pin'] ?? '');

        // 5. Patient name
        $this->txt($pdf, 40,  379, $d['patient_last_name'] ?? '', 10, 150);
        $this->txt($pdf, 201, 379, $d['patient_first_name'] ?? '', 10, 150);
        $this->txt($pdf, 361, 379, $d['patient_extension'] ?? '', 10, 150);
        $this->txt($pdf, 522, 379, $d['patient_middle_name'] ?? '', 10, 140);

        // 6. Relationship (no box when the patient is the member)
        match (strtolower($d['relationship_to_member'] ?? '')) {
            'child'  => $this->check($pdf, 663, 385),
            'parent' => $this->check($pdf, 724, 385),
            'spouse' => $this->check($pdf, 790, 385),
            default  => null,
        };

        // 7. Confinement period / 8. Patient DOB
        $this->date($pdf, self::ADMITTED, 457, $d['date_admitted'] ?? '');
        $this->date($pdf, self::DISCHARGED, 457, $d['date_discharged'] ?? '', 11, true);
        $this->date($pdf, self::DOB_PATIENT, 457, $d['patient_birthdate'] ?? '');

        // 9. Certification of member: signatory mirrors Part III (member / representative)
        $this->partI9($pdf, $d);

        // ---------- PART II (employer) : intentionally left blank ----------

        // ---------- PART III : consent / representative of the patient ----------
        $this->partIII($pdf, $d);

        // ---------- PART IV : health care professional (date signed = discharge month/year, day blank) ----------
        $this->cells($pdf, self::ACCRED[0], 1115, substr($this->digits($d['professional_accreditation']), 0, 4), 11);
        $this->cells($pdf, self::ACCRED[1], 1115, substr($this->digits($d['professional_accreditation']), 4, 7), 11);
        $this->cells($pdf, self::ACCRED[2], 1115, substr($this->digits($d['professional_accreditation']), 11, 1), 11);
        $this->txt($pdf, 385, 1112, $d['professional_name'], 10, 225);
        $this->signedDate($pdf, 1115, $d['date_discharged'] ?? '');

        // ---------- PART V : provider ----------
        $this->txt($pdf, 415, 1252, $d['first_case_rate'] ?? '', 11, 140);   // 89221 or 89222
        $this->txt($pdf, 680, 1255, $d['second_case_rate'] ?? '', 11, 170);
        $this->txt($pdf, 40,  1293, $d['hci_rep_name'], 10, 340);
        $this->txt($pdf, 400, 1293, $d['hci_rep_designation'], 10, 200);
        $this->signedDate($pdf, 1295, $d['date_discharged'] ?? '');
    }

    /** Part I item 9: same signer choice and representative details as Part III. */
    private function partI9(Fpdi $pdf, array $d): void
    {
        $byRep = ($d['part3_signer'] ?? 'patient') === 'representative';

        if (!$byRep) {
            $this->check($pdf, 45, 657);                         // Member box
            $this->txt($pdf, 90, 530, $this->fullName($d, 'member', true), 10, 280);   // middle initial
            $this->signedDate($pdf, 568, $d['date_discharged'] ?? '', self::SIGNED_I9_MEMBER);
            return;
        }

        $this->check($pdf, 116, 657);                            // Representative box
        $this->txt($pdf, 468, 530, $d['representative_name'] ?? '', 10, 380);
        $this->signedDate($pdf, 568, $d['date_discharged'] ?? '', self::SIGNED_I9_REP);

        switch (strtolower($d['representative_relationship'] ?? '')) {
            case 'spouse':  $this->check($pdf, 588, 599); break;
            case 'child':   $this->check($pdf, 655, 599); break;
            case 'parent':  $this->check($pdf, 712, 599); break;
            case 'sibling': $this->check($pdf, 588, 619); break;
            case 'others':
                $this->check($pdf, 655, 619);
                $this->txt($pdf, 740, 617, $d['representative_relationship_other'] ?? '', 9, 105);
                break;
        }

        if (($d['representative_reason'] ?? '') === 'incapacitated') {
            $this->check($pdf, 588, 641);
        } elseif (($d['representative_reason'] ?? '') === 'other') {
            $this->check($pdf, 588, 662);
            $this->txt($pdf, 672, 666, $d['representative_reason_other'] ?? '', 9, 185);
        }
    }

    private function partIII(Fpdi $pdf, array $d): void
    {
        $byRep = ($d['part3_signer'] ?? 'patient') === 'representative';
        $this->signedDate($pdf, 965, $d['date_discharged'] ?? '', self::SIGNED_III);

        if ($byRep) {
            $this->check($pdf, 111, 1058);                       // Representative box
            $this->txt($pdf, 95, 958, $d['representative_name'] ?? '', 10, 410);

            switch (strtolower($d['representative_relationship'] ?? '')) {
                case 'spouse':  $this->check($pdf, 588, 1001); break;
                case 'child':   $this->check($pdf, 655, 1001); break;
                case 'parent':  $this->check($pdf, 712, 1001); break;
                case 'sibling': $this->check($pdf, 588, 1022); break;
                case 'others':
                    $this->check($pdf, 655, 1022);
                    $this->txt($pdf, 740, 1021, $d['representative_relationship_other'] ?? '', 9, 105);
                    break;
            }

            if (($d['representative_reason'] ?? '') === 'incapacitated') {
                $this->check($pdf, 588, 1045);
            } elseif (($d['representative_reason'] ?? '') === 'other') {
                $this->check($pdf, 588, 1066);
                $this->txt($pdf, 672, 1066, $d['representative_reason_other'] ?? '', 9, 185);
            }
        } else {
            $this->check($pdf, 45, 1058);                        // Patient box
            $this->txt($pdf, 95, 962, $this->fullName($d, 'patient', true), 10, 410);   // middle initial
        }
    }

    private function pageCf2Front(Fpdi $pdf, array $d): void
    {
        // Part I
        $pan = preg_replace('/[^A-Za-z0-9]/', '', (string) ($d['facility_pan'] ?? ''));
        $this->cells($pdf, [482, 500, 518, 536, 554, 571, 589, 607, 625], 290, $pan, 11);
        $this->txt($pdf, 258, 315, $d['facility_name'] ?? '', 10, 590);
        $this->txt($pdf, 122, 339, $d['facility_street'] ?? '', 10, 300);
        $this->txt($pdf, 435, 339, $d['facility_city'] ?? '', 10, 215);
        $this->txt($pdf, 662, 339, $d['facility_province'] ?? '', 10, 200);

        // Part II-1 patient name
        $this->txt($pdf, 185, 406, $d['patient_last_name'] ?? '', self::CF2_NAME_SIZE, 170);
        $this->txt($pdf, 363, 406, $d['patient_first_name'] ?? '', self::CF2_NAME_SIZE, 175);
        $this->txt($pdf, 552, 406, $d['patient_extension'] ?? '', self::CF2_NAME_SIZE, 125);
        $this->txt($pdf, 689, 406, $d['patient_middle_name'] ?? '', self::CF2_NAME_SIZE, 170);

        // Part II-2 referred by another HCI: always NO
        $this->check($pdf, 56, 490);

        // Part II-3 confinement period (admitted = full date; discharged = no day)
        $cf2Date = ['m' => [289, 307], 'd' => [335, 353], 'y' => [381, 399, 417, 435]];
        $this->date($pdf, $cf2Date, 532, $d['date_admitted'] ?? '', 10);
        $this->date($pdf, $cf2Date, 554, $d['date_discharged'] ?? '', 10, true);

        // Time admitted / discharged, AM-PM from the entered time
        $this->time($pdf, 532, 696, 758, $d['time_admitted'] ?? '');
        $this->time($pdf, 554, 696, 758, $d['time_discharged'] ?? '', 557);

        // Part II-4 patient disposition: always IMPROVED
        $this->check($pdf, 56, 604);

        // Part II-5 accommodation: always NON-PRIVATE
        $this->check($pdf, 299, 692);

        // 6. Admission diagnosis (two lines under the heading)
        $this->wrap($pdf, 52, [736, 752], $d['admission_diagnosis'] ?? '', 800, 10);

        // 7. Discharge diagnoses: group a (rows 813/833/852) and group b (872/892/911)
        $this->diagnosisTable($pdf, $d['dx_a'], [813, 833, 852]);
        $this->diagnosisTable($pdf, $d['dx_b'], [872, 892, 911]);

        // 8. Phase box: Intensive or Maintenance (Day 0 to RIG dates removed)
        if (isset(self::PHASE_BOX[$d['phase'] ?? ''])) {
            [$bx, $by] = self::PHASE_BOX[$d['phase']];
            $this->check($pdf, $bx, $by);
        }

        // 9. Benefits (first case rate: 89221 or 89222)
        $this->txt($pdf, 255, 1327, $d['first_case_rate'] ?? '', 11, 240);
        $this->txt($pdf, 640, 1327, $d['second_case_rate'] ?? '', 11, 210);
    }

    /** One diagnosis per printed underline; ICD, procedure, RVS and date on the same row. */
    private function diagnosisTable(Fpdi $pdf, array $rows, array $ys): void
    {
        foreach ($rows as $i => $r) {
            $y = $ys[$i] ?? null;
            if ($y === null) break;

            $this->twoLines($pdf, 64, $y, $r['diagnosis'], 108);
            $this->txt($pdf, 184, $y, $r['icd10'], 8, 80);
            $this->txt($pdf, 295, $y, $r['procedure'], 8, 175);
            $this->txt($pdf, 478, $y, $r['rvs'], 8, 95);
            $this->txt($pdf, 580, $y, $this->mdy($r['date']), 8, 90);
        }
    }

    private function pageCf2Back(Fpdi $pdf, array $d): void
    {
        $signed = $d['date_discharged'] ?? '';   // every date signed = month + year of the DATE DISCHARGED
        $acc      = $this->digits($d['professional_accreditation']);   // 1501-1638387-1

        // Part 10: health care professional (first block): accreditation, name, date signed (day blank)
        $y  = self::CF2B_ACCRED_Y + $this->nudge['acc'];
        $sz = self::CF2B_ACCRED_SIZE;
        $this->cells($pdf, self::CF2B_ACCRED[0], $y, substr($acc, 0, 4), $sz);
        $this->cells($pdf, self::CF2B_ACCRED[1], $y, substr($acc, 4, 7), $sz);
        $this->cells($pdf, self::CF2B_ACCRED[2], $y, substr($acc, 11, 1), $sz);
        [$x, $ny, $w] = self::CF2B_PROF_NAME;
        $this->txt($pdf, $x, $ny + $this->nudge['name'], $d['professional_name'], 10, $w);
        $this->signedDate($pdf, self::CF2B_PROF_DATE_Y + $this->nudge['date'], $signed, self::CF2B_SIGNED, self::CF2B_DATE_SIZE);

        // Part III-A: only the FIRST box is checked
        $this->check($pdf, self::CF2B_BENEFIT_BOX[0], self::CF2B_BENEFIT_BOX[1]);

        // Part III-A "Total Actual Charges" column (right-aligned at 805)
        $this->money($pdf, 805, 514, $d['hci_charges'] ?? '');          // 2,925.00 or 4,875.00
        $this->money($pdf, 805, 536, $d['professional_fees'] ?? '');    // always 0.00
        $this->money($pdf, 805, 557, $d['grand_total'] ?? '');          // same as institution fees

        // Part III-B: signatory copied from CSF Part III (date signed, day blank)
        $byRep  = ($d['part3_signer'] ?? 'patient') === 'representative';
        $signer = $byRep ? ($d['representative_name'] ?? '') : $this->fullName($d, 'patient', true);
        [$x, $ny, $w] = self::CF2B_PATIENT_NAME;
        $this->txt($pdf, $x, $ny + $this->nudge['name'], $signer, 10, $w);
        $this->signedDate($pdf, self::CF2B_PATIENT_DATE_Y + $this->nudge['date'], $signed, self::CF2B_SIGNED, self::CF2B_DATE_SIZE);

        // Part III-B: relationship / reason / Patient-Representative boxes
        $this->cf2bConsent($pdf, $d);

        // Part IV: HCI representative (date signed, day blank)
        [$x, $ny, $w] = self::CF2B_HCI_NAME;
        $this->txt($pdf, $x, $ny + $this->nudge['name'], $d['hci_rep_name'], 10, $w);                  // CRISTEL C. SINIO
        [$x, $ny, $w] = self::CF2B_HCI_DESIG;
        $this->txt($pdf, $x, $ny + $this->nudge['name'], $d['hci_rep_designation'], 10, $w);           // PHILHEALTH COORDINATOR
        $this->signedDate($pdf, self::CF2B_HCI_DATE_Y + $this->nudge['date'], $signed, self::CF2B_SIGNED_IV, self::CF2B_DATE_SIZE);
    }

    /**
     * CF-2 back, Part III-B: same signer as CSF Part III.
     *  - no representative -> tick "Patient"
     *  - representative    -> tick "Representative", the relationship box and the reason box
     *                         (plus the "Others, Specify" / "Other Reasons" text)
     */
    private function cf2bConsent(Fpdi $pdf, array $d): void
    {
        $byRep = ($d['part3_signer'] ?? 'patient') === 'representative';

        [$wx, $wy] = self::CF2B_WHO_BOX[$byRep ? 'representative' : 'patient'];
        $this->check($pdf, $wx, $wy, 9);

        if (!$byRep) return;

        // Relationship of the representative to the member/patient
        $rel = strtolower((string) ($d['representative_relationship'] ?? ''));
        if (isset(self::CF2B_REL_BOX[$rel])) {
            [$x, $y] = self::CF2B_REL_BOX[$rel];
            $this->check($pdf, $x, $y, 9);

            if ($rel === 'others') {
                $this->txt($pdf, 398, 1161, $d['representative_relationship_other'] ?? '', 9, 105);
            }
        }

        // Reason for signing on behalf of the member/patient
        $reason = (string) ($d['representative_reason'] ?? '');
        if (isset(self::CF2B_REASON_BOX[$reason])) {
            [$x, $y] = self::CF2B_REASON_BOX[$reason];
            $this->check($pdf, $x, $y, 9);

            if ($reason === 'other') {
                $this->txt($pdf, 330, 1197, $d['representative_reason_other'] ?? '', 9, 170);
            }
        }
    }

    /* ----------------------------- helpers ----------------------------- */

    /** Place one cell of text, vertically centred on $ycMm. */
    private function put(Fpdi $pdf, float $xMm, float $ycMm, float $wMm, string $text, string $align = 'L', int $stretch = 0): void
    {
        $h = 6;
        $pdf->SetXY($xMm, $ycMm - $h / 2);
        $pdf->Cell($wMm, $h, $text, 0, 0, $align, false, '', $stretch, false, 'T', 'M');
    }

    /** Uppercase text, shrunk to fit $maxW (px) if given. */
    private function txt(Fpdi $pdf, float $x, float $yc, ?string $text, float $size = 10, ?float $maxW = null, bool $fit = false): void
    {
        $text = mb_strtoupper(trim((string) $text));
        if ($text === '') return;

        $pdf->SetFont('helvetica', '', $size);

        // $fit: keep the font size and squeeze the text to exactly the underline width (only if too long)
        if ($fit && $maxW) {
            $this->put($pdf, $x * $this->k, $yc * $this->k, $maxW * $this->k, $text, 'L', 1);
            return;
        }

        if ($maxW) {
            $limit = $maxW * $this->k;
            while ($size > 6 && $pdf->GetStringWidth($text) > $limit) {
                $size -= 0.5;
                $pdf->SetFont('helvetica', '', $size);
            }
        }

        $this->put($pdf, $x * $this->k, $yc * $this->k, ($maxW ?? 400) * $this->k + 5, $text, 'L');
    }

    /**
     * Diagnosis inside ONE underline: one line if it fits, otherwise two small lines
     * (font shrinks from 6pt down to 4.5pt until it fits on two lines; a leftover is squeezed).
     * $y is the vertical centre used for a single line; two lines sit just above the underline.
     */
    private function twoLines(Fpdi $pdf, float $x, float $y, ?string $text, float $maxW): void
    {
        $text = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $text)));
        if ($text === '') return;

        $limit = $maxW * $this->k;
        $lines = [];

        for ($size = 6.0; $size >= 4.5; $size -= 0.25) {
            $pdf->SetFont('helvetica', '', $size);
            $lines = $this->breakWords($pdf, $text, $limit);
            if (count($lines) <= 2) break;
        }

        if (count($lines) > 2) {                       // still too long: squeeze the rest into line 2
            $lines = [$lines[0], implode(' ', array_slice($lines, 1))];
        }

        $pdf->SetFont('helvetica', '', $size);

        if (count($lines) === 1) {
            $this->put($pdf, $x * $this->k, $y * $this->k, $limit, $lines[0], 'L', 1);
            return;
        }

        $this->put($pdf, $x * $this->k, ($y - 6) * $this->k, $limit, $lines[0], 'L', 1);
        $this->put($pdf, $x * $this->k, ($y + 2) * $this->k, $limit, $lines[1], 'L', 1);
    }

    /** Greedy word wrap using the current font. */
    private function breakWords(Fpdi $pdf, string $text, float $limitMm): array
    {
        $lines = [];
        $cur   = '';
        foreach (explode(' ', $text) as $word) {
            $try = $cur === '' ? $word : "$cur $word";
            if ($cur === '' || $pdf->GetStringWidth($try) <= $limitMm) {
                $cur = $try;
            } else {
                $lines[] = $cur;
                $cur = $word;
            }
        }
        $lines[] = $cur;

        return $lines;
    }

    /** Word-wrap across fixed baselines (one y per line). */
    private function wrap(Fpdi $pdf, float $x, array $ys, ?string $text, float $maxW, float $size): void
    {
        $text = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $text)));
        if ($text === '') return;

        $pdf->SetFont('helvetica', '', $size);
        $limit = $maxW * $this->k;
        $lines = $this->breakWords($pdf, $text, $limit);

        foreach (array_slice($lines, 0, count($ys)) as $i => $line) {
            $this->put($pdf, $x * $this->k, $ys[$i] * $this->k, $maxW * $this->k + 5, $line, 'L');
        }
    }

    /**
     * Right-aligned money value with a peso sign. TCPDF's built-in Helvetica has no peso glyph,
     * so DejaVu Sans (bundled with TCPDF) is used. Set $peso to '' to print the number only,
     * or to 'P ' if you prefer a plain letter P.
     */
    private function money(Fpdi $pdf, float $xRight, float $yc, ?string $value, string $peso = '₱ '): void
    {
        $value = trim((string) $value);
        if ($value === '') return;
        $clean = str_replace(',', '', $value);
        if (is_numeric($clean)) $value = number_format((float) $clean, 2);

        $pdf->SetFont('dejavusans', '', 10);
        $w = 200 * $this->k;
        $this->put($pdf, $xRight * $this->k - $w, $yc * $this->k, $w, $peso . $value, 'R');
    }

    /** One character centred in each 18px box ($xs = left edges). */
    private function cells(Fpdi $pdf, array $xs, float $yc, ?string $text, float $size = 11, float $w = 18): void
    {
        if ($text === null || $text === '') return;
        $pdf->SetFont('helvetica', '', $size);
        foreach (mb_str_split(mb_strtoupper($text)) as $i => $ch) {
            if (!isset($xs[$i])) break;
            $this->put($pdf, $xs[$i] * $this->k, $yc * $this->k, $w * $this->k, $ch, 'C');
        }
    }

    private function digits(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    /** $groups = [2-box, 9-box, 1-box]; needs 12 digits. */
    private function pin(Fpdi $pdf, array $groups, float $yc, ?string $pin): void
    {
        $digits = $this->digits($pin);
        if (strlen($digits) < 12) return;
        $this->cells($pdf, $groups[0], $yc, substr($digits, 0, 2));
        $this->cells($pdf, $groups[1], $yc, substr($digits, 2, 9));
        $this->cells($pdf, $groups[2], $yc, substr($digits, 11, 1));
    }

    /** $cells = ['m'=>[..],'d'=>[..],'y'=>[..]]; value is Y-m-d from <input type=date>. */
    private function date(Fpdi $pdf, array $cells, float $yc, ?string $value, float $size = 11, bool $blankDay = false): void
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) return;
        $this->cells($pdf, $cells['m'], $yc, $m[2], $size);
        if (!$blankDay) {
            $this->cells($pdf, $cells['d'], $yc, $m[3], $size);
        }
        $this->cells($pdf, $cells['y'], $yc, $m[1], $size);
    }

    /** "Date signed" = DATE DISCHARGED month + year, DAY ALWAYS BLANK. */
    private function signedDate(Fpdi $pdf, float $yc, ?string $discharged, ?array $cells = null, float $size = 11): void
    {
        $cells ??= self::SIGNED;
        if (!$discharged || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $discharged, $m)) return;
        $this->cells($pdf, $cells['m'], $yc, $m[2], $size);
        $this->cells($pdf, $cells['y'], $yc, $m[1], $size);
    }

    /** $value "HH:MM" (24h from <input type=time>) → hour/min cells + AM/PM check. */
    private function time(Fpdi $pdf, float $yc, float $amX, float $pmX, ?string $value, ?float $ampmY = null): void
    {
        if (!$value || !preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) return;

        $h24  = (int) $m[1];
        $ampm = $h24 >= 12 ? 'PM' : 'AM';
        $h12  = $h24 % 12 ?: 12;

        $this->cells($pdf, [571, 589], $yc, str_pad((string) $h12, 2, '0', STR_PAD_LEFT), 10);
        $this->cells($pdf, [617, 635], $yc, $m[2], 10);
        $this->check($pdf, $ampm === 'AM' ? $amX : $pmX, $ampmY ?? $yc);
    }

    private function mdy(?string $value): string
    {
        return ($value && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m))
            ? "{$m[2]}-{$m[3]}-{$m[1]}" : '';
    }

    /** Full name; with $initial = true the middle name becomes an initial (e.g. "JUAN D. CRUZ"). */
    private function fullName(array $d, string $prefix, bool $initial = false): string
    {
        $mid = trim((string) ($d["{$prefix}_middle_name"] ?? ''));
        if ($initial && $mid !== '') {
            $mid = mb_strtoupper(mb_substr($mid, 0, 1)) . '.';
        }

        return trim(implode(' ', array_filter([
            $d["{$prefix}_first_name"] ?? '',
            $mid,
            $d["{$prefix}_last_name"]  ?? '',
            $d["{$prefix}_extension"]  ?? '',
        ])));
    }

    /** Check mark centred on (cx, cy) px. $fontSize 9 is used for the small 13px boxes. */
    private function check(Fpdi $pdf, float $cx, float $cy, float $fontSize = 11): void
    {
        $pdf->SetFont('zapfdingbats', '', $fontSize);
        $w = 6;
        $this->put($pdf, $cx * $this->k - $w / 2, $cy * $this->k, $w, '4', 'C');
    }

    private function grid(Fpdi $pdf, array $size): void
    {
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetLineWidth(0.05);
        for ($px = 0; $px <= self::REF_W; $px += 50) {
            $mm = $px * $this->k;
            $pdf->Line($mm, 0, $mm, $size['height']);
            $pdf->Text($mm + 0.5, 1, (string) $px);
        }
        for ($py = 0; $py * $this->k <= $size['height']; $py += 50) {
            $mm = $py * $this->k;
            $pdf->Line(0, $mm, $size['width'], $mm);
            $pdf->Text(0.5, $mm + 0.5, (string) $py);
        }
        $pdf->SetTextColor(0, 0, 0);
    }
}