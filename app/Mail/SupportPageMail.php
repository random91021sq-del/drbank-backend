<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SupportPageMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $reason;
    protected array $message;
    protected string $description;
    protected string $name;
    protected string $email;
    /**
     * Create a new message instance.
     */
    public function __construct(string $reason, string $description, string $name, string $email,array $message,)
    {
        $this->reason = $reason;
        $this->message = $message;
        $this->description = $description;
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message['supportEmail'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        Log::info('Se envió el correo de soporte al correo: '.json_encode($this->email));
        return new Content(
            view: 'mails.SupportPage',
            with:[
                'reasonAnswer' => $this->reason,
                'description' => $this->description,
                'name' => $this->name,
                'email' => $this->email,
                'hello'=>$this->message['hello'],
                'drbank'=>$this->message['drbank'],
                'goodDay'=>$this->message['goodDay'],
                'reason'=>$this->message['reason'],
                'haveGoodDay'=>$this->message['haveGoodDay'],
                'detailSupport'=>$this->message['detailSupport'],
                'emailSupport'=>$this->message['emailSupport'],
                'messageSupport'=>$this->message['messageSupport'],
                'drbankTeam'=>$this->message['drbankTeam'],
                'messageTo'=>$this->message['messageTo'],
                'messageHaveQuestion'=>$this->message['messageHaveQuestion'],
                'reserved'=>$this->message['reserved'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
