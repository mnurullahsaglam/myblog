<x-mail::message :palette="$palette">
# You have been invited

You have been invited to {{ config('app.name') }}@if ($invitedByName !== config('app.name')) by {{ $invitedByName }}@endif.

The link below works once and expires in 48 hours.

<x-mail::button :url="$url">
Accept the invitation
</x-mail::button>

If you were not expecting this, ignore it — nothing happens until the link is opened.
</x-mail::message>
