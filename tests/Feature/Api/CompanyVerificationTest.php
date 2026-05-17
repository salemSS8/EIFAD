<?php

namespace Tests\Feature\Api;

use App\Domain\Company\Models\CompanyProfile;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::firstOrCreate(['RoleID' => 1, 'RoleName' => 'Admin']);
        Role::firstOrCreate(['RoleID' => 2, 'RoleName' => 'Employer']);
    }

    public function test_company_can_upload_verification_documents(): void
    {
        $user = User::factory()->create();
        $company = CompanyProfile::create(['CompanyID' => $user->UserID, 'CompanyName' => 'Verify Me']);

        $response = $this->actingAs($user)->postJson('/api/employer/verify/documents', [
            'documents' => [
                UploadedFile::fake()->create('id_card.pdf', 500, 'application/pdf'),
                UploadedFile::fake()->create('license.jpg', 500, 'image/jpeg'),
            ],
        ]);

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals('Pending', $company->VerificationStatus);
        $this->assertCount(2, $company->VerificationDocuments);

        Storage::disk('local')->assertExists($company->VerificationDocuments[0]['path']);
    }

    public function test_admin_can_verify_company(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('RoleName', 'Admin')->first());

        $employer = User::factory()->create();
        $company = CompanyProfile::create([
            'CompanyID' => $employer->UserID,
            'CompanyName' => 'Test',
            'VerificationStatus' => 'Pending',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/admin/companies/{$company->CompanyID}/verify", [
            'status' => 'Verified',
        ]);

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals('Verified', $company->VerificationStatus);
        $this->assertTrue($company->IsCompanyVerified);
    }

    public function test_company_can_upload_verification_documents_via_cloudinary_urls(): void
    {
        $user = User::factory()->create();
        $company = CompanyProfile::create(['CompanyID' => $user->UserID, 'CompanyName' => 'Verify Me']);

        $response = $this->actingAs($user)->postJson('/api/employer/verify/documents', [
            'document_urls' => [
                0 => 'https://res.cloudinary.com/demo/image/upload/v12345/register.pdf',
                1 => 'https://res.cloudinary.com/demo/image/upload/v12345/tax.pdf',
            ],
            'document_names' => [
                0 => 'commercial_register.pdf',
                1 => 'tax_card.pdf',
            ],
        ]);

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals('Pending', $company->VerificationStatus);
        $this->assertCount(2, $company->VerificationDocuments);
        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/v12345/register.pdf', $company->VerificationDocuments[0]['path']);
        $this->assertEquals('commercial_register.pdf', $company->VerificationDocuments[0]['name']);
    }
}
