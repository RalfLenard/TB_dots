<?php

namespace App\Http\Controllers;

use App\Models\FitToWorkCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use setasign\Fpdi\Tcpdf\Fpdi;

class FitToWorkCertificateController extends Controller
{
    /** All coordinates are "pixels" of a page rendered 893 wide (same as the medical certificate). */
    private const REF_W = 893;

    /** Print the patient name in capital letters? */
    private const NAME_UPPERCASE = true;

    /** Allowed "rows per page" choices. */
    private const PER_PAGE_OPTIONS = [10, 25, 50];

    private const SALUTATION = 'To whom it may concern,';
    private const CLOSING    = 'This certification is issued upon request and may be used for whatever medical purpose it may serve.';

    /** Wording printed after "physically fit to ..." for each type. */
    private const FIT_PHRASES = [
        'work'  => 'work',
        'study' => 'study',
        'ojt'   => 'undergo on-the-job training',
    ];

    /* ---------------------------------------------------------------
     |  COORDINATES (px @ 893 wide). y = vertical CENTER of the text.
     |  Use ?grid=1 on the PDF url to check them against the template.
     * ------------------------------------------------------------- */

    private const DATE_RIGHT  = 774.0;
    private const DATE_Y      = 324.9;
    private const DATE_SIZE   = 13.5;

    private const SALUTATION_POS = [122.4, 398.7];

    private const BODY_X       = 122.4;
    private const BODY_W       = 651.4;
    private const BODY_INDENT  = 54.2;
    private const BODY_LEADING = 36.9;
    private const BODY_SIZE    = 14.5;
    private const BODY_1_Y     = 472.5;
    private const BODY_GAP     = 2;

    private const SIGN_NAME_RIGHT = 774.0; private const SIGN_NAME_Y = 905.4;
    private const SIGN_META_CX = 609.1;  private const SIGN_POS_Y  = 932.8;
                                         private const SIGN_LIC_Y  = 959.2;

    private float $k = 1;

