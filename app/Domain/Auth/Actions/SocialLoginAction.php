<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthenticatedUserDTO;
use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialUser;

/**
 * Use case: Login or Register via Social Provider (Google/LinkedIn).
 */
class SocialLoginAction implements ActionInterface
{
    /**
     * Execute the social login logic.
     *
     * @param SocialUser $socialUser
     * @param string $provider (google, linkedin)
     * @return AuthenticatedUserDTO
     */
    public function execute(SocialUser $socialUser, string $provider): AuthenticatedUserDTO
    {
        // 1. Try to find user by ProviderID
        $user = User::where('ProviderID', $socialUser->getId())->first();

        // 2. If not found, try by email
        if (!$user) {
            $user = User::where('Email', $socialUser->getEmail())->first();

            if ($user) {
                // Link ProviderID to existing user
                $user->update([
                    'ProviderID' => $socialUser->getId(),
                    'AuthProvider' => $provider,
                    'Avatar' => $user->Avatar ?? $socialUser->getAvatar(),
                    'IsVerified' => true // Assume social login verifies email (mostly true)
                ]);
            }
        }

        // 3. If still not found, Create New User
        if (!$user) {
            $user = DB::transaction(function () use ($socialUser, $provider) {
                $newUser = User::create([
                    'FullName' => $socialUser->getName(),
                    'Email' => $socialUser->getEmail(),
                    'ProviderID' => $socialUser->getId(),
                    'AuthProvider' => $provider,
                    'Avatar' => $socialUser->getAvatar(),
                    'IsVerified' => true,
                    'CreatedAt' => now(),
                    // PasswordHash remains null
                ]);

                // We don't assign a role yet. User must call /auth/set-role endpoint?
                // Or we can default to JobSeeker? 
                // Plan says: "Assign role (if provided)" but Socialite callback doesn't have role.
                // Best practice: Create user without role, then redirect to a "Select Role" page on frontend.
                // For this API: User is created. If they try to access protected routes, they might need a role.

                return $newUser;
            });
        }

        // 4. Generate Token
        $role = $user->roles()->first();
        $roleName = $role ? $role->RoleName : null;

        // Capabilities based on role? For now just role name.
        $abilities = $roleName ? [$roleName] : [];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return new AuthenticatedUserDTO(
            userId: $user->UserID,
            email: $user->Email,
            name: $user->FullName,
            role: $roleName,
            sanctumToken: $token,
            firebaseUid: $user->ProviderID, // Reuse DTO field or ignore
        );
    }
}
