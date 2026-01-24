<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthenticatedUserDTO;
use App\Domain\Auth\Services\FirebaseAuthService;
use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;
use App\Domain\Shared\Exceptions\BusinessRuleException;

/**
 * Use case: Login an existing user using Firebase authentication.
 * 
 * This action:
 * 1. Verifies the Firebase ID token
 * 2. Finds the existing user
 * 3. Returns a new Sanctum token for API access
 */
class LoginWithFirebaseAction implements ActionInterface
{
    public function __construct(
        private FirebaseAuthService $firebaseAuth,
    ) {}

    /**
     * Execute the login use case.
     *
     * @param string $firebaseIdToken The ID token from Firebase Auth
     * @return AuthenticatedUserDTO
     * @throws BusinessRuleException
     */
    public function execute(string $firebaseIdToken): AuthenticatedUserDTO
    {
        // Verify Firebase token
        try {
            $tokenPayload = $this->firebaseAuth->verifyIdToken($firebaseIdToken);
        } catch (\Exception $e) {
            throw BusinessRuleException::because('Invalid authentication token');
        }

        $userInfo = $this->firebaseAuth->extractUserInfo($tokenPayload);

        // Find existing user
        $user = User::where('FirebaseUID', $userInfo['firebase_uid'])->first();

        if (!$user) {
            // Try by email as fallback
            $user = User::where('Email', $userInfo['email'])->first();

            if ($user && empty($user->FirebaseUID)) {
                // Link Firebase UID to existing user
                $user->update(['FirebaseUID' => $userInfo['firebase_uid']]);
            }
        }

        if (!$user) {
            throw BusinessRuleException::because('User not registered');
        }

        // Update avatar if changed (optional)
        if (!empty($userInfo['picture']) && $userInfo['picture'] !== $user->Avatar) {
            $user->update(['Avatar' => $userInfo['picture']]);
        }

        // Get User Role
        $role = $user->roles()->first();
        $roleName = $role ? $role->RoleName : 'JobSeeker'; // Fallback or throw error

        // Generate new Sanctum token
        $token = $user->createToken('auth-token', [$roleName])->plainTextToken;

        return new AuthenticatedUserDTO(
            userId: $user->UserID,
            email: $user->Email,
            name: $user->FullName,
            role: $roleName,
            sanctumToken: $token,
            firebaseUid: $user->FirebaseUID,
        );
    }
}
