<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\People\AcceptInvite;
use App\Contracts\NotifiesAdmin;
use App\Http\Requests\AcceptInviteRequest;
use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class InviteController extends Controller
{
    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('admin.dashboard');
        }

        $invite = $this->usableInvite($token);

        return Inertia::render('Auth/AcceptInvite', [
            'email' => $invite->email,
            'token' => $token,
        ]);
    }

    public function store(AcceptInviteRequest $request, string $token, AcceptInvite $acceptInvite): RedirectResponse
    {
        $invite = $this->usableInvite($token);

        /** @var array{name: string, password: string} $data */
        $data = $request->validated();

        try {
            $user = $acceptInvite->handle($invite, $data['name'], $data['password']);
        } catch (RuntimeException) {
            abort(404);
        }

        auth()->login($user);
        $request->session()->regenerate();

        $this->notifier->success('Welcome', 'Add a passkey so you can sign in without a password.');

        return to_route('admin.profile');
    }

    private function usableInvite(string $token): Invite
    {
        $invite = Invite::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        abort_if(! $invite instanceof Invite || ! $invite->isUsable(), 404);

        return $invite;
    }
}
