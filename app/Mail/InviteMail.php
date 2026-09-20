<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Theme\Palette;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class InviteMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly string $invitedByName,
    ) {}

    public function envelope(): Envelope
    {
        $name = config('app.name');

        return new Envelope(subject: 'You have been invited to '.(is_string($name) ? $name : 'the panel'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invite',
            with: ['palette' => Palette::forUnknownRecipient()],
        );
    }
}
