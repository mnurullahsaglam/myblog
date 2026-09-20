<x-mail::message>
# You have been invited

{{ $invitedByName }} has invited you to {{ config('app.name') }}.

The link below works once and expires in 48 hours.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

If you were not expecting this, ignore it — nothing happens until the link is opened.
</x-mail::message>