    /* ------------------------------ pages ------------------------------ */

    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', self::PER_PAGE_OPTIONS[0]);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::PER_PAGE_OPTIONS[0];
        }

        $certificates = FitToWorkCertificate::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim($request->string('search')) . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('full_name', 'like', $s)
                      ->orWhere('barangay', 'like', $s);
                });
            })
            ->latest('certificate_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (FitToWorkCertificate $c) => [
                'id'                    => $c->id,
                'full_name'             => $c->full_name,
                'age'                   => $c->age,
                'barangay'              => $c->barangay,
                'fit_type'              => $c->fit_type,
                'fit_label'             => FitToWorkCertificate::FIT_TYPES[$c->fit_type] ?? $c->fit_type,
                'physician_name'        => $c->physician_name,
                'certificate_date'      => $c->certificate_date->format('M d, Y'),
                'certificate_date_raw'  => $c->certificate_date->format('Y-m-d'), // for the edit form
            ]);

        return Inertia::render('FitToWork/Index', [
            'certificates'   => $certificates,
            'filters'        => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'physicians'     => collect(FitToWorkCertificate::PHYSICIANS)
                                ->map(fn ($info, $name) => [
                                    'name'           => $name,
                                    'position'       => $info['position'],
                                    'license_number' => $info['license'],
                                ])
                                ->values(),
            'fitTypes'       => FitToWorkCertificate::FIT_TYPES,
            'today'          => now()->toDateString(),
            'flash'          => [
                'success'  => session('success'),
                'print_id' => session('print_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $certificate = FitToWorkCertificate::create($data);

        return redirect()
            ->route('fit-to-work.index')
            ->with('success', 'Fit to work certificate created.')
            ->with('print_id', $certificate->id);
    }

    public function update(Request $request, FitToWorkCertificate $fitToWorkCertificate): RedirectResponse
    {
        $fitToWorkCertificate->update($this->validated($request));

        return redirect()
            ->back()
            ->with('success', 'Fit to work certificate updated.')
            ->with('print_id', $fitToWorkCertificate->id);
    }

    public function destroy(FitToWorkCertificate $fitToWorkCertificate): RedirectResponse
    {
        $fitToWorkCertificate->delete();

        return redirect()
            ->back()
            ->with('success', 'Fit to work certificate deleted.');
    }

    /** Shared by store() and update(). */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'certificate_date' => ['required', 'date'],
            'full_name'        => ['required', 'string', 'max:100'],
            'age'              => ['required', 'integer', 'min:0', 'max:120'],
            'barangay'         => ['required', 'string', 'max:100'],
            'fit_type'         => ['required', Rule::in(array_keys(FitToWorkCertificate::FIT_TYPES))],
            'physician_name'   => ['required', Rule::in(array_keys(FitToWorkCertificate::PHYSICIANS))],
        ]);

        $data['full_name'] = trim(preg_replace('/\s+/', ' ', $data['full_name']));

        // Position and license number always come from the server-side list.
        $physician              = FitToWorkCertificate::PHYSICIANS[$data['physician_name']];
        $data['position']       = $physician['position'];
        $data['license_number'] = $physician['license'];

        return $data;
    }

    /* ------------------------------- PDF ------------------------------- */

    public function show(Request $request, FitToWorkCertificate $fitToWorkCertificate)
    {
        return $this->response($request, $fitToWorkCertificate, 'inline');
    }

    public function download(Request $request, FitToWorkCertificate $fitToWorkCertificate)
    {
        return $this->response($request, $fitToWorkCertificate, 'attachment');
    }

    private function response(Request $request, FitToWorkCertificate $certificate, string $disposition)
    {
        $filename = 'fit-to-' . $certificate->fit_type . '-' . str($certificate->full_name)->slug() . '-' .
            $certificate->certificate_date->format('Y-m-d') . '.pdf';

        return response($this->render($certificate, $request->boolean('grid')), 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function render(FitToWorkCertificate $c, bool $grid = false): string
    {
        // Uses fittowork.pdf if you add one, otherwise the medical certificate template.
        $candidates = [];
        foreach (['fittowork.pdf', 'medcert.pdf'] as $file) {
            $candidates[] = storage_path('app/pdf-templates/' . $file);
            $candidates[] = storage_path('app/pdf-template/' . $file);
            $candidates[] = storage_path('app/private/pdf-templates/' . $file);
            $candidates[] = storage_path('app/private/pdf-template/' . $file);
            $candidates[] = resource_path('pdf-templates/' . $file);
        }

        $template = collect($candidates)->first(fn ($p) => is_file($p));

        abort_unless($template, 404, 'PDF template not found. Looked in: ' . implode(' | ', $candidates));

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

        return $pdf->Output('fit-to-work-' . $c->id . '.pdf', 'S');
    }

    /** Fill the page. */
    private function page(Fpdi $pdf, FitToWorkCertificate $c): void
    {
        $name = self::NAME_UPPERCASE ? mb_strtoupper($c->full_name) : $c->full_name;

        // "BRGY. BALUTU, CONCEPCION, TARLAC" (a leading "Brgy."/"Barangay" typed by the user is removed)
        $brgy = mb_strtoupper(trim(preg_replace('/^\s*(?:brgy\.?|barangay)\s+/i', '', $c->barangay)));
        $residence = 'BRGY. ' . $brgy . ', CONCEPCION, TARLAC';

        // work / study / undergo on-the-job training
        $fitPhrase = self::FIT_PHRASES[$c->fit_type] ?? self::FIT_PHRASES['work'];

        // Date (right-aligned)
        $pdf->SetFont('times', 'I', self::DATE_SIZE);
        $w = 400;
        $this->put($pdf, (self::DATE_RIGHT - $w) * $this->k, self::DATE_Y * $this->k, $w * $this->k,
            $c->certificate_date->format('F j, Y'), 'R');

        // Salutation
        $pdf->SetFont('times', 'I', self::BODY_SIZE);
        $this->put($pdf, self::SALUTATION_POS[0] * $this->k, self::SALUTATION_POS[1] * $this->k, 400 * $this->k,
            self::SALUTATION, 'L');

        // Paragraph 1
        $lines = $this->paragraph($pdf, self::BODY_1_Y, [
            ['This is to certify that I have seen and examined ', 'I'],
            [$name . ', ' . $c->age, 'BI'],
            [' years old, a resident of ', 'I'],
            [$residence, 'BI'],
            ['. Patient is essentially normal at the time of examination and is now physically fit to ' . $fitPhrase . '.', 'I'],
        ]);

        // Paragraph 2
        $this->paragraph($pdf, self::BODY_1_Y + ($lines - 1 + self::BODY_GAP) * self::BODY_LEADING, [[self::CLOSING, 'I']]);

        // Signature block
        $pdf->SetFont('times', 'BIU', 13.9);
        $w = 420;
        $this->put($pdf, (self::SIGN_NAME_RIGHT - $w) * $this->k, self::SIGN_NAME_Y * $this->k, $w * $this->k,
            mb_strtoupper($c->physician_name) . ', MD', 'R');

        $pdf->SetFont('times', '', 13);
        $this->centered($pdf, self::SIGN_META_CX, self::SIGN_POS_Y, $c->position);
        $this->centered($pdf, self::SIGN_META_CX, self::SIGN_LIC_Y, 'Lic. No: ' . $c->license_number);
    }

    /* ----------------------------- helpers ----------------------------- */

    private function put(Fpdi $pdf, float $xMm, float $ycMm, float $wMm, string $text, string $align = 'L', int $stretch = 0): void
    {
        $h = 6;
        $pdf->SetXY($xMm, $ycMm - $h / 2);
        $pdf->Cell($wMm, $h, $text, 0, 0, $align, false, '', $stretch, false, 'T', 'M');
    }

    private function centered(Fpdi $pdf, float $cx, float $yc, string $text): void
    {
        $w = 420;
        $this->put($pdf, ($cx - $w / 2) * $this->k, $yc * $this->k, $w * $this->k, $text, 'C');
    }

    /**
     * Justified paragraph with mixed styles and a first-line indent.
     * Returns the number of lines drawn.
     */
    private function paragraph(Fpdi $pdf, float $firstY, array $segments): int
    {
        $size = self::BODY_SIZE;

        $words = [];
        $cur   = [];
        foreach ($segments as [$text, $style]) {
            foreach (preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
                if (trim($part) === '') {
                    if ($cur) { $words[] = $cur; $cur = []; }
                    continue;
                }
                $pdf->SetFont('times', $style, $size);
                $cur[] = ['t' => $part, 's' => $style, 'w' => $pdf->GetStringWidth($part)];
            }
        }
        if ($cur) $words[] = $cur;

        $widthOf = fn (array $word) => array_sum(array_column($word, 'w'));

        $pdf->SetFont('times', 'I', $size);
        $space = $pdf->GetStringWidth(' ');
        $full  = self::BODY_W * $this->k;

        $lines = [];
        $line  = [];
        $sum   = 0.0;
        foreach ($words as $word) {
            $avail = $full - (count($lines) === 0 ? self::BODY_INDENT * $this->k : 0);
            $ww    = $widthOf($word);
            $need  = $sum + $ww + count($line) * $space;
            if ($line && $need > $avail) {
                $lines[] = [$line, $sum, $avail];
                $line = [];
                $sum  = 0.0;
            }
            $line[] = $word;
            $sum   += $ww;
        }
        if ($line) {
            $lines[] = [$line, $sum, $full - (count($lines) === 0 ? self::BODY_INDENT * $this->k : 0)];
        }

        foreach ($lines as $i => [$lineWords, $lineSum, $avail]) {
            $isLast = $i === count($lines) - 1;
            $gap    = (!$isLast && count($lineWords) > 1)
                ? ($avail - $lineSum) / (count($lineWords) - 1)
                : $space;

            $x = (self::BODY_X * $this->k) + ($i === 0 ? self::BODY_INDENT * $this->k : 0);
            $y = ($firstY + $i * self::BODY_LEADING) * $this->k;

            foreach ($lineWords as $word) {
                foreach ($word as $piece) {
                    $pdf->SetFont('times', $piece['s'], $size);
                    $this->put($pdf, $x, $y, $piece['w'] + 1, $piece['t'], 'L');
                    $x += $piece['w'];
                }
                $x += $gap;
            }
        }

        return count($lines);
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