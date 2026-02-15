<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['RoleID' => 1, 'RoleName' => 'Admin'],
            ['RoleID' => 2, 'RoleName' => 'Employer'],
            ['RoleID' => 3, 'RoleName' => 'JobSeeker'],
        ];

        Role::unguard();
        foreach ($roles as $role) {
            Role::firstOrCreate(['RoleName' => $role['RoleName']], $role);
        }
        Role::reguard();
    }
}
