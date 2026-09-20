<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\General;

use App\Actions\People\CreateInvite;
use App\Contracts\NotifiesAdmin;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteRequest;
use App\Models\Invite;
use App\Tables\Definitions\InviteTable;
use App\Tables\Definitions\UserTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who can reach the panel, and who has been asked to.
 *
 * Not an AdminResourceController: an invite is created and revoked, never
 * edited, so the form contract would bring an edit route that has to be
 * disabled and a schema nothing renders.
 */
final class PeopleController extends Controller
{
    public function __construct(private readonly NotifiesAdmin $notifier) {}

    public function index(Request $request): Response
    {
        $users = new UserTable;
        $invites = new InviteTable;

        return Inertia::render('General/People/Index', [
            'users' => [
                'schema' => $users->schema(),
                'rows' => $users->rows($request),
            ],
            'invites' => [
                'schema' => $invites->schema(),
                'rows' => $invites->rows($request),
            ],
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => ucfirst($role->value)],
                UserRole::cases(),
            ),
        ]);
    }

    public function store(InviteRequest $request, CreateInvite $createInvite): RedirectResponse
    {
        /** @var array{email: string, role: string} $data */
        $data = $request->validated();

        ['token' => $token] = $createInvite->handle(
            $data['email'],
            UserRole::from($data['role']),
            $request->user(),
        );

        $this->notifier->success('Invitation sent', 'The link is on screen until you leave this page.');

        return $this->backWithLink($token);
    }

    public function revoke(Invite $invite): RedirectResponse
    {
        abort_unless($invite->isUsable(), 404);

        $invite->update(['revoked_at' => now()]);

        $this->notifier->success('Invitation revoked', 'That link no longer works.');

        return redirect()->route('admin.people.index');
    }

    /**
     * Reissue rather than resend: only the hash is stored, so the original link
     * cannot be rebuilt. CreateInvite supersedes the live invite on its own, so
     * nothing is revoked here.
     */
    public function reissue(Request $request, Invite $invite, CreateInvite $createInvite): RedirectResponse
    {
        abort_unless($invite->isUsable(), 404);

        ['token' => $token] = $createInvite->handle($invite->email, $invite->role, $request->user());

        $this->notifier->success('Invitation reissued', 'The previous link has stopped working.');

        return $this->backWithLink($token);
    }

    /**
     * The only moment this URL can be shown. Flashed rather than returned as a
     * prop so a refresh does not put it back on screen.
     */
    private function backWithLink(string $token): RedirectResponse
    {
        return redirect()
            ->route('admin.people.index')
            ->with('flash.invite_url', route('invite.show', $token));
    }
}
