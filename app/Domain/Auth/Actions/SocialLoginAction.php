<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthenticatedUserDTO;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\Company\Models\CompanyProfile;
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
     * @param string|null $requestedRole Optional role to assign (JobSeeker, Employer)
     * @return AuthenticatedUserDTO
     */
    public function execute(SocialUser $socialUser, string $provider, ?string $requestedRole = null): AuthenticatedUserDTO
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
            $user = DB::transaction(function () use ($socialUser, $provider, $requestedRole) {
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

                // Assign role if requested, default to JobSeeker if null or invalid
                $roleName = in_array($requestedRole, ['JobSeeker', 'Employer']) ? $requestedRole : 'JobSeeker';
                $role = Role::where('RoleName', $roleName)->first();

                if ($role) {
                    $newUser->roles()->attach($role->RoleID);

                    // Create appropriate profile
                    if ($roleName === 'JobSeeker') {
                        JobSeekerProfile::create(['JobSeekerID' => $newUser->UserID]);
                    } elseif ($roleName === 'Employer') {
                        CompanyProfile::create([
                            'CompanyID' => $newUser->UserID,
                            'CompanyName' => $newUser->FullName,
                        ]);
                    }
                }

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
            providerId: $user->ProviderID,
        );
    }
}
