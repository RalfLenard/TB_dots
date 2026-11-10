<?php

namespace App\Http\Controllers;

use App\Models\EmploymentMedicalCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use setasign\Fpdi\Tcpdf\Fpdi;

class EmploymentMedicalCertificateController extends Controller
{
    /** All coordinates are "pixels" of a page rendered 893 wide (measured from the CS Form No. 211 PDF). */
    private const REF_W = 893;

    /** Allowed "rows per page" choices. */
    private const PER_PAGE_OPTIONS = [10, 25, 50];

    /* ---------------------------------------------------------------
     |  COORDINATES (px @ 893 wide). cx = horizontal CENTER of the cell,
     |  y = vertical CENTER of the text. w = widest the text may be
     |  (it shrinks to fit). Use ?grid=1 on the PDF url to check them.
     * ------------------------------------------------------------- */

    // Name (top left cell)
    private const NAME_CX = 309;  private const NAME_Y = 569.9;  private const NAME_W = 480;  private const NAME_SIZE = 11;

    // Agency / address (top right cell, two lines)
    private const AGENCY_CX = 708;  private const AGENCY_W = 275;
    private const AGENCY_Y1 = 580.1;  private const AGENCY_Y2 = 600.3;

    // Address (left cell: 1 line, or 2 lines when long) and municipality (right of it)
    private const ADDR_CX = 159;  private const ADDR_W = 200;
    private const ADDR_Y  = 629.3;  private const ADDR_Y1 = 620.0;  private const ADDR_Y2 = 639.1;
    private const MUNI_CX = 412;  private const MUNI_Y = 629.3;  private const MUNI_W = 285;

    // Age / sex / civil status / proposed position
    private const ROW2_Y = 687.2;
    private const AGE_CX = 141;    private const AGE_W = 160;
    private const SEX_CX = 289;    private const SEX_W = 115;
    private const CIVIL_CX = 457;  private const CIVIL_W = 200;
    private const POS_CX = 708;    private const POS_W = 275;

    // Check marks: centre of each box
    private const EXAM_BOX_X = 216;
    private const EXAM_BOX_Y = [
        'blood'         => 380.2,
        'urinalysis'    => 397.4,
        'chest_xray'    => 414.7,
        'drug_test'     => 432.0,
        'psychological' => 449.8,
        'neuro'         => 470.4,
    ];
    private const FIT_BOX = ['fit' => [558.5, 788.4], 'unfit' => [608.5, 787.3]];

    // Physician block (left column)
    private const DOC_CX = 309;  private const DOC_W = 480;
    private const DOC_NAME_Y = 866.0;
    private const DOC_AFFIL_Y = 914.2;
    private const DOC_LIC_Y = 976.2;
    private const DOC_DESIG_Y = 1037.8;

    // Other information (right column)
    private const HEIGHT_CX = 611;  private const WEIGHT_CX = 708;  private const BLOOD_CX = 805;
    private const VITALS_Y = 983.4;  private const VITALS_W = 80;
    private const DATE_CX = 708;  private const DATE_Y = 1037.8;  private const DATE_W = 275;

    private float $k = 1;

