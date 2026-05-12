<?php

namespace Tests\Feature\Api\Admin;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVCertification;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use App\Jobs\AnalyzeCertificateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'Admin']);
        Role::create(['RoleName' => 'JobSeeker']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('RoleName', 'Admin')->first());
    }

    private function createCertification(string $status = 'pending', ?float $aiScore = null): CVCertification
    {
        $seeker = User::factory()->create();
        $seeker->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $seeker->UserID]);

        $cv = CV::create([
            'JobSeekerID' => $seeker->UserID,
            'Title' => 'Test CV',
            'CreatedAt' => now(),
        ]);

        return CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => 'Test Certificate',
            'IssuingOrganization' => 'Test Org',
            'IsVerified' => false,
            'VerificationStatus' => $status,
            'AiConfidenceScore' => $aiScore,
        ]);
    }

    public function test_admin_can_list_all_certificates(): void
    {
        $this->createCertification('pending');
        $this->createCertification('ai_reviewed', 85.0);
        $this->createCertification('verified');
        $this->createCertification('rejected');

        $response = $this->actingAs($this->admin)->getJson('/api/admin/certificates');

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('total'));
    }

    public function test_admin_can_filter_certificates_by_status(): void
    {
        $this->createCertification('pending');
        $this->createCertification('ai_reviewed', 90.0);
        $this->createCertification('verified');

        $response = $this->actingAs($this->admin)->getJson('/api/admin/certificates?status=pending');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_admin_can_search_certificates_by_name(): void
    {
        $this->createCertification('pending');

        $response = $this->actingAs($this->admin)->getJson('/api/admin/certificates?search=Test');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_admin_can_view_certificate_details(): void
    {
        $cert = $this->createCertification('ai_reviewed', 75.0);

        $response = $this->actingAs($this->admin)->getJson("/api/admin/certificates/{$cert->CertificationID}");

        $response->assertStatus(200)
            ->assertJsonPath('data.CertificationID', $cert->CertificationID)
            ->assertJsonPath('data.CertificateName', 'Test Certificate');
    }

    public function test_admin_can_verify_certificate(): void
    {
        $cert = $this->createCertification('ai_reviewed', 90.0);

        $response = $this->actingAs($this->admin)->putJson("/api/admin/certificates/{$cert->CertificationID}/verify", [
            'notes' => 'تم التحقق يدوياً',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.VerificationStatus', 'verified')
            ->assertJsonPath('data.IsVerified', true);

        $this->assertDatabaseHas('cv_certifications', [
            'CertificationID' => $cert->CertificationID,
            'VerificationStatus' => 'verified',
            'VerifiedBy' => $this->admin->UserID,
        ]);
    }

    public function test_admin_can_reject_certificate(): void
    {
        $cert = $this->createCertification('ai_reviewed', 20.0);

        $response = $this->actingAs($this->admin)->putJson("/api/admin/certificates/{$cert->CertificationID}/reject", [
            'reason' => 'شهادة مزورة',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.VerificationStatus', 'rejected')
            ->assertJsonPath('data.IsVerified', false);

        $this->assertDatabaseHas('cv_certifications', [
            'CertificationID' => $cert->CertificationID,
            'VerificationStatus' => 'rejected',
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        $cert = $this->createCertification('ai_reviewed');

        $response = $this->actingAs($this->admin)->putJson("/api/admin/certificates/{$cert->CertificationID}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_admin_can_reanalyze_certificate(): void
    {
        Queue::fake();

        $cert = $this->createCertification('ai_reviewed', 50.0);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/certificates/{$cert->CertificationID}/reanalyze");

        $response->assertStatus(200)
            ->assertJsonPath('data.VerificationStatus', 'pending');

        Queue::assertPushed(AnalyzeCertificateJob::class);
    }

    public function test_non_admin_cannot_access_certificates(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/admin/certificates');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_certificates(): void
    {
        $response = $this->getJson('/api/admin/certificates');
        $response->assertStatus(401);
    }

    public function test_show_returns_404_for_nonexistent_certificate(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/certificates/99999');
        $response->assertStatus(404);
    }
}
