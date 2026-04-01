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
     * Generate a PDF from a Blade template and return the raw PDF bytes.
     *
     * @param  array{width: float, height: float}|null  $customSize  Paper size in mm
     */
    public function generate(string $template, array $data, ?array $customSize = null): string
    {
        $html = view($template, $data)->render();

        // Strategy 1: Gotenberg
        $pdfBytes = $this->tryGotenberg($html, $customSize);
        if ($pdfBytes !== null) {
            return $pdfBytes;
        }

        // Strategy 2: DomPDF fallback
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
     */
    public function stream(string $template, array $data, string $filename, ?array $customSize = null): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $pdfBytes = $this->generate($template, $data, $customSize);

            return response($pdfBytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF stream failed', ['template' => $template, 'error' => $e->getMessage()]);

            return response('PDF generation failed: '.$e->getMessage(), 500);
        }
    }

    protected function tryGotenberg(string $html, ?array $customSize = null): ?string
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

            if ($customSize) {
                $multipart[] = ['name' => 'paperWidth', 'contents' => (string) round($customSize['width'] / 25.4, 2)];
                $multipart[] = ['name' => 'paperHeight', 'contents' => (string) round($customSize['height'] / 25.4, 2)];
                $multipart[6] = ['name' => 'preferCssPageSize', 'contents' => 'false'];
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
