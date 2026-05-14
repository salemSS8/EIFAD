<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use App\Domain\Job\Models\JobAd;
use App\Domain\Company\Models\CompanyProfile;
use App\Domain\User\Models\User;
use App\Domain\AI\Models\JobDemandSnapshot;
use App\Jobs\SyncMarketTrendsJob;
use App\Domain\AI\Services\SyncMarketTrendsService;

class SyncMarketTrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_job_aggregates_data_correctly(): void
    {
        // 1. Create User, Company and Industry
        $user = User::create([
            'FullName' => 'Test Company',
            'Email' => 'company@example.com',
            'PasswordHash' => 'hash',
        ]);

        $company = CompanyProfile::create([
            'CompanyID' => $user->UserID,
            'CompanyName' => 'Tech Corp',
            'FieldOfWork' => 'Technology',
        ]);

        $industry = \App\Domain\Job\Models\Industry::create(['name' => 'Information Technology']);

        // 2. Create Job Ad
        JobAd::create([
            'CompanyID' => $company->CompanyID,
            'industry_id' => $industry->id,
            'Title' => 'Laravel Developer',
            'Location' => 'Riyadh',
            'Status' => 'Active',
            'SalaryMin' => 5000,
            'SalaryMax' => 7000,
            'PostedAt' => now(),
        ]);

        // 3. Run the job
        $job = new SyncMarketTrendsJob();
        $job->handle(new SyncMarketTrendsService());

        // 4. Assert snapshots
        $this->assertDatabaseHas('jobdemandsnapshot', [
            'JobTitle' => 'Laravel Developer',
            'city_name' => 'Riyadh',
            'industry_id' => $industry->id,
            'PostCount' => 1,
        ]);

        // 5. Assert sync log
        $this->assertDatabaseHas('sync_logs', [
            'status' => 'completed',
        ]);
    }

    public function test_api_returns_filtered_trends(): void
    {
        // 1. Create user for authentication
        $user = User::factory()->create();
        
        // 2. Seed some snapshots
        $industry = \App\Domain\Job\Models\Industry::create(['name' => 'Finance']);
        JobDemandSnapshot::create([
            'JobTitle' => 'Accountant',
            'industry_id' => $industry->id,
            'city_name' => 'London',
            'PostCount' => 5,
            'SnapshotDate' => now()->toDateString(),
        ]);

        // 3. Query API with authentication
        $response = $this->actingAs($user)->getJson('/api/market-trends?industry_id=' . $industry->id . '&city_name=London');

        // 4. Assertions
        $response->assertStatus(200)
            ->assertJsonFragment(['labels' => ['Accountant']])
            ->assertJsonFragment(['values' => [5]]);
    }
}
