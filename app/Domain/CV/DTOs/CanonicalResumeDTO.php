<?php

namespace App\Domain\CV\DTOs;

/**
 * Canonical Resume DTO - Normalized CV Data Structure.
 * 
 * This DTO represents the standardized format for CV data
 * after extraction from any source (Affinda, Regex, Database).
 * 
 * All downstream jobs MUST use this DTO, not raw arrays.
 */
readonly class CanonicalResumeDTO
{
    public function __construct(
        // Personal Information
        public ?string $fullName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $location = null,
        public ?string $linkedIn = null,
        public ?string $website = null,

        // Summary
        public ?string $professionalSummary = null,

        // Collections
        public array $skills = [],
        public array $experiences = [],
        public array $education = [],
        public array $certifications = [],
        public array $languages = [],
        public array $projects = [],

        // Metadata
        public ?string $sourceType = null, // 'affinda', 'regex', 'database', 'manual'
        public ?string $extractedAt = null,
        public ?string $rawContent = null,
    ) {}

    /**
     * Create from array (for backward compatibility).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fullName: $data['personal_info']['name'] ?? $data['full_name'] ?? null,
            email: $data['personal_info']['email'] ?? $data['email'] ?? null,
            phone: $data['personal_info']['phone'] ?? $data['phone'] ?? null,
            location: $data['personal_info']['location'] ?? $data['location'] ?? null,
            linkedIn: $data['personal_info']['linkedin'] ?? $data['linkedin'] ?? null,
            website: $data['personal_info']['website'] ?? $data['website'] ?? null,
            professionalSummary: $data['summary'] ?? $data['professional_summary'] ?? null,
            skills: self::normalizeSkills($data['skills'] ?? []),
            experiences: self::normalizeExperiences($data['experience'] ?? $data['experiences'] ?? []),
            education: self::normalizeEducation($data['education'] ?? []),
            certifications: $data['certifications'] ?? [],
            languages: self::normalizeLanguages($data['languages'] ?? []),
            projects: $data['projects'] ?? [],
            sourceType: $data['source_type'] ?? 'unknown',
            extractedAt: $data['extracted_at'] ?? now()->toIso8601String(),
            rawContent: $data['raw_content'] ?? null,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'personal_info' => [
                'name' => $this->fullName,
                'email' => $this->email,
                'phone' => $this->phone,
                'location' => $this->location,
                'linkedin' => $this->linkedIn,
                'website' => $this->website,
            ],
            'summary' => $this->professionalSummary,
            'skills' => $this->skills,
            'experiences' => $this->experiences,
            'education' => $this->education,
            'certifications' => $this->certifications,
            'languages' => $this->languages,
            'projects' => $this->projects,
            'source_type' => $this->sourceType,
            'extracted_at' => $this->extractedAt,
        ];
    }

    /**
     * Normalize skills to standard format.
     */
    private static function normalizeSkills(array $skills): array
    {
        return array_map(function ($skill) {
            if (is_string($skill)) {
                return [
                    'name' => $skill,
                    'level' => null,
                    'years' => null,
                ];
            }
            return [
                'name' => $skill['name'] ?? $skill['skill_name'] ?? $skill,
                'level' => $skill['level'] ?? $skill['skill_level'] ?? null,
                'years' => $skill['years'] ?? $skill['years_of_experience'] ?? null,
            ];
        }, $skills);
    }

    /**
     * Normalize experiences to standard format.
     */
    private static function normalizeExperiences(array $experiences): array
    {
        return array_map(function ($exp) {
            return [
                'job_title' => $exp['title'] ?? $exp['job_title'] ?? null,
                'company' => $exp['company'] ?? $exp['company_name'] ?? null,
                'start_date' => $exp['start_date'] ?? null,
                'end_date' => $exp['end_date'] ?? null,
                'is_current' => $exp['is_current'] ?? ($exp['end_date'] === null),
                'description' => $exp['description'] ?? $exp['responsibilities'] ?? null,
                'location' => $exp['location'] ?? null,
            ];
        }, $experiences);
    }

    /**
     * Normalize education to standard format.
     */
    private static function normalizeEducation(array $education): array
    {
        return array_map(function ($edu) {
            return [
                'degree' => $edu['degree'] ?? $edu['degree_name'] ?? null,
                'institution' => $edu['institution'] ?? $edu['school'] ?? null,
                'major' => $edu['major'] ?? $edu['field_of_study'] ?? null,
                'graduation_year' => $edu['year'] ?? $edu['graduation_year'] ?? null,
                'gpa' => $edu['gpa'] ?? null,
            ];
        }, $education);
    }

    /**
     * Normalize languages to standard format.
     */
    private static function normalizeLanguages(array $languages): array
    {
        return array_map(function ($lang) {
            if (is_string($lang)) {
                return ['name' => $lang, 'level' => null];
            }
            return [
                'name' => $lang['name'] ?? $lang['language'] ?? null,
                'level' => $lang['level'] ?? $lang['proficiency'] ?? null,
            ];
        }, $languages);
    }

    /**
     * Check if the resume has minimum required data.
     */
    public function isValid(): bool
    {
        return !empty($this->fullName) || !empty($this->email) || !empty($this->skills);
    }

    /**
     * Get total years of experience.
     */
    public function getTotalExperienceYears(): float
    {
        $totalYears = 0;

        foreach ($this->experiences as $exp) {
            $startDate = strtotime($exp['start_date'] ?? '');
            $endDate = $exp['end_date'] ? strtotime($exp['end_date']) : time();

            if ($startDate) {
                $years = ($endDate - $startDate) / (365 * 24 * 60 * 60);
                $totalYears += max(0, $years);
            }
        }

        return round($totalYears, 1);
    }

    /**
     * Get skill names as flat array.
     */
    public function getSkillNames(): array
    {
        return array_map(fn($s) => $s['name'] ?? '', $this->skills);
    }
}
