<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * PDF Document Renderer.
 *
 * Strategy order:
 * 1. Gotenberg (Chrome in a separate container via HTTP API)
 * 2. DomPDF fallback (pure PHP)
 */
class PdfDocumentRenderer
{
    protected string $gotenbergUrl;

    public function __construct()
    {
        $this->gotenbergUrl = env('GOTENBERG_URL', 'http://gotenberg:3000');
    }

    /**
     * Resolve the compiled Tailwind CSS asset that the on-screen share
     * view (`pages.match-share`) references via `<link href="app.css">`
     * when rendered with `pdfMode=true`. Returns an asset map suitable
     * for `generate()` / `stream()`'s `$assets` argument — keyed by the
     * filename Gotenberg sees (the relative href the template uses), with
     * the absolute path on disk as the value.
     *
     * Lives on the renderer (not the controller) because TWO different
     * download endpoints emit this same PDF artefact and were previously
     * resolving the manifest from independent copies of the helper. The
     * second copy was the source of yet another "downloaded report still
     * looks like the same shit" regression — `MatchReportController::
     * download` rendered the OLD A4 narrative template (`exports.pdf-
     * match-report`) while `MatchExportController::pdfMyShooterReport`
     * rendered the NEW share view, so which template you got depended
     * entirely on which Download button you happened to click. Centralised
     * here so the answer is always "render the share view".
     *
     * Returns [] when the manifest is missing (e.g. local dev without
     * `npm run build`); the PDF still renders via Gotenberg, just without
     * the Tailwind layer.
     *
     * @return array<string,string>
     */
    public static function shareViewAssets(): array
    {
        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            return [];
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $cssRel = $manifest['resources/css/app.css']['file'] ?? null;
        if (! $cssRel) {
            return [];
        }
        $cssAbs = public_path('build/'.$cssRel);
        return file_exists($cssAbs) ? ['app.css' => $cssAbs] : [];
    }

    /**
     * Generate a PDF from a Blade template and return the raw PDF bytes.
     *
     * @param  array{width: float, height: float}|null  $customSize  Paper size in mm
     * @param  bool  $singlePage  When true, asks Gotenberg to emit the entire document on a single tall page regardless of `@page`
     * @param  array<string,string>  $assets   Sibling asset files to send to Gotenberg alongside index.html.
     *                                          Keyed by filename inside the zip (e.g. 'app.css' => '<file path on disk>').
     *                                          The Blade template's `<link rel="stylesheet" href="app.css">` (relative URL)
     *                                          will resolve against these. Lets us reuse the on-screen Tailwind CSS for the
     *                                          PDF without inlining the whole 400KB stylesheet into the HTML payload.
     */
    public function generate(string $template, array $data, ?array $customSize = null, bool $singlePage = false, array $assets = []): string
    {
        $html = view($template, $data)->render();

        // Strategy 1: Gotenberg
        $pdfBytes = $this->tryGotenberg($html, $customSize, $singlePage, $assets);
        if ($pdfBytes !== null) {
            return $pdfBytes;
        }

        // Strategy 2: DomPDF fallback (assets aren't supported here — DomPDF
        // doesn't understand modern CSS anyway, so the template should fall
        // back to inline-style equivalents on its own).
        return $this->generateWithDomPdf($template, $data, $customSize);
    }

