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
            fullName: $this->getString($data['candidateName'] ?? $data['name'] ?? null),
            email: $this->extractFirst($data['email'] ?? $data['emails'] ?? []),
            phone: $this->extractFirst($data['phoneNumber'] ?? $data['phoneNumbers'] ?? []),
            location: $data['location']['parsed']['formatted'] ?? $data['location']['formatted'] ?? null,
            linkedIn: $this->extractLinkedIn($data['website'] ?? $data['websites'] ?? []),
            professionalSummary: $this->getString($data['summary'] ?? $data['objective'] ?? null),
            skills: $this->mapAffindaSkills($data['skill'] ?? $data['skills'] ?? []),
            experiences: $this->mapAffindaExperiences($data['workExperience'] ?? []),
            education: $this->mapAffindaEducation($data['education'] ?? []),
            certifications: $this->mapAffindaCertifications($data['certifications'] ?? []),
            languages: $this->mapAffindaLanguages($data['language'] ?? $data['languages'] ?? []),
            sourceType: 'affinda',
            extractedAt: now()->toIso8601String(),
        );
    }

    /**
     * Map regex-parsed data to Canonical DTO.
     */
    public function fromRegexParsedData(array $parsedData, ?string $rawContent = null): CanonicalResumeDTO
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
        return $cv->skills->map(fn ($cvSkill) => [
            'name' => $cvSkill->skill->SkillName ?? null,
            'level' => $cvSkill->SkillLevel ?? null,
            'years' => null,
        ])->toArray();
    }

    private function mapExperiences(CV $cv): array
    {
        return $cv->experiences->map(fn ($exp) => [
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
        return $cv->education->map(fn ($edu) => [
            'degree' => $edu->DegreeName,
            'institution' => $edu->Institution,
            'major' => $edu->Major,
            'graduation_year' => $edu->GraduationYear,
            'gpa' => null,
        ])->toArray();
    }

    private function mapCertifications(CV $cv): array
    {
        return $cv->courses->map(fn ($course) => [
            'name' => $course->course->CourseName ?? null,
            'issuer' => null,
            'date' => null,
        ])->toArray();
    }

    private function mapLanguages(CV $cv): array
    {
        return $cv->languages->map(fn ($cvLang) => [
            'name' => $cvLang->language->LanguageName ?? null,
            'level' => $cvLang->LanguageLevel ?? null,
        ])->toArray();
    }

    // =========================================================================
    // Affinda Response Mappers
    // =========================================================================

    private function mapAffindaSkills(array $skills): array
    {
        return array_map(function ($skill) {
            $parsed = $skill['parsed'] ?? $skill;

            return [
                'name' => $parsed['name'] ?? $skill['raw'] ?? $skill,
                'level' => $parsed['lastUsed'] ?? null,
                'years' => ($parsed['numberOfMonths'] ?? null) ? round($parsed['numberOfMonths'] / 12, 1) : null,
            ];
        }, $skills);
    }

    private function mapAffindaExperiences(array $experiences): array
    {
        return array_map(function ($exp) {
            $p = $exp['parsed'] ?? $exp;

            return [
                'job_title' => $this->getString($p['workExperienceJobTitle'] ?? $p['jobTitle'] ?? null),
                'company' => $this->getString($p['workExperienceOrganization'] ?? $p['organization'] ?? null),
                'start_date' => $p['workExperienceDates']['parsed']['start']['date'] ?? $p['dates']['startDate'] ?? null,
                'end_date' => $p['workExperienceDates']['parsed']['end']['date'] ?? $p['dates']['endDate'] ?? null,
                'is_current' => $p['workExperienceDates']['parsed']['end']['isCurrent'] ?? $p['dates']['isCurrent'] ?? false,
                'description' => $this->getString($p['workExperienceDescription'] ?? $p['jobDescription'] ?? null),
                'location' => $p['workExperienceLocation']['parsed']['formatted'] ?? $p['location']['formatted'] ?? null,
            ];
        }, $experiences);
    }

    private function mapAffindaEducation(array $education): array
    {
        return array_map(function ($edu) {
            $p = $edu['parsed'] ?? $edu;

            return [
                'degree' => $this->getString($p['educationLevel'] ?? $p['accreditation']['education'] ?? null),
                'institution' => $this->getString($p['educationOrganization'] ?? $p['organization'] ?? null),
                'major' => $this->extractFirst($p['educationMajor'] ?? []) ?? $this->getString($p['accreditation']['inputStr'] ?? null),
                'graduation_year' => $p['educationDates']['parsed']['end']['year'] ?? (isset($p['dates']['completionDate'])
                    ? date('Y', strtotime($p['dates']['completionDate']))
                    : null),
                'gpa' => $p['educationGrade']['value'] ?? $p['grade']['value'] ?? null,
            ];
        }, $education);
    }

    private function mapAffindaCertifications(array $certifications): array
    {
        return array_map(fn ($cert) => [
            'name' => $cert['name'] ?? null,
            'issuer' => null,
            'date' => null,
        ], $certifications);
    }

    private function mapAffindaLanguages(array $languages): array
    {
        return array_map(function ($lang) {
            $p = $lang['parsed'] ?? $lang;

            return [
                'name' => $this->getString($p['languageName'] ?? $p['name'] ?? $lang),
                'level' => $this->getString($p['languageProficiency'] ?? null),
            ];
        }, $languages);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function extractFirst(array $items): ?string
    {
        $first = $items[0] ?? null;

        return $this->getString($first);
    }

    private function extractLinkedIn(array $websites): ?string
    {
        foreach ($websites as $website) {
            $url = is_array($website) ? ($website['parsed']['url'] ?? $website['raw'] ?? '') : $website;
            if (str_contains(strtolower($url), 'linkedin')) {
                return $url;
            }
        }

        return null;
    }

    private function getString(mixed $field): ?string
    {
        if ($field === null) {
            return null;
        }

        if (is_string($field)) {
            return $field;
        }

        if (is_array($field)) {
            // Try parsed first if it's a string
            if (isset($field['parsed']) && is_string($field['parsed'])) {
                return $field['parsed'];
            }

            // Fallback to raw if it's a string
            if (isset($field['raw']) && is_string($field['raw'])) {
                return $field['raw'];
            }
        }

        return null;
    }
}
