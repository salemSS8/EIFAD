<?php

namespace Tests\Feature\Api\Profile;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVCertification;
use App\Domain\CV\Models\CVCustomSection;
use App\Domain\CV\Models\CVLanguage;
use App\Domain\CV\Models\CVSkill;
use App\Domain\CV\Models\Education;
use App\Domain\CV\Models\Experience;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CVTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $jobSeekerID;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['RoleName' => 'JobSeeker']);

        $this->user = User::factory()->create();
        $this->user->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $this->user->UserID]);
        $this->jobSeekerID = $this->user->UserID;
    }

    /**
     * Helper to create a CV for the test user.
     */
    private function createCV(array $overrides = []): CV
    {
        return CV::create(array_merge([
            'JobSeekerID' => $this->jobSeekerID,
            'Title' => 'Test CV',
            'PersonalSummary' => 'Test summary',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ], $overrides));
    }

    // ==========================================
    // CV CRUD
    // ==========================================

    public function test_job_seeker_can_list_cvs(): void
    {
        $this->createCV(['Title' => 'CV One']);
        $this->createCV(['Title' => 'CV Two']);

        $response = $this->actingAs($this->user)->getJson('/api/cvs');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_job_seeker_can_create_cv(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cvs', [
            'title' => 'My New CV',
            'personal_summary' => 'A great developer',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['Title' => 'My New CV']);

        $this->assertDatabaseHas('cv', [
            'JobSeekerID' => $this->jobSeekerID,
            'Title' => 'My New CV',
        ]);
    }

    public function test_create_cv_requires_title(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/cvs', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_job_seeker_can_show_cv_with_relations(): void
    {
        $cv = $this->createCV();

        // Add related data
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'PHP']);
        CVSkill::create(['CVID' => $cv->CVID, 'SkillID' => $skillId, 'SkillLevel' => 'Advanced']);

        $langId = DB::table('language')->insertGetId(['LanguageName' => 'English']);
        CVLanguage::create(['CVID' => $cv->CVID, 'LanguageID' => $langId, 'LanguageLevel' => 'Native']);

        Education::create(['CVID' => $cv->CVID, 'Institution' => 'MIT', 'DegreeName' => 'BSc']);
        Experience::create(['CVID' => $cv->CVID, 'JobTitle' => 'Dev', 'CompanyName' => 'Google', 'StartDate' => '2020-01-01']);
        CVCertification::create(['CVID' => $cv->CVID, 'CertificateName' => 'PMP']);
        CVCustomSection::create(['CVID' => $cv->CVID, 'SectionType' => 'Award', 'Title' => 'Best Employee']);

        $response = $this->actingAs($this->user)->getJson('/api/cvs/'.$cv->CVID);

        $response->assertStatus(200)
            ->assertJsonPath('data.Title', 'Test CV')
            ->assertJsonPath('data.skills.0.SkillLevel', 'Advanced')
            ->assertJsonPath('data.languages.0.LanguageLevel', 'Native')
            ->assertJsonPath('data.education.0.Institution', 'MIT')
            ->assertJsonPath('data.experiences.0.JobTitle', 'Dev')
            ->assertJsonPath('data.certifications.0.CertificateName', 'PMP')
            ->assertJsonPath('data.custom_sections.0.Title', 'Best Employee');
    }

    public function test_job_seeker_can_update_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->putJson('/api/cvs/'.$cv->CVID, [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['Title' => 'Updated Title']);
    }

    public function test_job_seeker_can_delete_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->deleteJson('/api/cvs/'.$cv->CVID);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cv', ['CVID' => $cv->CVID]);
    }

    public function test_cannot_access_other_users_cv(): void
    {
        $otherUser = User::factory()->create();
        DB::table('jobseekerprofile')->insert(['JobSeekerID' => $otherUser->UserID]);
        $otherCv = CV::create([
            'JobSeekerID' => $otherUser->UserID,
            'Title' => 'Other CV',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/cvs/'.$otherCv->CVID);

        $response->assertStatus(404);
    }

    // ==========================================
    // No Profile Scenario
    // ==========================================

    public function test_user_without_profile_gets_404(): void
    {
        $noProfileUser = User::factory()->create();
        $noProfileUser->roles()->attach(Role::where('RoleName', 'JobSeeker')->first());

        $response = $this->actingAs($noProfileUser)->getJson('/api/cvs');
        $response->assertStatus(404);
    }

    // ==========================================
    // CV Skills
    // ==========================================

    public function test_can_add_skill_to_cv(): void
    {
        $cv = $this->createCV();
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'Laravel']);

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/skills", [
            'skill_id' => $skillId,
            'skill_level' => 'Advanced',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['SkillLevel' => 'Advanced']);

        $this->assertDatabaseHas('cvskill', [
            'CVID' => $cv->CVID,
            'SkillID' => $skillId,
        ]);
    }

    public function test_cannot_add_duplicate_skill_to_cv(): void
    {
        $cv = $this->createCV();
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'PHP']);
        CVSkill::create(['CVID' => $cv->CVID, 'SkillID' => $skillId, 'SkillLevel' => 'Beginner']);

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/skills", [
            'skill_id' => $skillId,
            'skill_level' => 'Advanced',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['skill_id']);
    }

    public function test_can_update_skill_in_cv(): void
    {
        $cv = $this->createCV();
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'PHP']);
        CVSkill::create(['CVID' => $cv->CVID, 'SkillID' => $skillId, 'SkillLevel' => 'Beginner']);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/skills/{$skillId}", [
            'skill_level' => 'Expert',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['SkillLevel' => 'Expert']);
    }

    public function test_can_remove_skill_from_cv(): void
    {
        $cv = $this->createCV();
        $skillId = DB::table('skill')->insertGetId(['SkillName' => 'PHP']);
        CVSkill::create(['CVID' => $cv->CVID, 'SkillID' => $skillId, 'SkillLevel' => 'Beginner']);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/skills/{$skillId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cvskill', ['CVID' => $cv->CVID, 'SkillID' => $skillId]);
    }

    // ==========================================
    // CV Education
    // ==========================================

    public function test_can_add_education_to_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/education", [
            'institution' => 'Harvard University',
            'degree_name' => 'BSc Computer Science',
            'major' => 'CS',
            'graduation_year' => 2024,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['Institution' => 'Harvard University']);
    }

    public function test_can_update_education_in_cv(): void
    {
        $cv = $this->createCV();
        $edu = Education::create([
            'CVID' => $cv->CVID,
            'Institution' => 'Old University',
            'DegreeName' => 'BSc',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/education/{$edu->EducationID}", [
            'institution' => 'New University',
            'graduation_year' => 2025,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['Institution' => 'New University']);
    }

    public function test_can_remove_education_from_cv(): void
    {
        $cv = $this->createCV();
        $edu = Education::create([
            'CVID' => $cv->CVID,
            'Institution' => 'University',
            'DegreeName' => 'BSc',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/education/{$edu->EducationID}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('education', ['EducationID' => $edu->EducationID]);
    }

    public function test_remove_nonexistent_education_returns_404(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/education/99999");

        $response->assertStatus(404);
    }

    // ==========================================
    // CV Experience
    // ==========================================

    public function test_can_add_experience_to_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/experience", [
            'job_title' => 'Senior Developer',
            'company_name' => 'Google',
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'responsibilities' => 'Building APIs',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['JobTitle' => 'Senior Developer']);
    }

    public function test_can_update_experience_in_cv(): void
    {
        $cv = $this->createCV();
        $exp = Experience::create([
            'CVID' => $cv->CVID,
            'JobTitle' => 'Junior Dev',
            'CompanyName' => 'Startup',
            'StartDate' => '2019-01-01',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/experience/{$exp->ExperienceID}", [
            'job_title' => 'Senior Dev',
            'company_name' => 'Big Corp',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['JobTitle' => 'Senior Dev']);
    }

    public function test_can_remove_experience_from_cv(): void
    {
        $cv = $this->createCV();
        $exp = Experience::create([
            'CVID' => $cv->CVID,
            'JobTitle' => 'Dev',
            'CompanyName' => 'Company',
            'StartDate' => '2020-01-01',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/experience/{$exp->ExperienceID}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('experience', ['ExperienceID' => $exp->ExperienceID]);
    }

    public function test_remove_nonexistent_experience_returns_404(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/experience/99999");

        $response->assertStatus(404);
    }

    // ==========================================
    // CV Languages
    // ==========================================

    public function test_can_add_language_to_cv(): void
    {
        $cv = $this->createCV();
        $langId = DB::table('language')->insertGetId(['LanguageName' => 'Arabic']);

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/languages", [
            'language_id' => $langId,
            'language_level' => 'Native',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['LanguageLevel' => 'Native']);
    }

    public function test_cannot_add_duplicate_language_to_cv(): void
    {
        $cv = $this->createCV();
        $langId = DB::table('language')->insertGetId(['LanguageName' => 'Arabic']);
        CVLanguage::create(['CVID' => $cv->CVID, 'LanguageID' => $langId, 'LanguageLevel' => 'Native']);

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/languages", [
            'language_id' => $langId,
            'language_level' => 'Fluent',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_id']);
    }

    public function test_can_update_language_in_cv(): void
    {
        $cv = $this->createCV();
        $langId = DB::table('language')->insertGetId(['LanguageName' => 'English']);
        CVLanguage::create(['CVID' => $cv->CVID, 'LanguageID' => $langId, 'LanguageLevel' => 'Intermediate']);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/languages/{$langId}", [
            'language_level' => 'Advanced',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['LanguageLevel' => 'Advanced']);
    }

    public function test_can_remove_language_from_cv(): void
    {
        $cv = $this->createCV();
        $langId = DB::table('language')->insertGetId(['LanguageName' => 'French']);
        CVLanguage::create(['CVID' => $cv->CVID, 'LanguageID' => $langId, 'LanguageLevel' => 'Beginner']);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/languages/{$langId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cvlanguage', ['CVID' => $cv->CVID, 'LanguageID' => $langId]);
    }

    // ==========================================
    // CV Certifications
    // ==========================================

    public function test_can_add_certification_to_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/certifications", [
            'certificate_name' => 'AWS Solutions Architect',
            'issuing_organization' => 'Amazon',
            'is_verified' => true,
            'issue_date' => '2024-06-15',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['CertificateName' => 'AWS Solutions Architect']);

        $this->assertDatabaseHas('cv_certifications', [
            'CVID' => $cv->CVID,
            'CertificateName' => 'AWS Solutions Architect',
        ]);

        $cert = CVCertification::where('CVID', $cv->CVID)->first();
        $this->assertEquals('2024-06-15', $cert->IssueDate->format('Y-m-d'));
    }

    public function test_can_add_certification_without_issue_date(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/certifications", [
            'certificate_name' => 'PMP',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('cv_certifications', [
            'CVID' => $cv->CVID,
            'CertificateName' => 'PMP',
            'IssueDate' => null,
        ]);
    }

    public function test_can_update_certification_in_cv(): void
    {
        $cv = $this->createCV();
        $cert = CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => 'Old Cert',
            'IsVerified' => false,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/certifications/{$cert->CertificationID}", [
            'certificate_name' => 'New Cert',
            'is_verified' => true,
            'issue_date' => '2025-01-01',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['CertificateName' => 'New Cert']);

        $cert->refresh();
        $this->assertEquals('2025-01-01', $cert->IssueDate->format('Y-m-d'));
    }

    public function test_can_remove_certification_from_cv(): void
    {
        $cv = $this->createCV();
        $cert = CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => 'PMP',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/certifications/{$cert->CertificationID}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cv_certifications', ['CertificationID' => $cert->CertificationID]);
    }

    // ==========================================
    // CV Custom Sections
    // ==========================================

    public function test_can_add_custom_section_to_cv(): void
    {
        $cv = $this->createCV();

        $response = $this->actingAs($this->user)->postJson("/api/cvs/{$cv->CVID}/custom-sections", [
            'SectionType' => 'Volunteering',
            'Title' => 'Red Cross',
            'Description' => 'Helped people',
            'StartDate' => '2022-01-01',
            'EndDate' => '2022-12-31',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['Title' => 'Red Cross']);
    }

    public function test_can_update_custom_section_in_cv(): void
    {
        $cv = $this->createCV();
        $section = CVCustomSection::create([
            'CVID' => $cv->CVID,
            'SectionType' => 'Award',
            'Title' => 'Old Award',
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/cvs/{$cv->CVID}/custom-sections/{$section->CustomSectionID}", [
            'Title' => 'Best Developer 2025',
            'Description' => 'Awarded for excellence',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['Title' => 'Best Developer 2025']);
    }

    public function test_can_remove_custom_section_from_cv(): void
    {
        $cv = $this->createCV();
        $section = CVCustomSection::create([
            'CVID' => $cv->CVID,
            'SectionType' => 'Project',
            'Title' => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/cvs/{$cv->CVID}/custom-sections/{$section->CustomSectionID}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cv_custom_sections', ['CustomSectionID' => $section->CustomSectionID]);
    }
}
