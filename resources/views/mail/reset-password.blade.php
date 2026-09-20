<x-mail::message :palette="$palette">
# Reset your password

Someone asked to reset the password for this account. If that was you, use the link below.

<x-mail::button :url="$url">
Reset password
</x-mail::button>

The link expires in {{ $expiresInMinutes }} minutes and works once.

If you did not ask for this, ignore it — your password stays as it is.
</x-mail::message>
