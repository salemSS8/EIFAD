<?php

namespace Tests\Feature\Api\Job;

use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JobSearchTest extends TestCase
{
    use RefreshDatabase;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Employer']);
        Role::create(['RoleName' => 'JobSeeker']);

        // Create employer with company profile
        $employer = User::factory()->create();
        $employer->roles()->attach(Role::where('RoleName', 'Employer')->first());
        DB::table('companyprofile')->insert([
            'CompanyID' => $employer->UserID,
            'CompanyName' => 'Tech Corp',
            'FieldOfWork' => 'Technology',
        ]);
        $this->companyId = $employer->UserID;

        // Seed jobs
        $this->seedJobs();
    }

    protected function seedJobs(): void
    {
        DB::table('jobad')->insert([
            [
                'CompanyID' => $this->companyId,
                'Title' => 'Backend Developer',
                'Description' => 'Build APIs with Laravel and PHP',
                'Requirements' => 'PHP, Laravel, MySQL',
                'Location' => 'Riyadh',
                'WorkType' => 'Full-time',
                'WorkplaceType' => 'On-site',
                'SalaryMin' => 5000,
                'SalaryMax' => 8000,
                'Status' => 'Active',
                'PostedAt' => now()->subDays(2),
            ],
            [
                'CompanyID' => $this->companyId,
                'Title' => 'Frontend Developer',
                'Description' => 'React and Vue.js specialist',
                'Requirements' => 'JavaScript, React, CSS',
                'Location' => 'Jeddah',
                'WorkType' => 'Part-time',
                'WorkplaceType' => 'Remote',
                'SalaryMin' => 3000,
                'SalaryMax' => 5000,
                'Status' => 'Active',
                'PostedAt' => now()->subDays(1),
            ],
            [
                'CompanyID' => $this->companyId,
                'Title' => 'DevOps Engineer',
                'Description' => 'CI/CD and cloud infrastructure',
                'Requirements' => 'Docker, AWS, Linux',
                'Location' => 'Riyadh',
                'WorkType' => 'Full-time',
                'WorkplaceType' => 'Hybrid',
                'SalaryMin' => 7000,
                'SalaryMax' => 12000,
                'Status' => 'Active',
                'PostedAt' => now(),
            ],
            [
                'CompanyID' => $this->companyId,
                'Title' => 'Closed Position',
                'Description' => 'This job is closed',
                'Requirements' => 'N/A',
                'Location' => 'Riyadh',
                'WorkType' => 'Full-time',
                'WorkplaceType' => 'On-site',
                'SalaryMin' => 0,
                'SalaryMax' => 0,
                'Status' => 'Closed',
                'PostedAt' => now(),
            ],
        ]);
    }

    public function test_returns_only_active_jobs(): void
    {
        $response = $this->getJson('/api/jobs');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Closed job should NOT appear
        collect($data)->each(function ($job) {
            $this->assertNotEquals('Closed', $job['Status']);
        });
    }

    public function test_search_by_keyword_in_title(): void
    {
        $response = $this->getJson('/api/jobs?search=Backend');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Backend Developer', $data[0]['Title']);
    }

    public function test_search_by_keyword_in_description(): void
    {
        $response = $this->getJson('/api/jobs?search=React');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Frontend Developer', $data[0]['Title']);
    }

    public function test_search_by_keyword_in_requirements(): void
    {
        $response = $this->getJson('/api/jobs?search=Docker');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('DevOps Engineer', $data[0]['Title']);
    }

    public function test_filter_by_location(): void
    {
        $response = $this->getJson('/api/jobs?location=Riyadh');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        collect($data)->each(function ($job) {
            $this->assertStringContainsString('Riyadh', $job['Location']);
        });
    }

    public function test_filter_by_work_type(): void
    {
        $response = $this->getJson('/api/jobs?work_type=Part-time');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Frontend Developer', $data[0]['Title']);
    }

    public function test_filter_by_workplace_type(): void
    {
        $response = $this->getJson('/api/jobs?workplace_type=Remote');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Frontend Developer', $data[0]['Title']);
    }

    public function test_filter_by_salary_range(): void
    {
        // Jobs with salary overlapping 6000-10000
        $response = $this->getJson('/api/jobs?salary_min=6000&salary_max=10000');

        $response->assertStatus(200);

        $data = $response->json('data');
        // Backend (5000-8000) overlaps, DevOps (7000-12000) overlaps
        $this->assertCount(2, $data);
    }

    public function test_filter_by_company_id(): void
    {
        $response = $this->getJson('/api/jobs?company_id='.$this->companyId);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_sort_by_latest(): void
    {
        $response = $this->getJson('/api/jobs?sort=latest');

        $response->assertStatus(200);

        $data = $response->json('data');
        // DevOps posted today should be first
        $this->assertEquals('DevOps Engineer', $data[0]['Title']);
    }

    public function test_sort_by_salary_desc(): void
    {
        $response = $this->getJson('/api/jobs?sort=salary_desc');

        $response->assertStatus(200);

        $data = $response->json('data');
        // DevOps (12000 max) should be first
        $this->assertEquals('DevOps Engineer', $data[0]['Title']);
    }

    public function test_pagination_with_per_page(): void
    {
        $response = $this->getJson('/api/jobs?per_page=2');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(3, $response->json('total'));
        $this->assertEquals(2, $response->json('per_page'));
    }

    public function test_combined_filters(): void
    {
        $response = $this->getJson('/api/jobs?search=Developer&location=Riyadh&work_type=Full-time');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Backend Developer', $data[0]['Title']);
    }

    public function test_no_results_for_invalid_filters(): void
    {
        $response = $this->getJson('/api/jobs?search=NonExistentJob12345');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(0, $data);
    }
}
