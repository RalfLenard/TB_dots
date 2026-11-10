<?php

namespace App\Http\Controllers;

use App\Models\AicsCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use setasign\Fpdi\Tcpdf\Fpdi;

class AicsCertificateController extends Controller
{
    /** All coordinates are "pixels" of a page rendered 893 wide (measured from AICS_2022.pdf). */
    private const REF_W = 893;

    private const NAME_UPPERCASE = true;

    private const SALUTATION = 'To whom it may concern,';
    private const CLOSING    = 'This certification is issued upon request and may be used for whatever medical purpose it may serve.';
    private const HEADING    = 'Recommended management:';

    private const PER_PAGE_OPTIONS = [10, 25, 50];

    /* ---------------------------------------------------------------
     |  COORDINATES (px @ 893 wide). y = vertical CENTER of the text.
     |  Use ?grid=1 on the PDF url to check them against the template.
     * ------------------------------------------------------------- */

    private const DATE_RIGHT  = 761.0;
    private const DATE_Y      = 351.5;
    private const DATE_SIZE   = 13.9;

    private const SALUTATION_POS = [108.1, 394.7];

    private const BODY_X       = 108.1;   // left margin
    private const BODY_W       = 682.0;   // paragraph width (justified)
    private const BODY_INDENT  = 54.0;    // first-line indent
    private const BODY_LEADING = 36.9;    // distance between lines
    private const BODY_SIZE    = 14.5;
    private const BODY_1_Y     = 447.0;   // centre of the first line

    private const LIST_X       = 162.1;   // the "-"
    private const LIST_TEXT_X  = 196.0;   // text after the "-"

    private const SIGN_NAME_RIGHT = 790.6;
    private const SIGN_NAME_Y     = 1125.8;   // normal position of the doctor name
    private const SIGN_NAME_MAX_Y = 1140.0;   // lowest it may go (above the footer)
    private const SIGN_META_CX    = 623.5;
    private const SIGN_POS_DY     = 26.9;     // position, below the name
    private const SIGN_LIC_DY     = 53.2;     // license number, below the name

    private float $k = 1;

    /* ------------------------------ pages ------------------------------ */

    public function index(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 10;
        }

