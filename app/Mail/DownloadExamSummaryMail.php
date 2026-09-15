<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\PdfGenerator;

class DownloadExamSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $email;
    protected array $exams;
    protected array $message;

    public function __construct(string $email, array $exams, array $message)
    {
        $this->email = $email;
        $this->message = $message;
        $this->exams = array_map(function ($exam) {
            if (is_object($exam)) {
                $exam = (array) $exam;
            }

            return array_map(function ($value) {
                if (is_object($value)) {
                    return (array) $value;
                }

                return $value;
            }, $exam);
        }, $exams);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message['subject']
        );
    }

    public function content(): Content
    {
        Log::info('Se envió el correo de resumen de examen al correo: '.json_encode($this->email));

        return new Content(
            view: 'mails.DownloadExamSummary',
            with: [
                'email' => $this->email,
                'name' => $this->message['name'] ?? '',
                'hello' => $this->message['hello'],
                'drbank' => $this->message['drbank'],
                'examSummaryIntro' => $this->message['examSummaryIntro'],
                'drbankTeam' => $this->message['drbankTeam'],
                'messageTo' => $this->message['messageTo'],
                'messageHaveQuestion' => $this->message['messageHaveQuestion'],
                'reserved' => $this->message['reserved'],
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->exams as $exam) {
            $title = $exam['title'] ?? 'resumen_examen';
            $filename = $title;
            $filename .= '.pdf';

            $pdfContent = PdfGenerator::generateExamSummaryPdf($title, $exam);

            $attachments[] = Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
