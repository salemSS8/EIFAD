<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthenticatedUserDTO;
use App\Domain\Auth\Services\FirebaseAuthService;
use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Use case: Register a new user using Firebase authentication.
 * 
 * This action:
 * 1. Verifies the Firebase ID token
 * 2. Creates a new user record if not exists
 * 3. Assigns the specified role
 * 4. Returns a Sanctum token for API access
 */
class RegisterWithFirebaseAction implements ActionInterface
{
    public function __construct(
        private FirebaseAuthService $firebaseAuth,
    ) {}

    /**
     * Execute the registration use case.
     *
     * @param string $firebaseIdToken The ID token from Firebase Auth
     * @param string $role The role to assign (job_seeker, employer)
     * @param array $additionalData Optional additional user data
     * @return AuthenticatedUserDTO
     * @throws BusinessRuleException
     */
    public function execute(string $firebaseIdToken, ?string $roleName = null, array $additionalData = []): AuthenticatedUserDTO
    {
        // Validate role if provided
        if ($roleName && !in_array($roleName, ['JobSeeker', 'Employer'])) {
            throw BusinessRuleException::because('Invalid role specified');
        }

        // Verify Firebase token
        try {
            $tokenPayload = $this->firebaseAuth->verifyIdToken($firebaseIdToken);
        } catch (\Exception $e) {
            throw BusinessRuleException::because('Invalid authentication token');
        }

        $userInfo = $this->firebaseAuth->extractUserInfo($tokenPayload);

        // Check if user already exists
        $existingUser = User::where('FirebaseUID', $userInfo['firebase_uid'])
            ->orWhere('Email', $userInfo['email'])
            ->first();

        if ($existingUser) {
            throw BusinessRuleException::because('User already registered');
        }

        // Create new user within transaction
        return DB::transaction(function () use ($userInfo, $roleName, $additionalData) {
            $user = User::create([
                'FirebaseUID' => $userInfo['firebase_uid'],
                'Email' => $userInfo['email'],
                'FullName' => $additionalData['name'] ?? $userInfo['name'] ?? 'New User',
                'Avatar' => $userInfo['picture'] ?? null,
                'IsVerified' => $userInfo['email_verified'] ?? false,
                'AuthProvider' => $userInfo['provider'] ?? 'firebase',
                'CreatedAt' => now(),
                // PasswordHash left null (ensure DB allows it)
            ]);

            // Assign role (if provided)
            if ($roleName) {
                $role = \App\Domain\User\Models\Role::where('RoleName', $roleName)->first();
                if ($role) {
                    DB::table('userrole')->insert([
                        'UserID' => $user->UserID,
                        'RoleID' => $role->RoleID,
                        'AssignedAt' => now(),
                    ]);
                }

                // Create profile based on role
                if ($roleName === 'JobSeeker') {
                    DB::table('jobseekerprofile')->insert([
                        'JobSeekerID' => $user->UserID,
                    ]);
                } else {
                    DB::table('companyprofile')->insert([
                        'CompanyID' => $user->UserID,
                    ]);
                }
            }

            // Generate Sanctum token
            $token = $user->createToken('auth-token', $roleName ? [$roleName] : [])->plainTextToken;

            return new AuthenticatedUserDTO(
                userId: $user->UserID,
                email: $user->Email,
                name: $user->FullName,
                role: $roleName,
                sanctumToken: $token,
                firebaseUid: $user->FirebaseUID,
            );
        });
    }
}
