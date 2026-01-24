<?php

namespace App\Domain\CV\Jobs;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVSkill;
use App\Domain\CV\Models\Education;
use App\Domain\CV\Models\Experience;
use App\Domain\CV\Models\CVLanguage;
use App\Domain\CV\DTOs\CanonicalResumeDTO;
use App\Domain\CV\Services\AffindaResumeParser;
use App\Domain\CV\Services\CanonicalResumeMapper;
use App\Domain\Skill\Models\Skill;
use App\Domain\Skill\Models\Language;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Job: Parse CV and extract structured data.
 * 
 * Parsing Pipeline (as per documentation):
 * 1. Try Affinda Resume Parser (PRIMARY)
 * 2. Fallback to Regex-based parsing if Affinda fails
 * 3. Output: CanonicalResumeDTO (normalized model)
 * 
 * NO AI is used in this job - pure extraction logic.
 */
class ParseCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public CV $cv
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        AffindaResumeParser $affindaParser,
        CanonicalResumeMapper $mapper
    ): array {
        try {
            $filePath = $this->getFullFilePath();
            $canonicalResume = null;
            $sourceType = 'unknown';

            // Step 1: Try Affinda Parser (PRIMARY - as per documentation)
            if ($affindaParser->isAvailable() && $filePath && file_exists($filePath)) {
                Log::info('ParseCvJob: Attempting Affinda parsing', ['cv_id' => $this->cv->CVID]);

                $canonicalResume = $affindaParser->parseFile($filePath);

                if ($canonicalResume) {
                    $sourceType = 'affinda';
                    Log::info('ParseCvJob: Affinda parsing successful', ['cv_id' => $this->cv->CVID]);
                }
            }

            // Step 2: Fallback to Regex-based parsing
            if (!$canonicalResume) {
                Log::info('ParseCvJob: Falling back to regex parsing', ['cv_id' => $this->cv->CVID]);

                $rawContent = $this->extractRawContent();

                if (!empty($rawContent)) {
                    $parsedData = $this->parseWithRegex($rawContent);
                    $canonicalResume = $mapper->fromRegexParsedData($parsedData, $rawContent);
                    $sourceType = 'regex';
                }
            }

            // Step 3: Fallback to database existing data
            if (!$canonicalResume || !$canonicalResume->isValid()) {
                Log::info('ParseCvJob: Using database data', ['cv_id' => $this->cv->CVID]);
                $canonicalResume = $mapper->fromCvModel($this->cv);
                $sourceType = 'database';
            }

            // Persist extracted data
            $this->persistCanonicalData($canonicalResume);

            // Update CV with raw content
            $this->cv->update([
                'ParsedContent' => $canonicalResume->rawContent,
                'ParsingMethod' => $sourceType,
                'ParsedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            Log::info('ParseCvJob: CV parsed successfully', [
                'cv_id' => $this->cv->CVID,
                'source_type' => $sourceType,
                'skills_count' => count($canonicalResume->skills),
            ]);

            return [
                'success' => true,
                'source_type' => $sourceType,
                'canonical_resume' => $canonicalResume->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('ParseCvJob failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get full file path for CV.
     */
    private function getFullFilePath(): ?string
    {
        $filePath = $this->cv->FilePath;

        if (empty($filePath)) {
            return null;
        }

        return Storage::disk('local')->path($filePath);
    }

    /**
     * Extract raw text content from CV file.
     */
    private function extractRawContent(): string
    {
        $filePath = $this->getFullFilePath();

        if (!$filePath || !file_exists($filePath)) {
            return $this->cv->PersonalSummary ?? '';
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractFromPdf($filePath),
            'docx' => $this->extractFromDocx($filePath),
            'txt' => file_get_contents($filePath),
            default => '',
        };
    }

    /**
     * Extract text from PDF file.
     */
    private function extractFromPdf(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (\Exception $e) {
            Log::warning('PDF parsing failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from DOCX file.
     */
    private function extractFromDocx(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $content = $zip->getFromName('word/document.xml');
                $zip->close();
                return strip_tags($content);
            }
        } catch (\Exception $e) {
            Log::warning('DOCX parsing failed', ['error' => $e->getMessage()]);
        }
        return '';
    }

    /**
     * Parse structured data from raw text using regex patterns.
     */
    private function parseWithRegex(string $content): array
    {
        return [
            'personal_info' => $this->extractPersonalInfo($content),
            'skills' => $this->extractSkills($content),
            'experience' => $this->extractExperience($content),
            'education' => $this->extractEducation($content),
            'languages' => $this->extractLanguages($content),
        ];
    }

    /**
     * Extract personal information using regex patterns.
     */
    private function extractPersonalInfo(string $content): array
    {
        $info = ['name' => null, 'email' => null, 'phone' => null, 'location' => null];

        // Email pattern
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches)) {
            $info['email'] = $matches[0];
        }

        // Phone patterns
        if (preg_match('/\+?\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}/', $content, $matches)) {
            $info['phone'] = $matches[0];
        }

        // Name - usually first line
        $lines = array_filter(explode("\n", $content));
        if (!empty($lines)) {
            $firstLine = trim(reset($lines));
            if (strlen($firstLine) < 50 && !str_contains($firstLine, '@')) {
                $info['name'] = $firstLine;
            }
        }

        return $info;
    }

    /**
     * Extract skills from content.
     */
    private function extractSkills(string $content): array
    {
        $skills = [];
        $content = strtolower($content);

        $commonSkills = [
            'php',
            'javascript',
            'python',
            'java',
            'c++',
            'c#',
            'ruby',
            'go',
            'swift',
            'react',
            'vue',
            'angular',
            'node.js',
            'laravel',
            'django',
            'spring',
            'mysql',
            'postgresql',
            'mongodb',
            'redis',
            'sql',
            'html',
            'css',
            'typescript',
            'git',
            'docker',
            'kubernetes',
            'aws',
            'flutter',
            'kotlin',
            'machine learning',
            'ai',
            'data science',
        ];

        foreach ($commonSkills as $skill) {
            if (str_contains($content, $skill)) {
                $skills[] = ucfirst($skill);
            }
        }

        return array_unique($skills);
    }

    /**
     * Extract experience entries.
     */
    private function extractExperience(string $content): array
    {
        // Basic pattern matching for date ranges
        $experiences = [];
        $datePattern = '/(\d{4})\s*[-–]\s*(\d{4}|present|current)/i';

        preg_match_all($datePattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $experiences[] = [
                'date_range' => $match[0],
                'start_date' => $match[1] . '-01-01',
                'end_date' => is_numeric($match[2]) ? $match[2] . '-12-31' : null,
            ];
        }

        return $experiences;
    }

    /**
     * Extract education entries.
     */
    private function extractEducation(array $content = null): array
    {
        $education = [];
        $degrees = ['phd', 'doctorate', 'master', 'mba', 'bachelor', 'diploma'];

        $contentStr = is_array($content) ? '' : ($content ?? '');
        $contentLower = strtolower($contentStr);

        foreach ($degrees as $degree) {
            if (str_contains($contentLower, $degree)) {
                $education[] = ['degree' => ucfirst($degree)];
            }
        }

        return $education;
    }

    /**
     * Extract languages.
     */
    private function extractLanguages(string $content): array
    {
        $languages = [];
        $content = strtolower($content);

        $commonLangs = ['english', 'arabic', 'french', 'spanish', 'german', 'chinese'];

        foreach ($commonLangs as $lang) {
            if (str_contains($content, $lang)) {
                $languages[] = ucfirst($lang);
            }
        }

        return array_unique($languages);
    }

    /**
     * Persist canonical resume data to database.
     */
    private function persistCanonicalData(CanonicalResumeDTO $resume): void
    {
        $cvId = $this->cv->CVID;

        // Persist skills
        foreach ($resume->skills as $skillData) {
            $skillName = $skillData['name'] ?? null;
            if (!$skillName) continue;

            $skill = Skill::firstOrCreate(
                ['SkillName' => $skillName],
                ['SkillName' => $skillName]
            );

            CVSkill::firstOrCreate([
                'CVID' => $cvId,
                'SkillID' => $skill->SkillID,
            ], [
                'SkillLevel' => $skillData['level'] ?? null,
            ]);
        }

        // Persist languages
        foreach ($resume->languages as $langData) {
            $langName = $langData['name'] ?? null;
            if (!$langName) continue;

            $language = Language::firstOrCreate(
                ['LanguageName' => $langName],
                ['LanguageName' => $langName]
            );

            CVLanguage::firstOrCreate([
                'CVID' => $cvId,
                'LanguageID' => $language->LanguageID,
            ], [
                'LanguageLevel' => $langData['level'] ?? null,
            ]);
        }

        Log::info('ParseCvJob: Canonical data persisted', [
            'cv_id' => $cvId,
            'skills_count' => count($resume->skills),
            'languages_count' => count($resume->languages),
        ]);
    }
}
