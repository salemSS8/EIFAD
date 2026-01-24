<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['RoleName' => 'Admin'],
            ['RoleName' => 'Employer'],
            ['RoleName' => 'JobSeeker'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['RoleName' => $role['RoleName']], $role);
        }
    }
}