    /* ------------------------------ pages ------------------------------ */

    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', self::PER_PAGE_OPTIONS[0]);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE_OPTIONS[0];
        }

        $certificates = EmploymentMedicalCertificate::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim($request->string('search')) . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('last_name', 'like', $s)
                      ->orWhere('first_name', 'like', $s)
                      ->orWhere('address', 'like', $s)
                      ->orWhere('proposed_position', 'like', $s);
                });
            })
            ->latest('date_examined')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmploymentMedicalCertificate $c) => [
                'id'                => $c->id,
                'full_name'         => $c->full_name,
                'last_name'         => $c->last_name,
                'first_name'        => $c->first_name,
                'name_extension'    => $c->name_extension,
                'middle_name'       => $c->middle_name,
                'address'           => $c->address,
                'municipality'      => $c->municipality,
                'agency_name'       => $c->agency_name,
                'agency_address'    => $c->agency_address,
                'age'               => $c->age,
                'sex'               => $c->sex,
                'civil_status'      => $c->civil_status,
                'proposed_position' => $c->proposed_position,
                'exams'             => $c->exams ?? [],
                'fit_status'        => $c->fit_status,
                'fit_label'         => EmploymentMedicalCertificate::FIT_STATUSES[$c->fit_status] ?? $c->fit_status,
                'height'            => $c->height,
                'weight'            => $c->weight,
                'blood_type'        => $c->blood_type,
                'physician_name'    => $c->physician_name,
                'date_examined'     => $c->date_examined->format('M d, Y'),
                'date_examined_raw' => $c->date_examined->format('Y-m-d'),   // for the edit form
            ]);

        return Inertia::render('EmploymentMedicalCertificates/Index', [
            'certificates'   => $certificates,
            'filters'        => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'physicians'     => collect(EmploymentMedicalCertificate::PHYSICIANS)
                                ->map(fn ($info, $name) => [
                                    'name'           => $name,
                                    'position'       => $info['position'],
                                    'license_number' => $info['license'],
                                    'affiliation'    => $info['affiliation'],
                                ])
                                ->values(),
            'sexes'          => EmploymentMedicalCertificate::SEXES,
            'civilStatuses'  => EmploymentMedicalCertificate::CIVIL_STATUSES,
            'bloodTypes'     => EmploymentMedicalCertificate::BLOOD_TYPES,
            'fitStatuses'    => EmploymentMedicalCertificate::FIT_STATUSES,
            'examOptions'    => EmploymentMedicalCertificate::EXAMS,
            'defaults'       => EmploymentMedicalCertificate::DEFAULTS,
            'today'          => now()->toDateString(),
            'flash'          => [
                'success'  => session('success'),
                'print_id' => session('print_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $certificate = EmploymentMedicalCertificate::create($this->validated($request));

        return redirect()
            ->route('employment-medical-certificates.index')
            ->with('success', 'Medical certificate (for employment) created.')
            ->with('print_id', $certificate->id);
    }

    public function update(Request $request, EmploymentMedicalCertificate $employmentMedicalCertificate): RedirectResponse
    {
        $employmentMedicalCertificate->update($this->validated($request));

        return redirect()
            ->back()
            ->with('success', 'Medical certificate (for employment) updated.')
            ->with('print_id', $employmentMedicalCertificate->id);
    }

    public function destroy(EmploymentMedicalCertificate $employmentMedicalCertificate): RedirectResponse
    {
        $employmentMedicalCertificate->delete();

        return redirect()
            ->back()
            ->with('success', 'Medical certificate (for employment) deleted.');
    }

    /** Shared by store() and update(). */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'date_examined'     => ['required', 'date'],
            'last_name'         => ['required', 'string', 'max:100'],
            'first_name'        => ['required', 'string', 'max:100'],
            'name_extension'    => ['nullable', 'string', 'max:10'],
            'middle_name'       => ['nullable', 'string', 'max:100'],
            'address'           => ['required', 'string', 'max:150'],
            'municipality'      => ['required', 'string', 'max:100'],
            'agency_name'       => ['required', 'string', 'max:150'],
            'agency_address'    => ['required', 'string', 'max:150'],
            'age'               => ['required', 'integer', 'min:0', 'max:120'],
            'sex'               => ['required', Rule::in(array_keys(EmploymentMedicalCertificate::SEXES))],
            'civil_status'      => ['required', Rule::in(array_keys(EmploymentMedicalCertificate::CIVIL_STATUSES))],
            'proposed_position' => ['required', 'string', 'max:100'],
            'exams'             => ['nullable', 'array'],
            'exams.*'           => [Rule::in(array_keys(EmploymentMedicalCertificate::EXAMS))],
            'fit_status'        => ['required', Rule::in(array_keys(EmploymentMedicalCertificate::FIT_STATUSES))],
            'height'            => ['required', 'numeric', 'min:0.3', 'max:2.8'],
            'weight'            => ['required', 'numeric', 'min:1', 'max:500'],
            'blood_type'        => ['nullable', Rule::in(EmploymentMedicalCertificate::BLOOD_TYPES)],
            'physician_name'    => ['required', Rule::in(array_keys(EmploymentMedicalCertificate::PHYSICIANS))],
        ]);

        // Tidy the text: trim and collapse repeated spaces.
        foreach (['last_name', 'first_name', 'name_extension', 'middle_name', 'address', 'municipality', 'agency_name', 'agency_address', 'proposed_position'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = trim(preg_replace('/\s+/', ' ', $data[$f]));
            }
        }

        // Exams: keep the form's order, no duplicates.
        $data['exams'] = array_values(array_intersect(
            array_keys(EmploymentMedicalCertificate::EXAMS),
            $data['exams'] ?? []
        ));

        // Position, license number and affiliation always come from the server-side list.
        $physician              = EmploymentMedicalCertificate::PHYSICIANS[$data['physician_name']];
        $data['position']       = $physician['position'];
        $data['license_number'] = $physician['license'];
        $data['affiliation']    = $physician['affiliation'];

        return $data;
    }

    /* ------------------------------- PDF ------------------------------- */

    /** Open in the browser (preview / print). Add ?grid=1 to overlay the coordinate grid. */
    public function show(Request $request, EmploymentMedicalCertificate $employmentMedicalCertificate)
    {
        return $this->response($request, $employmentMedicalCertificate, 'inline');
    }

    public function download(Request $request, EmploymentMedicalCertificate $employmentMedicalCertificate)
    {
        return $this->response($request, $employmentMedicalCertificate, 'attachment');
    }

    private function response(Request $request, EmploymentMedicalCertificate $certificate, string $disposition)
    {
        $filename = 'cs-form-211-' . str($certificate->full_name)->slug() . '-' .
            $certificate->date_examined->format('Y-m-d') . '.pdf';

        return response($this->render($certificate, $request->boolean('grid')), 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function render(EmploymentMedicalCertificate $c, bool $grid = false): string
    {
        $candidates = [];
        foreach (['cs211.pdf'] as $file) {
            $candidates[] = storage_path('app/pdf-templates/' . $file);
            $candidates[] = storage_path('app/pdf-template/' . $file);
            $candidates[] = storage_path('app/private/pdf-templates/' . $file);
            $candidates[] = storage_path('app/private/pdf-template/' . $file);
            $candidates[] = resource_path('pdf-templates/' . $file);
        }

        $template = collect($candidates)->first(fn ($p) => is_file($p));

        abort_unless($template, 404, 'PDF template (cs211.pdf) not found. Looked in: ' . implode(' | ', $candidates));

        $pdf = new Fpdi();
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);

        $pdf->setSourceFile($template);
        $tpl  = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);

        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        $this->k = $size['width'] / self::REF_W;
        $pdf->SetTextColor(0, 0, 0);

        if ($grid) {
            $this->grid($pdf, $size);
        }

        $this->page($pdf, $c);

        return $pdf->Output('cs-form-211-' . $c->id . '.pdf', 'S');
    }

    /** Fill the page. */
    private function page(Fpdi $pdf, EmploymentMedicalCertificate $c): void
    {
        $up = fn (?string $v) => mb_strtoupper(trim((string) $v));

        // ---- Instructions: attached exam results ----
        foreach ($c->exams ?? [] as $key) {
            if (isset(self::EXAM_BOX_Y[$key])) {
                $this->check($pdf, self::EXAM_BOX_X, self::EXAM_BOX_Y[$key]);
            }
        }

        // ---- For the proposed appointee ----
        $this->fitted($pdf, self::NAME_CX, self::NAME_Y, $up($c->full_name), self::NAME_SIZE, self::NAME_W);

        $this->fitted($pdf, self::AGENCY_CX, self::AGENCY_Y1, $up($c->agency_name), 10, self::AGENCY_W, '');
        $this->fitted($pdf, self::AGENCY_CX, self::AGENCY_Y2, $up($c->agency_address), 10, self::AGENCY_W, '');

        $this->wrapped($pdf, self::ADDR_CX, $up($c->address), self::ADDR_W);
        $this->fitted($pdf, self::MUNI_CX, self::MUNI_Y, $up($c->municipality), 10, self::MUNI_W);

        $years = $c->age === 1 ? '1 Year Old' : $c->age . ' Years Old';
        $this->fitted($pdf, self::AGE_CX, self::ROW2_Y, $years, 10, self::AGE_W);
        $this->fitted($pdf, self::SEX_CX, self::ROW2_Y, $up($c->sex), 10, self::SEX_W);
        $this->fitted($pdf, self::CIVIL_CX, self::ROW2_Y, $up($c->civil_status), 10, self::CIVIL_W);
        $this->fitted($pdf, self::POS_CX, self::ROW2_Y, $up($c->proposed_position), 10, self::POS_W);

        // ---- For the licensed government physician ----
        if (isset(self::FIT_BOX[$c->fit_status])) {
            [$bx, $by] = self::FIT_BOX[$c->fit_status];
            $this->check($pdf, $bx, $by);
        }

        $this->fitted($pdf, self::DOC_CX, self::DOC_NAME_Y, $up($c->physician_name) . ', M.D.', 10, self::DOC_W);
        $this->fitted($pdf, self::DOC_CX, self::DOC_AFFIL_Y, $up($c->affiliation), 10, self::DOC_W);
        $this->fitted($pdf, self::DOC_CX, self::DOC_LIC_Y, (string) $c->license_number, 10, self::DOC_W);
        $this->fitted($pdf, self::DOC_CX, self::DOC_DESIG_Y, (string) $c->position, 10, self::DOC_W);

        // ---- Other information about the proposed appointee ----
        $this->fitted($pdf, self::HEIGHT_CX, self::VITALS_Y, number_format((float) $c->height, 2, '.', ''), 10, self::VITALS_W);
        $this->fitted($pdf, self::WEIGHT_CX, self::VITALS_Y, $this->trimNumber($c->weight) . 'Kg', 10, self::VITALS_W);
        $this->fitted($pdf, self::BLOOD_CX, self::VITALS_Y, (string) $c->blood_type, 10, self::VITALS_W);

        $this->fitted($pdf, self::DATE_CX, self::DATE_Y, $c->date_examined->format('n/j/Y'), 10, self::DATE_W);
    }

    /* ----------------------------- helpers ----------------------------- */

    /** Place one cell of text, vertically centred on $ycMm. */
    private function put(Fpdi $pdf, float $xMm, float $ycMm, float $wMm, string $text, string $align = 'L', int $stretch = 0): void
    {
        $h = 6;
        $pdf->SetXY($xMm, $ycMm - $h / 2);
        $pdf->Cell($wMm, $h, $text, 0, 0, $align, false, '', $stretch, false, 'T', 'M');
    }

    /** Text centred on x = $cx (px), shrunk (down to 6pt) so it fits $maxW (px). $style: 'B' bold, '' regular. */
    private function fitted(Fpdi $pdf, float $cx, float $yc, ?string $text, float $size, float $maxW, string $style = 'B'): void
    {
        $text = trim((string) $text);
        if ($text === '') return;

        $limit = $maxW * $this->k;
        $pdf->SetFont('helvetica', $style, $size);
        while ($size > 6 && $pdf->GetStringWidth($text) > $limit) {
            $size -= 0.5;
            $pdf->SetFont('helvetica', $style, $size);
        }

        $this->put($pdf, ($cx - $maxW / 2) * $this->k, $yc * $this->k, $limit, $text, 'C', 1);
    }

    /** Address cell: one line when it fits, otherwise two centred lines (the font shrinks if it still does not fit). */
    private function wrapped(Fpdi $pdf, float $cx, ?string $text, float $maxW, float $size = 10, string $style = 'B'): void
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') return;

        $limit = $maxW * $this->k;
        $lines = [];
        for ($s = $size; $s >= 6; $s -= 0.5) {
            $pdf->SetFont('helvetica', $style, $s);
            $lines = $this->breakWords($pdf, $text, $limit);
            if (count($lines) <= 2) break;
        }
        if (count($lines) > 2) {
            $lines = [$lines[0], implode(' ', array_slice($lines, 1))];
        }

        $ys = count($lines) === 1 ? [self::ADDR_Y] : [self::ADDR_Y1, self::ADDR_Y2];
        foreach ($lines as $i => $line) {
            $this->put($pdf, ($cx - $maxW / 2) * $this->k, $ys[$i] * $this->k, $limit, $line, 'C', 1);
        }
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

    /** 48.50 -> "48.5", 48.00 -> "48". */
    private function trimNumber($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** Check mark centred on (cx, cy) px (the form's boxes are about 13px). */
    private function check(Fpdi $pdf, float $cx, float $cy, float $fontSize = 9): void
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