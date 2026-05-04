<?php

namespace Database\Seeders;

use App\Domain\User\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['RoleID' => 1, 'RoleName' => 'Admin'],
            ['RoleID' => 2, 'RoleName' => 'Employer'],
            ['RoleID' => 3, 'RoleName' => 'JobSeeker'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['RoleName' => $role['RoleName']]);
        }
    }
}
