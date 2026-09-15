<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivationProfileMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $name;
    protected string $last_name;
    protected array $message;
    protected string $email;
    protected string $code_activate;
    /**
     * Create a new message instance.
     */
    public function __construct(string $name, string $last_name,array $message, string $email,string $code_activate)
    {
        $this->name=$name;
        $this->last_name=$last_name;
        $this->email=$email;
        $this->code_activate=$code_activate;
        $this->message = $message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message['codeActivate']
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        Log::info('Se envió el correo de activación al correo: ' . json_encode($this->email));
        Log::info('Datos: '.json_encode(['message'=>$this->message]));
        return new Content(
            view: 'mails.ActivationProfile',
            with: [
                'name' => $this->name,
                'lastname' => $this->last_name,
                'email' => $this->email,
                'code' => $this->code_activate,
                'hello'=>$this->message['hello'],
                'drbank'=>$this->message['drbank'],
                'messageActivation'=>$this->message['messageActivation'],
                'goodDay'=>$this->message['goodDay'],
                'drbankTeam'=>$this->message['drbankTeam'],
                'messageTo'=>$this->message['messageTo'],
                'messageHaveQuestion'=>$this->message['messageHaveQuestion'],
                'reserved'=>$this->message['reserved']
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
