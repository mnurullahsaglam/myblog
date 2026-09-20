<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Support\Features;
use Illuminate\Database\Seeder;
use Laravel\Pennant\Feature;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()
            ->admin()
            ->create([
                'name' => config('app.admin_name'),
                'email' => config('app.admin_email'),
            ]);

        $member = $this->createMember();

        $this->createSettings();
        $this->createInvites($owner);
        $this->createNotifications($owner);
        $this->createDevices($owner, $member);

        Feature::for($owner)->activate(Features::BudgetLimits);
    }

    private function createMember(): ?User
    {
        $email = config('app.member_email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        return User::factory()
            ->member()
            ->create([
                'name' => config('app.member_name'),
                'email' => $email,
            ]);
    }

    private function createSettings(): void
    {
        Setting::set('appearance', 'accent', 'khaki');
        Setting::set('appearance', 'color_scheme', 'dark');
        Setting::set('site_info', 'title', 'OP//SHELL');
        Setting::set('site_info', 'description', 'A developer blog and personal admin panel.');
        Setting::set('meta', 'meta_keywords', json_encode(['laravel', 'php', 'rust']), 'json');
    }

    private function createInvites(User $owner): void
    {
        Invite::factory()->create([
            'email' => 'pending@example.test',
            'invited_by' => $owner->id,
        ]);

        Invite::factory()->accepted()->create([
            'email' => 'accepted@example.test',
            'invited_by' => $owner->id,
        ]);

        Invite::factory()->revoked()->create([
            'email' => 'revoked@example.test',
            'invited_by' => $owner->id,
        ]);

        Invite::factory()->expired()->create([
            'email' => 'expired@example.test',
            'invited_by' => $owner->id,
        ]);
    }

    private function createNotifications(User $owner): void
    {
        $owner->notify(new AdminAlert('WakaTime synced', '14 summaries imported.', 'success'));
        $owner->notify(new AdminAlert('Bill due soon', 'Ev elektrik is due in 3 days.', 'warning'));
        $owner->notify(new AdminAlert('GitHub sync failed', 'The token was rejected.', 'danger'));

        $owner->notifications()->first()?->markAsRead();
    }

    private function createDevices(User $owner, ?User $member): void
    {
        $owner->createToken('iPhone 16 Pro');
        $owner->createToken('iPad');

        $member?->createToken('iPhone 15');
    }
}