        $certificates = AicsCertificate::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim($request->string('search')) . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('full_name', 'like', $s)
                      ->orWhere('barangay', 'like', $s)
                      ->orWhere('diagnosis', 'like', $s);
                });
            })
            ->latest('certificate_date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AicsCertificate $c) => [
                'id'               => $c->id,
                'full_name'        => $c->full_name,
                'age'              => $c->age,
                'barangay'         => $c->barangay,
                'diagnosis'        => $c->diagnosis,
                'physician_name'   => $c->physician_name,
                'certificate_date' => $c->certificate_date->format('M d, Y'),
                // raw values used to pre-fill the edit form
                'date_raw'         => $c->certificate_date->toDateString(),
                'recommendations'  => $this->recommendationList($c->recommendations),
            ]);

        return Inertia::render('Aics/Index', [
            'certificates'   => $certificates,
            'filters'        => [
                'search'   => $request->input('search', ''),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'physicians'     => collect(AicsCertificate::PHYSICIANS)
                                  ->map(fn ($info, $name) => [
                                      'name'           => $name,
                                      'position'       => $info['position'],
                                      'license_number' => $info['license'],
                                  ])
                                  ->values(),
            'today'          => now()->toDateString(),
            'flash'          => [
                'success'  => session('success'),
                'print_id' => session('print_id'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $certificate = AicsCertificate::create($this->validated($request));

        return redirect()
            ->route('aics.index')
            ->with('success', 'AICS certificate created.')
            ->with('print_id', $certificate->id);
    }

    public function update(Request $request, AicsCertificate $aicsCertificate): RedirectResponse
    {
        $aicsCertificate->update($this->validated($request));

        return redirect()
            ->back()
            ->with('success', 'AICS certificate updated.')
            ->with('print_id', $aicsCertificate->id);
    }

    public function destroy(AicsCertificate $aicsCertificate): RedirectResponse
    {
        $aicsCertificate->delete();

        return redirect()
            ->back()
            ->with('success', 'AICS certificate deleted.');
    }

    /** Shared by store() and update(). */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'certificate_date'  => ['required', 'date'],
            'full_name'         => ['required', 'string', 'max:100'],
            'age'               => ['required', 'integer', 'min:0', 'max:120'],
            'barangay'          => ['required', 'string', 'max:100'],
            'diagnosis'         => ['required', 'string', 'max:255'],
            'recommendations'   => ['nullable', 'array', 'max:10'],
            'recommendations.*' => ['nullable', 'string', 'max:100'],
            'physician_name'    => ['required', Rule::in(array_keys(AicsCertificate::PHYSICIANS))],
        ]);

        $clean = fn ($v) => trim(preg_replace('/\s+/', ' ', (string) $v));

        $data['full_name']       = $clean($data['full_name']);
        $data['barangay']        = $clean($data['barangay']);
        $data['diagnosis']       = rtrim($clean($data['diagnosis']), " .");
        $data['recommendations'] = collect($data['recommendations'] ?? [])
            ->map($clean)
            ->filter()
            ->values()
            ->all();

        // Position and license number always come from the server-side list.
        $physician              = AicsCertificate::PHYSICIANS[$data['physician_name']];
        $data['position']       = $physician['position'];
        $data['license_number'] = $physician['license'];

        return $data;
    }

    /**
     * Always returns a plain list of non-empty strings, whether the stored value
     * is an array, a JSON string, or a double-encoded JSON string.
     */
    private function recommendationList(mixed $value): array
    {
        for ($i = 0; $i < 2 && is_string($value); $i++) {
            $decoded = json_decode($value, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                break;
            }
            $value = $decoded;
        }

        if (is_string($value)) {
            $value = [$value];
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->values()
            ->all();
    }

    /* ------------------------------- PDF ------------------------------- */

    public function show(Request $request, AicsCertificate $aicsCertificate)
    {
        return $this->response($request, $aicsCertificate, 'inline');
    }

    public function download(Request $request, AicsCertificate $aicsCertificate)
    {
        return $this->response($request, $aicsCertificate, 'attachment');
    }

    private function response(Request $request, AicsCertificate $certificate, string $disposition)
    {
        $filename = 'aics-' . str($certificate->full_name)->slug() . '-' .
            $certificate->certificate_date->format('Y-m-d') . '.pdf';

        return response($this->render($certificate, $request->boolean('grid')), 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function render(AicsCertificate $c, bool $grid = false): string
    {
        $candidates = [
            storage_path('app/pdf-templates/aics.pdf'),
            storage_path('app/pdf-template/aics.pdf'),
            storage_path('app/private/pdf-templates/aics.pdf'),
            storage_path('app/private/pdf-template/aics.pdf'),
            resource_path('pdf-templates/aics.pdf'),
        ];

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

        return $pdf->Output('aics-' . $c->id . '.pdf', 'S');
    }

    /** Fill the page. Everything below the first paragraph flows, so any number of items works. */
    private function page(Fpdi $pdf, AicsCertificate $c): void
    {
        $k    = $this->k;
        $L    = self::BODY_LEADING;
        $name = self::NAME_UPPERCASE ? mb_strtoupper($c->full_name) : $c->full_name;

        $brgy = trim(preg_replace('/^\s*(?:brgy\.?|barangay)\s+/i', '', $c->barangay));
        $recs = $this->recommendationList($c->recommendations);

        $bodyW = self::BODY_W * $k;
        $right = (self::BODY_X + self::BODY_W);

        // ---- 1) measure everything (no drawing yet)
        $p1 = $this->wrap($pdf, [
            ['This is to certify that I have seen and examined ', 'I'],
            [$name . ', ' . $c->age . ' years old', 'BI'],
            [' from ', 'I'],
            ['BRGY. ' . $brgy . ' Concepcion, Tarlac', 'BI'],
            [' with a diagnosis of ', 'I'],
            [rtrim(trim($c->diagnosis), '.') . '.', 'I'],
        ], $bodyW - self::BODY_INDENT * $k, $bodyW);

        $itemW  = ($right - self::LIST_TEXT_X) * $k;
        $items  = array_map(fn ($r) => $this->wrap($pdf, [[$r, 'I']], $itemW, $itemW), $recs);
        $closer = $this->wrap($pdf, [[self::CLOSING, 'I']], $bodyW - self::BODY_INDENT * $k, $bodyW);

        // Vertical positions, in "lines" counted from the first line of paragraph 1.
        $u = count($p1) - 1;
        $headU = null;
        $itemU = [];
        if ($items) {
            $headU = $u + 1.4;
            $cur   = $headU + 1.4;
            foreach ($items as $lines) {
                $itemU[] = $cur;
                $cur    += count($lines);
            }
            $u = $cur - 1;
        }
        $closeU = $u + 2.0;
        $signU  = $closeU + count($closer) - 1 + (95 / $L);

        // If the list is long, tighten the line spacing so the signature stays above the footer.
        $lead  = max(min($L, (self::SIGN_NAME_MAX_Y - self::BODY_1_Y) / $signU), $L * 0.7);
        $signY = max(self::SIGN_NAME_Y, self::BODY_1_Y + $signU * $lead);

        // ---- 2) draw
        // Date: bold italic, capitals
        $pdf->SetFont('times', 'BI', self::DATE_SIZE);
        $w = 400;
        $this->put($pdf, (self::DATE_RIGHT - $w) * $k, self::DATE_Y * $k, $w * $k,
            mb_strtoupper($c->certificate_date->format('F d, Y')), 'R');

        // Salutation
        $pdf->SetFont('times', 'I', self::BODY_SIZE);
        $this->put($pdf, self::SALUTATION_POS[0] * $k, self::SALUTATION_POS[1] * $k, 400 * $k,
            self::SALUTATION, 'L');

        // Paragraph 1
        $this->draw($pdf, $p1, (self::BODY_X + self::BODY_INDENT) * $k, self::BODY_X * $k, self::BODY_1_Y, $lead, true);

        // Recommendations
        if ($items) {
            $pdf->SetFont('times', 'I', self::BODY_SIZE);
            $this->put($pdf, self::LIST_X * $k, (self::BODY_1_Y + $headU * $lead) * $k, 400 * $k, self::HEADING, 'L');

            foreach ($items as $i => $lines) {
                $y = self::BODY_1_Y + $itemU[$i] * $lead;
                $pdf->SetFont('times', '', self::BODY_SIZE);
                $this->put($pdf, self::LIST_X * $k, $y * $k, 10 * $k, '-', 'L');
                $this->draw($pdf, $lines, self::LIST_TEXT_X * $k, self::LIST_TEXT_X * $k, $y, $lead, false);
            }
        }

        // Closing paragraph
        $this->draw($pdf, $closer, (self::BODY_X + self::BODY_INDENT) * $k, self::BODY_X * $k,
            self::BODY_1_Y + $closeU * $lead, $lead, true);

        // Signature block
        $pdf->SetFont('times', 'BIU', 13.9);
        $w = 420;
        $this->put($pdf, (self::SIGN_NAME_RIGHT - $w) * $k, $signY * $k, $w * $k,
            mb_strtoupper($c->physician_name) . ', MD', 'R');

        $pdf->SetFont('times', '', 13);
        $this->centered($pdf, self::SIGN_META_CX, $signY + self::SIGN_POS_DY, $c->position);
        $this->centered($pdf, self::SIGN_META_CX, $signY + self::SIGN_LIC_DY, 'License No: ' . $c->license_number);
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
     * Break styled text into lines.
     * $segments = [[text, 'I'|'BI'], ...]; $firstW / $restW are the widths (mm) of line 1 and the others.
     * Returns [[words, widthOfWords, availableWidth], ...].
     */
    private function wrap(Fpdi $pdf, array $segments, float $firstW, float $restW): array
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

        $pdf->SetFont('times', 'I', $size);
        $space = $pdf->GetStringWidth(' ');

        $lines = [];
        $line  = [];
        $sum   = 0.0;
        foreach ($words as $word) {
            $avail = count($lines) === 0 ? $firstW : $restW;
            $ww    = array_sum(array_column($word, 'w'));
            if ($line && $sum + $ww + count($line) * $space > $avail) {
                $lines[] = [$line, $sum, $avail];
                $line = [];
                $sum  = 0.0;
            }
            $line[] = $word;
            $sum   += $ww;
        }
        if ($line) {
            $lines[] = [$line, $sum, count($lines) === 0 ? $firstW : $restW];
        }

        return $lines;
    }

    /** Draw lines made by wrap(). $y0 and $leading are in px; x values in mm. */
    private function draw(Fpdi $pdf, array $lines, float $firstX, float $restX, float $y0, float $leading, bool $justify): void
    {
        $size = self::BODY_SIZE;
        $pdf->SetFont('times', 'I', $size);
        $space = $pdf->GetStringWidth(' ');

        foreach ($lines as $i => [$lineWords, $lineSum, $avail]) {
            $isLast = $i === count($lines) - 1;
            $gap    = ($justify && !$isLast && count($lineWords) > 1)
                ? ($avail - $lineSum) / (count($lineWords) - 1)
                : $space;

            $x = $i === 0 ? $firstX : $restX;
            $y = ($y0 + $i * $leading) * $this->k;

            foreach ($lineWords as $word) {
                foreach ($word as $piece) {
                    $pdf->SetFont('times', $piece['s'], $size);
                    $this->put($pdf, $x, $y, $piece['w'] + 1, $piece['t'], 'L');
                    $x += $piece['w'];
                }
                $x += $gap;
            }
        }
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