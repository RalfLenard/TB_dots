<?php

namespace App\Http\Controllers;

use App\Models\MedicalCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use setasign\Fpdi\Tcpdf\Fpdi;

class MedicalCertificateController extends Controller
{
    /** All coordinates are "pixels" of a page rendered 893 wide (measured from medcert.pdf). */
    private const REF_W = 893;

    /** Print the patient name in capital letters? */
    private const NAME_UPPERCASE = true;

    /** Fixed wording (the template only keeps the letterhead, the title and "NOT VALID WITHOUT SEAL"). */
    private const SALUTATION = 'To whom it may concern,';
    private const CLOSING    = 'This certification is issued upon request and may be used for whatever medical purpose it may serve.';

    /* ---------------------------------------------------------------
     |  COORDINATES (px @ 893 wide). y = vertical CENTER of the text.
     |  Use ?grid=1 on the PDF url to check them against the template.
     * ------------------------------------------------------------- */

    private const DATE_RIGHT  = 774.0;   // date is right-aligned to this x
    private const DATE_Y      = 324.9;
    private const DATE_SIZE   = 13.5;

    private const SALUTATION_POS = [122.4, 398.7];       // x, y

    private const BODY_X       = 122.4;                   // left margin of the paragraphs
    private const BODY_W       = 651.4;                   // paragraph width (justified)
    private const BODY_INDENT  = 54.2;                    // first-line indent
    private const BODY_LEADING = 36.9;                    // distance between lines
    private const BODY_SIZE    = 14.5;                    // pt (Times is narrower than the template's Cambria)
    private const BODY_1_Y     = 472.5;                   // centre of the first line of paragraph 1
    private const BODY_GAP     = 2;                       // paragraph 2 starts this many lines below paragraph 1's last line

    private const SIGN_NAME_RIGHT = 774.0; private const SIGN_NAME_Y = 905.4;  // doctor name (bold, underlined), right-aligned
    private const SIGN_META_CX = 609.1;  private const SIGN_POS_Y  = 932.8;   // position
                                         private const SIGN_LIC_Y  = 959.2;   // license number

    private float $k = 1;

    /* ------------------------------ pages ------------------------------ */

