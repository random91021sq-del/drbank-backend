<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecoveryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    protected string $name;
    protected string $last_name;
    protected array $message;
    protected string $email;
    protected string $token;
    /**
     * Create a new message instance.
     */
    public function __construct(String $email, string $name, string $last_name,string $token, array $message)
    {
        $this->name=$name;
        $this->last_name=$last_name;
        $this->email=$email;
        $this->message=$message;
        $this->token=$token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->message['recoveryEmail'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        Log::info('Se envió el correo de recuperación de contraseña al correo: '.json_encode($this->email));
        return new Content(
            view: 'mails.RecoveryPassword',
            with:[
                'url'=>Str::replace(':token',$this->token,env('RECOVERY_REDIRECT')),
                'name'=>$this->name,
                'last_name'=>$this->last_name,
                'email' => $this->email,
                'hello'=>$this->message['hello'],
                'drbank'=>$this->message['drbank'],
                'goodDay'=>$this->message['goodDay'],
                'newAccess'=>$this->message['newAccess'],
                "messageExpired"=>$this->message["messageExpired"],
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
