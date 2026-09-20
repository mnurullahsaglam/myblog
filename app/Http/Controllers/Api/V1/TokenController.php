<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

final class TokenController extends Controller
{
    /**
     * Issue a token for one device.
     *
     * Two-factor is enforced here rather than skipped: a token outlives a
     * session and lives on the most easily stolen device in the house, so
     * trading a password for one without the second factor would remove it.
     *
     * An unknown address and a wrong password fail identically, so the endpoint
     * cannot be used to discover who has an account.
     */
    public function store(TokenRequest $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        /** @var array{email: string, password: string, device_name: string, code?: string|null} $data */
        $data = $request->validated();

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first();

        if (! $user instanceof User || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Those credentials do not match.']);
        }

        if ($user->hasEnabledTwoFactorAuthentication() && ! $this->passesTwoFactor($user, $data['code'] ?? null, $provider)) {
            return response()->json(['two_factor' => true], 423);
        }

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * A recovery code is checked against the stored list before it is replaced.
     *
     * Fortify's replaceRecoveryCode() returns void and performs a blind
     * str_replace, so calling it without checking first would accept any string
     * at all as a valid second factor.
     */
    private function passesTwoFactor(User $user, ?string $code, TwoFactorAuthenticationProvider $provider): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $secret = $user->two_factor_secret;

        if (is_string($secret)) {
            $decrypted = decrypt($secret);

            if (is_string($decrypted) && $provider->verify($decrypted, $code)) {
                return true;
            }
        }

        if (! in_array($code, $user->recoveryCodes(), true)) {
            return false;
        }

        $user->replaceRecoveryCode($code);

        return true;
    }
}
