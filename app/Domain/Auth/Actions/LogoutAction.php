<?php

namespace App\Domain\Auth\Actions;

use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;

/**
 * Use case: Logout user by revoking tokens.
 */
class LogoutAction implements ActionInterface
{
    /**
     * Execute the logout use case.
     *
     * @param User $user The authenticated user
     * @param bool $allDevices Whether to logout from all devices
     * @return void
     */
    public function execute(User $user, bool $allDevices = false): void
    {
        if ($allDevices) {
            // Revoke all tokens
            $user->tokens()->delete();
        } else {
            // Revoke only current token
            $user->currentAccessToken()->delete();
        }
    }
}
