<?php

namespace Tests\Feature;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleCaseInsensitivityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_matches_roles_case_insensitively()
    {
        // 1. Create a user
        $user = User::factory()->create();

        // 2. Create a role with lowercase name in the database
        $role = Role::create([
            'RoleID' => 3,
            'RoleName' => 'jobseeker', // Lowercase
        ]);

        // 3. Assign role to user
        DB::table('userrole')->insert([
            'UserID' => $user->UserID,
            'RoleID' => $role->RoleID,
            'AssignedAt' => now(),
        ]);

        // 4. Verify isJobSeeker() matches (it checks for 'JobSeeker' PascalCase)
        $this->assertTrue($user->isJobSeeker());

        // 5. Verify manual check with different casing
        $this->assertTrue($user->hasRole('JOBSEEKER'));
        $this->assertTrue($user->hasRole('JobSeeker'));
        $this->assertTrue($user->hasRole('jobseeker'));
    }
}
