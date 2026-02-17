<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\UserRole;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('RoleName', 'Admin')->first();
        $employerRole = Role::where('RoleName', 'Employer')->first();
        $jobSeekerRole = Role::where('RoleName', 'JobSeeker')->first();

        UserRole::unguard();
        $userRoleId = 1;

        // Assign Admin role to first user
        $admin = User::where('Email', 'admin@example.com')->first();
        if ($admin && $adminRole) {
            UserRole::firstOrCreate(
                ['UserID' => $admin->UserID, 'RoleID' => $adminRole->RoleID],
                ['UserRoleID' => $userRoleId++, 'AssignedAt' => now()]
            );
        }

        // Assign Employer role
        $employers = ['ahmed@techcompany.com', 'sara@healthco.com', 'khaled@edutech.com'];
        foreach ($employers as $email) {
            $user = User::where('Email', $email)->first();
            if ($user && $employerRole) {
                UserRole::firstOrCreate(
                    ['UserID' => $user->UserID, 'RoleID' => $employerRole->RoleID],
                    ['UserRoleID' => $userRoleId++, 'AssignedAt' => now()]
                );
            }
        }

        // Assign JobSeeker role
        $jobSeekers = [
            'mohammed.ali@email.com',
            'fatima.ahmed@email.com',
            'omar.salem@email.com',
            'noura.khaled@email.com',
            'yasser.abdulrahman@email.com',
            'huda.mohammed@email.com'
        ];
        foreach ($jobSeekers as $email) {
            $user = User::where('Email', $email)->first();
            if ($user && $jobSeekerRole) {
                UserRole::firstOrCreate(
                    ['UserID' => $user->UserID, 'RoleID' => $jobSeekerRole->RoleID],
                    ['UserRoleID' => $userRoleId++, 'AssignedAt' => now()]
                );
            }
        }
        UserRole::reguard();
    }
}
