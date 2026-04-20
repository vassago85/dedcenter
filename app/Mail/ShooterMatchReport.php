<?php

namespace App\Mail;

use App\Services\PdfDocumentRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ShooterMatchReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $report,
    ) {}

    public function envelope(): Envelope
    {
        $matchName = $this->report['match']['name'] ?? 'Match';

        return new Envelope(
            subject: "Your Match Report — {$matchName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shooter-match-report',
            with: ['report' => $this->report],
        );
    }

    public function attachments(): array
    {
        try {
            $renderer = app(PdfDocumentRenderer::class);
            $pdfBytes = $renderer->generate('exports.pdf-match-report', ['report' => $this->report], null, true);
            $filename = Str::slug($this->report['match']['name'] ?? 'match') . '-report.pdf';

            return [
                \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $pdfBytes, $filename)
                    ->withMime('application/pdf'),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PDF attachment generation failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