    public function index(Request $request): Response
    {
        $certificates = MedicalCertificate::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim($request->string('search')) . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('full_name', 'like', $s)
                      ->orWhere('barangay', 'like', $s)
                      ->orWhere('assessment', 'like', $s);
                });
            })
            ->latest('certificate_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MedicalCertificate $c) => [
                'id'               => $c->id,
                'full_name'        => $c->full_name,
                'age'              => $c->age,
                'barangay'         => $c->barangay,
                'assessment'       => $c->assessment,
                'rest_days'        => $c->rest_days,
                'fit_type'         => $c->fit_type,
                'fit_date'         => $c->fit_date->format('M d, Y'),
                'physician_name'   => $c->physician_name,
                'certificate_date' => $c->certificate_date->format('M d, Y'),
            ]);

        return Inertia::render('MedicalCertificates/Index', [
            'certificates' => $certificates,
            'filters'      => ['search' => $request->input('search', '')],
            'physicians'   => collect(MedicalCertificate::PHYSICIANS)
                                ->map(fn ($info, $name) => [
                                    'name'           => $name,
                                    'position'       => $info['position'],
                                    'license_number' => $info['license'],
                                ])
                                ->values(),
            'fitTypes'     => MedicalCertificate::FIT_TYPES,
            'today'        => now()->toDateString(),
            'flash'        => [
                'success'  => session('success'),
                'print_id' => session('print_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'certificate_date' => ['required', 'date'],
            'full_name'        => ['required', 'string', 'max:100'],
            'age'              => ['required', 'integer', 'min:0', 'max:120'],
            'barangay'         => ['required', 'string', 'max:100'],
            'assessment'       => ['required', 'string', 'max:255'],
            'rest_days'        => ['required', 'integer', 'min:0', 'max:365'],
            'fit_type'         => ['required', Rule::in(array_keys(MedicalCertificate::FIT_TYPES))],
            'fit_date'         => ['required', 'date'],
            'physician_name'   => ['required', Rule::in(array_keys(MedicalCertificate::PHYSICIANS))],
        ]);

        // Tidy the name: trim and collapse repeated spaces.
        $data['full_name'] = trim(preg_replace('/\s+/', ' ', $data['full_name']));

        // Position and license number always come from the server-side list, not from the form.
        $physician              = MedicalCertificate::PHYSICIANS[$data['physician_name']];
        $data['position']       = $physician['position'];
        $data['license_number'] = $physician['license'];

        $certificate = MedicalCertificate::create($data);

        return redirect()
            ->route('medical-certificates.index')
            ->with('success', 'Medical certificate created.')
            ->with('print_id', $certificate->id);
    }

    public function destroy(MedicalCertificate $medicalCertificate): RedirectResponse
    {
        $medicalCertificate->delete();

        return redirect()
            ->route('medical-certificates.index')
            ->with('success', 'Medical certificate deleted.');
    }

    /* ------------------------------- PDF ------------------------------- */

    /** Open in the browser (preview / print). Add ?grid=1 to overlay the coordinate grid. */
    public function show(Request $request, MedicalCertificate $medicalCertificate)
    {
        return $this->response($request, $medicalCertificate, 'inline');
    }

    public function download(Request $request, MedicalCertificate $medicalCertificate)
    {
        return $this->response($request, $medicalCertificate, 'attachment');
    }

    private function response(Request $request, MedicalCertificate $certificate, string $disposition)
    {
        $filename = 'medical-certificate-' . str($certificate->full_name)->slug() . '-' .
            $certificate->certificate_date->format('Y-m-d') . '.pdf';

        return response($this->render($certificate, $request->boolean('grid')), 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function render(MedicalCertificate $c, bool $grid = false): string
    {
        $template = collect([
            storage_path('app/pdf-templates/medcert.pdf'),
            storage_path('app/pdf-template/medcert.pdf'),
            storage_path('app/private/pdf-templates/medcert.pdf'),
        ])->first(fn ($p) => is_file($p));

        abort_unless($template, 404, 'The PDF template was not found in storage/app/pdf-templates.');

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

        return $pdf->Output('medical-certificate-' . $c->id . '.pdf', 'S');
    }

    /** Fill the page. */
    private function page(Fpdi $pdf, MedicalCertificate $c): void
    {
        $name = self::NAME_UPPERCASE ? mb_strtoupper($c->full_name) : $c->full_name;

        // Date (right-aligned)
        $pdf->SetFont('times', 'I', self::DATE_SIZE);
        $w = 400;
        $this->put($pdf, (self::DATE_RIGHT - $w) * $this->k, self::DATE_Y * $this->k, $w * $this->k,
            $c->certificate_date->format('F j, Y'), 'R');

        // Salutation
        $pdf->SetFont('times', 'I', self::BODY_SIZE);
        $this->put($pdf, self::SALUTATION_POS[0] * $this->k, self::SALUTATION_POS[1] * $this->k, 400 * $this->k,
            self::SALUTATION, 'L');

        // Paragraph 1: bold = the filled-in parts
        $lines = $this->paragraph($pdf, self::BODY_1_Y, [
            ['This is to certify that I have seen and examined ', 'I'],
            [$name . ', ' . $c->age, 'BI'],
            [' years old from ', 'I'],
            ['Barangay ' . trim($c->barangay) . ' Concepcion, Tarlac', 'BI'],
            ['. On assessment client is a diagnosed case of ', 'I'],
            [trim($c->assessment), 'BI'],
            ['. Home meds were given and was advised to rest for ', 'I'],
            [(string) $c->rest_days, 'BI'],
            [' day/s. Patient is physically fit to ' . $c->fit_type . ' on ', 'I'],
            [$c->fit_date->format('F j, Y'), 'BI'],
            ['.', 'I'],
        ]);

        // Paragraph 2 follows the last line of paragraph 1, whatever its length
        $this->paragraph($pdf, self::BODY_1_Y + ($lines - 1 + self::BODY_GAP) * self::BODY_LEADING, [[self::CLOSING, 'I']]);

        // Signature block (centred)
        $pdf->SetFont('times', 'BIU', 13.9);
        $w = 420;
        $this->put($pdf, (self::SIGN_NAME_RIGHT - $w) * $this->k, self::SIGN_NAME_Y * $this->k, $w * $this->k,
            mb_strtoupper($c->physician_name) . ', MD', 'R');

        $pdf->SetFont('times', '', 13);
        $this->centered($pdf, self::SIGN_META_CX, self::SIGN_POS_Y, $c->position);
        $this->centered($pdf, self::SIGN_META_CX, self::SIGN_LIC_Y, 'Lic. No: ' . $c->license_number);
    }

    /* ----------------------------- helpers ----------------------------- */

    /** Place one cell of text, vertically centred on $ycMm. */
    private function put(Fpdi $pdf, float $xMm, float $ycMm, float $wMm, string $text, string $align = 'L', int $stretch = 0): void
    {
        $h = 6;
        $pdf->SetXY($xMm, $ycMm - $h / 2);
        $pdf->Cell($wMm, $h, $text, 0, 0, $align, false, '', $stretch, false, 'T', 'M');
    }

    /** Text centred on x = $cx (px) with the font that is currently set. */
    private function centered(Fpdi $pdf, float $cx, float $yc, string $text): void
    {
        $w = 420;
        $this->put($pdf, ($cx - $w / 2) * $this->k, $yc * $this->k, $w * $this->k, $text, 'C');
    }

    /**
     * Justified paragraph with mixed styles and a first-line indent.
     * $segments = [[text, 'I'|'BI'], ...]; line 1 is centred on $firstY, then every BODY_LEADING px.
     * The last line is left-aligned, all other lines are stretched to the full width.
     * Returns the number of lines drawn.
     */
    private function paragraph(Fpdi $pdf, float $firstY, array $segments): int
    {
        $size = self::BODY_SIZE;

        // 1) split into words; a word can hold several styled pieces (e.g. "Tarlac" + ".")
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

        // 2) greedy line breaking
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

        // 3) draw
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