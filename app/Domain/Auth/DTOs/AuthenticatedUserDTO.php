<?php

namespace App\Domain\Auth\DTOs;

/**
 * Data Transfer Object for authenticated user data.
 */
readonly class AuthenticatedUserDTO
{
    public function __construct(
        public int $userId,
        public string $email,
        public ?string $name,
        public ?string $role,
        public string $sanctumToken,
        public ?string $firebaseUid = null,
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'token' => $this->sanctumToken,
            'firebase_uid' => $this->firebaseUid,
        ];
    }
}
