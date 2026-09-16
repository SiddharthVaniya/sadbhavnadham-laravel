<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DailyDonationReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $reportDate,
        public int $orderCount,
        public string $xlsxBinary,
        public string $attachmentName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily donations — '.$this->formalBritishDate(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.daily-donation-report',
            with: [
                'formalBritishDate' => $this->formalBritishDate(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->xlsxBinary, $this->attachmentName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    public function formalBritishDate(): string
    {
        return $this->reportDate->format('jS F Y');
    }
}
