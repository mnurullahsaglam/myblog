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

/**
 * The only message this application sends.
 *
 * The plaintext token is passed in rather than read from the invite, because
 * the invite does not have it — it stores a hash.
 *
 * The invitee has no account yet and so no scheme of their own, so the message
 * renders light — the fallback for an unknown reader.
 *
 * ShouldQueue because this application's architecture test requires it of every
 * mailable. With QUEUE_CONNECTION=sync it still sends inside the request, so
 * nothing about today's behaviour changes; the day a real queue exists, issuing
 * an invite stops waiting on a mail server it cannot control.
 */
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
