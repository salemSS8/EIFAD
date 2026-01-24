<?php

namespace App\Domain\CV\Services;

use App\Domain\CV\DTOs\CanonicalResumeDTO;
use App\Domain\CV\Models\CV;

/**
 * Canonical Resume Mapper - Transforms data to Canonical Model.
 * 
 * This mapper ensures all CV data is normalized before
 * being used by scoring, matching, or AI explanation jobs.
 */
class CanonicalResumeMapper
{
    /**
     * Map CV model to Canonical DTO.
     */
    public function fromCvModel(CV $cv): CanonicalResumeDTO
    {
        $cv->load(['skills.skill', 'experiences', 'education', 'languages.language', 'courses.course']);

        return new CanonicalResumeDTO(
            fullName: $cv->jobSeeker?->user?->FullName ?? null,
            email: $cv->jobSeeker?->user?->Email ?? null,
            phone: $cv->jobSeeker?->user?->Phone ?? null,
            location: $cv->jobSeeker?->Location ?? null,
            professionalSummary: $cv->PersonalSummary,
            skills: $this->mapSkills($cv),
            experiences: $this->mapExperiences($cv),
            education: $this->mapEducation($cv),
            certifications: $this->mapCertifications($cv),
            languages: $this->mapLanguages($cv),
            sourceType: 'database',
            extractedAt: now()->toIso8601String(),
            rawContent: $cv->ParsedContent,
        );
    }

    /**
     * Map Affinda API response to Canonical DTO.
     */
    public function fromAffindaResponse(array $affindaData): CanonicalResumeDTO
    {
        $data = $affindaData['data'] ?? $affindaData;

        return new CanonicalResumeDTO(
            fullName: $data['name']['raw'] ?? $data['name'] ?? null,
            email: $this->extractFirst($data['emails'] ?? []),
            phone: $this->extractFirst($data['phoneNumbers'] ?? []),
            location: $data['location']['formatted'] ?? null,
            linkedIn: $this->extractLinkedIn($data['websites'] ?? []),
            professionalSummary: $data['summary'] ?? $data['objective'] ?? null,
            skills: $this->mapAffindaSkills($data['skills'] ?? []),
            experiences: $this->mapAffindaExperiences($data['workExperience'] ?? []),
            education: $this->mapAffindaEducation($data['education'] ?? []),
            certifications: $this->mapAffindaCertifications($data['certifications'] ?? []),
            languages: $this->mapAffindaLanguages($data['languages'] ?? []),
            sourceType: 'affinda',
            extractedAt: now()->toIso8601String(),
        );
    }

    /**
     * Map regex-parsed data to Canonical DTO.
     */
    public function fromRegexParsedData(array $parsedData, string $rawContent = null): CanonicalResumeDTO
    {
        return CanonicalResumeDTO::fromArray([
            'personal_info' => $parsedData['personal_info'] ?? [],
            'summary' => null,
            'skills' => $parsedData['skills'] ?? [],
            'experience' => $parsedData['experience'] ?? [],
            'education' => $parsedData['education'] ?? [],
            'languages' => $parsedData['languages'] ?? [],
            'source_type' => 'regex',
            'raw_content' => $rawContent,
        ]);
    }

    // =========================================================================
    // Database Model Mappers
    // =========================================================================

    private function mapSkills(CV $cv): array
    {
        return $cv->skills->map(fn($cvSkill) => [
            'name' => $cvSkill->skill->SkillName ?? null,
            'level' => $cvSkill->SkillLevel ?? null,
            'years' => null,
        ])->toArray();
    }

    private function mapExperiences(CV $cv): array
    {
        return $cv->experiences->map(fn($exp) => [
            'job_title' => $exp->JobTitle,
            'company' => $exp->CompanyName,
            'start_date' => $exp->StartDate?->format('Y-m-d'),
            'end_date' => $exp->EndDate?->format('Y-m-d'),
            'is_current' => $exp->EndDate === null,
            'description' => $exp->Responsibilities,
            'location' => null,
        ])->toArray();
    }

    private function mapEducation(CV $cv): array
    {
        return $cv->education->map(fn($edu) => [
            'degree' => $edu->DegreeName,
            'institution' => $edu->Institution,
            'major' => $edu->Major,
            'graduation_year' => $edu->GraduationYear,
            'gpa' => null,
        ])->toArray();
    }

    private function mapCertifications(CV $cv): array
    {
        return $cv->courses->map(fn($course) => [
            'name' => $course->course->CourseName ?? null,
            'issuer' => null,
            'date' => null,
        ])->toArray();
    }

    private function mapLanguages(CV $cv): array
    {
        return $cv->languages->map(fn($cvLang) => [
            'name' => $cvLang->language->LanguageName ?? null,
            'level' => $cvLang->LanguageLevel ?? null,
        ])->toArray();
    }

    // =========================================================================
    // Affinda Response Mappers
    // =========================================================================

    private function mapAffindaSkills(array $skills): array
    {
        return array_map(fn($skill) => [
            'name' => $skill['name'] ?? $skill,
            'level' => $skill['lastUsed'] ?? null,
            'years' => $skill['numberOfMonths'] ? round($skill['numberOfMonths'] / 12, 1) : null,
        ], $skills);
    }

    private function mapAffindaExperiences(array $experiences): array
    {
        return array_map(fn($exp) => [
            'job_title' => $exp['jobTitle'] ?? null,
            'company' => $exp['organization'] ?? null,
            'start_date' => $exp['dates']['startDate'] ?? null,
            'end_date' => $exp['dates']['endDate'] ?? null,
            'is_current' => $exp['dates']['isCurrent'] ?? false,
            'description' => $exp['jobDescription'] ?? null,
            'location' => $exp['location']['formatted'] ?? null,
        ], $experiences);
    }

    private function mapAffindaEducation(array $education): array
    {
        return array_map(fn($edu) => [
            'degree' => $edu['accreditation']['education'] ?? null,
            'institution' => $edu['organization'] ?? null,
            'major' => $edu['accreditation']['inputStr'] ?? null,
            'graduation_year' => isset($edu['dates']['completionDate'])
                ? date('Y', strtotime($edu['dates']['completionDate']))
                : null,
            'gpa' => $edu['grade']['value'] ?? null,
        ], $education);
    }

    private function mapAffindaCertifications(array $certifications): array
    {
        return array_map(fn($cert) => [
            'name' => $cert['name'] ?? null,
            'issuer' => null,
            'date' => null,
        ], $certifications);
    }

    private function mapAffindaLanguages(array $languages): array
    {
        return array_map(fn($lang) => [
            'name' => $lang['name'] ?? $lang,
            'level' => null,
        ], $languages);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function extractFirst(array $items): ?string
    {
        return $items[0] ?? null;
    }

    private function extractLinkedIn(array $websites): ?string
    {
        foreach ($websites as $website) {
            if (str_contains(strtolower($website), 'linkedin')) {
                return $website;
            }
        }
        return null;
    }
}