    /**
     * Generate and save to disk, returning the file path.
     */
    public function generateAndSave(string $template, array $data, string $filePath, string $disk = 'local', ?array $customSize = null): string
    {
        $html = view($template, $data)->render();

        // Strategy 1: Gotenberg
        if ($this->generateWithGotenberg($html, $filePath, $disk, $customSize)) {
            return $filePath;
        }

        // Strategy 2: DomPDF fallback
        try {
            $pdf = Pdf::view($template, $data)->driver('dompdf');
            if ($customSize) {
                $pdf->paperSize($customSize['width'], $customSize['height'], 'mm');
            } else {
                $pdf->format('a4');
            }
            $pdf->disk($disk)->save($filePath);

            Log::info('PDF generated via DomPDF fallback', ['template' => $template, 'file' => $filePath]);

            return $filePath;
        } catch (\Throwable $e) {
            Log::error('All PDF generation methods failed', ['template' => $template, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate a PDF and stream it directly to the browser.
     *
     * @param  array<string,string>  $assets  See generate().
     */
    public function stream(string $template, array $data, string $filename, ?array $customSize = null, bool $singlePage = false, array $assets = []): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $pdfBytes = $this->generate($template, $data, $customSize, $singlePage, $assets);

            return response($pdfBytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF stream failed', ['template' => $template, 'error' => $e->getMessage()]);

            return response('PDF generation failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * @param  array<string,string>  $assets  Sibling files; key = filename inside the bundle, value = absolute path on disk.
     *                                          Each gets attached as another `files` part so the rendered HTML can
     *                                          reference them via relative URL (e.g. `<link href="app.css">`).
     */
    protected function tryGotenberg(string $html, ?array $customSize = null, bool $singlePage = false, array $assets = []): ?string
    {
        try {
            $multipart = [
                ['name' => 'files', 'contents' => $html, 'filename' => 'index.html'],
                ['name' => 'printBackground', 'contents' => 'true'],
                ['name' => 'marginTop', 'contents' => '0'],
                ['name' => 'marginBottom', 'contents' => '0'],
                ['name' => 'marginLeft', 'contents' => '0'],
                ['name' => 'marginRight', 'contents' => '0'],
                ['name' => 'preferCssPageSize', 'contents' => 'true'],
            ];

            // Attach sibling asset files (typically the compiled Tailwind CSS
            // for the on-screen share view, so the PDF reuses the same
            // stylesheet instead of forking a print-only template). Skipped
            // silently if the file is missing — Gotenberg will still render,
            // just without the missing asset.
            foreach ($assets as $filename => $diskPath) {
                if (is_string($diskPath) && file_exists($diskPath)) {
                    $multipart[] = [
                        'name' => 'files',
                        'contents' => fopen($diskPath, 'rb'),
                        'filename' => $filename,
                    ];
                } else {
                    Log::warning('Skipping missing PDF asset', ['filename' => $filename, 'path' => $diskPath]);
                }
            }

            if ($customSize) {
                $multipart[] = ['name' => 'paperWidth', 'contents' => (string) round($customSize['width'] / 25.4, 2)];
                $multipart[] = ['name' => 'paperHeight', 'contents' => (string) round($customSize['height'] / 25.4, 2)];
                $multipart[6] = ['name' => 'preferCssPageSize', 'contents' => 'false'];
            }

            // `singlePage=true` forces Gotenberg/Chromium to render the full
            // document as one tall page regardless of what the CSS @page rule
            // says. Needed because `@page { size: 210mm auto }` is only
            // honoured inconsistently across Chromium builds — the flag is
            // the authoritative switch for digital-first reports.
            if ($singlePage) {
                $multipart[] = ['name' => 'singlePage', 'contents' => 'true'];
            }

            $response = Http::timeout(30)
                ->asMultipart()
                ->post($this->gotenbergUrl.'/forms/chromium/convert/html', $multipart);

            if ($response->successful() && strlen($response->body()) >= 100) {
                Log::info('PDF generated via Gotenberg');

                return $response->body();
            }

            Log::warning('Gotenberg PDF generation failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('Gotenberg connection failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function generateWithGotenberg(string $html, string $filePath, string $disk, ?array $customSize = null): bool
    {
        $pdfBytes = $this->tryGotenberg($html, $customSize);
        if ($pdfBytes === null) {
            return false;
        }

        Storage::disk($disk)->put($filePath, $pdfBytes);
        Log::info('PDF saved via Gotenberg', ['file' => $filePath]);

        return true;
    }

    protected function generateWithDomPdf(string $template, array $data, ?array $customSize = null): string
    {
        try {
            $pdf = Pdf::view($template, $data)->driver('dompdf');
            if ($customSize) {
                $pdf->paperSize($customSize['width'], $customSize['height'], 'mm');
            } else {
                $pdf->format('a4');
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
            $pdf->save($tempPath);
            $content = file_get_contents($tempPath);
            @unlink($tempPath);

            Log::info('PDF generated via DomPDF fallback');

            return $content;
        } catch (\Throwable $e) {
            Log::error('DomPDF generation failed', ['template' => $template, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
