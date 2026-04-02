<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

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
}
