<?php

namespace Tests\Feature\Api;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Role::where('RoleName', 'Employer')->exists()) {
            Role::create(['RoleName' => 'Employer']);
        }
    }

    protected function createCompany(string $name, string $address, string $field, bool $verified = true): int
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('RoleName', 'Employer')->first());

        DB::table('companyprofile')->insert([
            'CompanyID' => $user->UserID,
            'CompanyName' => $name,
            'OrganizationName' => $name.' Org',
            'Address' => $address,
            'FieldOfWork' => $field,
            'IsCompanyVerified' => $verified,
        ]);

        return $user->UserID;
    }

    public function test_can_search_companies_by_name()
    {
        $this->createCompany('Tech Solutions', 'Sana\'a', 'IT');
        $this->createCompany('Creative Agency', 'Cairo', 'Design');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/companies?name=Tech');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.CompanyName', 'Tech Solutions');
    }

    public function test_can_filter_companies_by_location()
    {
        $this->createCompany('Tech One', 'Sana\'a', 'IT');
        $this->createCompany('Tech Two', 'Cairo', 'IT');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/companies?location=Cairo');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.Address', 'Cairo');
    }

    public function test_can_filter_companies_by_field()
    {
        $this->createCompany('Doc Hospital', 'Amman', 'Health');
        $this->createCompany('Tech Lab', 'Amman', 'IT');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/companies?field=Health');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.FieldOfWork', 'Health');
    }

    public function test_can_combine_filters()
    {
        $this->createCompany('Big Tech', 'New York', 'IT');
        $this->createCompany('Small Tech', 'New York', 'Design');
        $this->createCompany('Other Tech', 'London', 'IT');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/companies?name=Tech&location=New York&field=IT');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.CompanyName', 'Big Tech');
    }

    public function test_it_only_shows_verified_companies()
    {
        $this->createCompany('Verified Corp', 'Sana\'a', 'IT', true);
        $this->createCompany('Unverified Corp', 'Sana\'a', 'IT', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/companies');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.CompanyName', 'Verified Corp');
    }
}
