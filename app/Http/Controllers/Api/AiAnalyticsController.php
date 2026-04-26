<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Models\JobDemandSnapshot;
use App\Domain\AI\Models\SkillDemandSnapshot;
use App\Domain\AI\Models\SkillGapAnalysis;
use App\Domain\Application\Models\JobApplication;
use App\Domain\Course\Models\Roadmap;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVAnalysis;
use App\Domain\Shared\Services\GeminiAIService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiMatchResource;
use App\Http\Resources\CvAnalysisResource;
use App\Http\Resources\RoadmapResource;
use App\Http\Resources\SkillGapResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * AI Analytics Controller - Exposes AI-generated analysis data.
 */
class AiAnalyticsController extends Controller
{
    /**
     * Get AI match details for a specific application.
     * (For Employers viewing applicant match analysis)
     */
    #[OA\Get(
        path: '/applications/{application}/ai-match',
        operationId: 'getAiMatch',
        tags: ['AI Analytics'],
        summary: 'Get AI match analysis for an application',
        description: 'Returns the AI screening match score, notes, and detailed CV-Job compatibility breakdown.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'application', in: 'path', required: true, description: 'Application ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'AI match details')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Application not found')]
    public function aiMatch(Request $request, int $application): JsonResponse
    {
        $jobApplication = JobApplication::with(['jobAd.company'])
            ->where('ApplicationID', $application)
            ->firstOrFail();

        // Authorization: only the employer who owns the job can view
        $user = $request->user();
        if ($jobApplication->jobAd && $jobApplication->jobAd->CompanyID !== $user->UserID) {
            return response()->json(['message' => 'Unauthorized: Only the company owning this job can view AI matches.'], 403);
        }

        // Load CVJobMatch separately (matching both CVID and JobAdID)
        $cvJobMatch = \App\Domain\AI\Models\CVJobMatch::with('details.skill')
            ->where('CVID', $jobApplication->CVID)
            ->where('JobAdID', $jobApplication->JobAdID)
            ->first();

        $jobApplication->setRelation('cvJobMatch', $cvJobMatch);

        return response()->json([
            'data' => new AiMatchResource($jobApplication),
        ]);
    }

    /**
     * Get CV analysis results.
     * (For Job Seekers viewing their CV assessment)
     */
    #[OA\Get(
        path: '/cvs/{cv}/analysis',
        operationId: 'getCvAnalysis',
        tags: ['AI Analytics'],
        summary: 'Get AI analysis of a CV',
        description: 'Returns scores, strengths, gaps, and improvement recommendations for a CV.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cv', in: 'path', required: true, description: 'CV ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'CV analysis results',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'cv_id', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'scores',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'overall', type: 'integer', example: 75),
                                new OA\Property(property: 'skills', type: 'integer', example: 80),
                                new OA\Property(property: 'experience', type: 'integer', example: 70),
                                new OA\Property(property: 'education', type: 'integer', example: 65),
                                new OA\Property(property: 'completeness', type: 'integer', example: 85),
                                new OA\Property(property: 'consistency', type: 'integer', example: 90),
                            ]
                        ),
                        new OA\Property(property: 'score_breakdown', type: 'object', nullable: true),
                        new OA\Property(property: 'scoring_method', type: 'string', example: 'rule_based'),
                        new OA\Property(property: 'strengths', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                        new OA\Property(property: 'potential_gaps', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                        new OA\Property(property: 'improvement_recommendations', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                        new OA\Property(property: 'ai_explanation', type: 'string', nullable: true),
                        new OA\Property(property: 'ai_model', type: 'string', nullable: true, example: 'gemini-1.5-flash'),
                        new OA\Property(property: 'analyzed_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'scored_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'explained_at', type: 'string', format: 'date-time', nullable: true),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized — only the CV owner can view analysis')]
    #[OA\Response(
        response: 404,
        description: 'No analysis found for this CV',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'No analysis found for this CV'),
                new OA\Property(property: 'data', type: 'string', nullable: true, example: null),
            ]
        )
    )]
    public function cvAnalysis(Request $request, int $cv): JsonResponse
    {
        $cvModel = CV::where('CVID', $cv)->firstOrFail();

        // Authorization: only the CV owner can view
        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;
        if (! $jobSeekerProfile || $cvModel->JobSeekerID !== $jobSeekerProfile->JobSeekerID) {
            return response()->json(['message' => 'Unauthorized: You can only view analysis for your own CV.'], 403);
        }

        $analysis = CVAnalysis::where('CVID', $cv)
            ->latest()
            ->first();

        if (! $analysis) {
            return response()->json([
                'message' => 'No analysis found for this CV',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new CvAnalysisResource($analysis),
        ]);
    }

    /**
     * Get skill gap analysis for a CV.
     * (For Job Seekers to know what they're missing)
     */
    #[OA\Get(
        path: '/cvs/{cv}/skill-gaps',
        operationId: 'getCvSkillGaps',
        tags: ['AI Analytics'],
        summary: 'Get skill gap analysis for a CV',
        description: 'Returns a list of skills the job seeker is missing compared to market demands.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cv', in: 'path', required: true, description: 'CV ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'job_ad_id', in: 'query', required: false, description: 'Filter by specific job ad', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Skill gap analysis results')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    public function skillGaps(Request $request, int $cv): JsonResponse
    {
        $cvModel = CV::where('CVID', $cv)->firstOrFail();

        // Authorization: only the CV owner can view
        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;
        if (! $jobSeekerProfile || $cvModel->JobSeekerID !== $jobSeekerProfile->JobSeekerID) {
            return response()->json(['message' => 'Unauthorized: You can only view skill gaps for your own CV.'], 403);
        }

        $query = SkillGapAnalysis::with(['skill', 'jobAd:JobAdID,Title'])
            ->where('CVID', $cv);

        if ($request->filled('job_ad_id')) {
            $query->where('JobAdID', $request->input('job_ad_id'));
        }

        $gaps = $query->get();

        return response()->json([
            'data' => SkillGapResource::collection($gaps),
            'summary' => [
                'total_gaps' => $gaps->count(),
                'gap_types' => $gaps->groupBy('GapType')->map->count(),
            ],
        ]);
    }

    /**
     * Get match score for a job before applying.
     * (For Job Seekers to see their compatibility score in advance)
     */
    #[OA\Get(
        path: '/jobs/{jobId}/match-score',
        operationId: 'getPreApplyMatchScore',
        tags: ['AI Analytics'],
        summary: 'Get match score before applying',
        description: 'Returns the CV match score for a specific job without actually applying.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, description: 'Job Ad ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'cv_id', in: 'query', required: true, description: 'CV ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Match score details')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Job or CV not found')]
    public function preApplyMatchScore(Request $request, int $jobId): JsonResponse
    {
        $request->validate([
            'cv_id' => 'required|exists:cv,CVID',
        ]);

        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;

        if (! $jobSeekerProfile) {
            return response()->json(['message' => 'Unauthorized: Only job seekers with a valid profile can view match scores.'], 403);
        }

        $cvModel = CV::where('CVID', $request->input('cv_id'))
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $jobModel = \App\Domain\Job\Models\JobAd::findOrFail($jobId);

        // Check if there is an existing precalculated match
        $cvJobMatch = \App\Domain\AI\Models\CVJobMatch::where('CVID', $cvModel->CVID)
            ->where('JobAdID', $jobId)
            ->first();

        if ($cvJobMatch) {
            $matchData = [
                'job_id' => $jobId,
                'cv_id' => $cvModel->CVID,
                'match_score' => $cvJobMatch->MatchScore,
                'strengths' => $cvJobMatch->Strengths ?? [],
                'gaps' => $cvJobMatch->Gaps ?? [],
                'explanation' => $cvJobMatch->Explanation ?? 'AI analysis processed.',
            ];
        } else {
            $preview = $this->calculatePreMatch($cvModel, $jobModel);

            // Persist the match result so it's available in /jobs/matching
            \App\Domain\AI\Models\CVJobMatch::updateOrCreate(
                [
                    'CVID' => $cvModel->CVID,
                    'JobAdID' => $jobId,
                ],
                [
                    'MatchScore' => $preview['score'],
                    'SkillsScore' => $preview['skills_score'],
                    'ExperienceScore' => $preview['experience_score'],
                    'EducationScore' => $preview['education_score'],
                    'Strengths' => $preview['strengths'],
                    'Gaps' => $preview['gaps'],
                    'Explanation' => $preview['explanation'],
                    'ScoreBreakdown' => [
                        'skills' => $preview['skills_score'],
                        'experience' => $preview['experience_score'],
                        'education' => $preview['education_score'],
                    ],
                    'ScoringMethod' => 'rule_based_heuristic',
                    'CalculatedAt' => now(),
                ]
            );

            $matchData = [
                'job_id' => $jobId,
                'cv_id' => $cvModel->CVID,
                'match_score' => $preview['score'],
                'skills_score' => $preview['skills_score'],
                'experience_score' => $preview['experience_score'],
                'education_score' => $preview['education_score'],
                'strengths' => $preview['strengths'],
                'gaps' => $preview['gaps'],
                'explanation' => $preview['explanation'],
            ];
        }

        return response()->json([
            'data' => $matchData,
        ]);
    }

    protected function calculatePreMatch($cv, $job): array
    {
        $cv->load(['skills.skill', 'experiences', 'education']);
        $job->load(['skills.skill']);

        $jobSkills = $job->skills->pluck('skill.SkillName')->map(fn ($s) => strtolower(trim($s)))->toArray();
        $cvSkills = $cv->skills->pluck('skill.SkillName')->map(fn ($s) => strtolower(trim($s)))->toArray();

        $strengths = [];
        $gaps = [];

        $skillsScore = 100;
        if (! empty($jobSkills)) {
            $matchedSkills = array_intersect($jobSkills, $cvSkills);
            $missingSkills = array_diff($jobSkills, $cvSkills);

            if (! empty($matchedSkills)) {
                $strengths[] = $this->transBoth('skills_matched', ['skills' => implode(', ', array_slice($matchedSkills, 0, 3))]);
            }
            if (! empty($missingSkills)) {
                $gaps[] = $this->transBoth('skills_missing', ['skills' => implode(', ', array_slice($missingSkills, 0, 3))]);
            }

            $skillsScore = min(100, (int) round((count($matchedSkills) / count($jobSkills)) * 100 + max(0, min(10, count($cvSkills) - count($jobSkills)))));
        } else {
            $strengths[] = $this->transBoth('no_skills_required');
        }

        $experienceScore = 20;
        if ($cv->experiences->isNotEmpty()) {
            $totalYears = 0;
            $relevantExperience = false;
            $jobTitle = strtolower($job->Title ?? '');

            foreach ($cv->experiences as $exp) {
                $startDate = strtotime($exp->StartDate ?? '');
                $endDate = $exp->EndDate ? strtotime($exp->EndDate) : time();
                if ($startDate) {
                    $totalYears += max(0, ($endDate - $startDate) / (365 * 24 * 60 * 60));
                }
                $expTitle = strtolower($exp->JobTitle ?? '');
                if (str_contains($jobTitle, $expTitle) || str_contains($expTitle, $jobTitle)) {
                    $relevantExperience = true;
                }
            }

            if ($totalYears >= 10) {
                $experienceScore = 90;
            } elseif ($totalYears >= 5) {
                $experienceScore = 75;
            } elseif ($totalYears >= 3) {
                $experienceScore = 60;
            } elseif ($totalYears >= 1) {
                $experienceScore = 45;
            } else {
                $experienceScore = 30;
            }

            if ($relevantExperience) {
                $experienceScore = min(100, $experienceScore + 10);
                $strengths[] = $this->transBoth('experience_match');
            } else {
                $gaps[] = $this->transBoth('experience_mismatch');
            }

            $strengths[] = $this->transBoth('years_of_experience', ['years' => round($totalYears, 1)]);
        } else {
            $gaps[] = $this->transBoth('no_experience');
        }

        $educationScore = 30;
        if ($cv->education->isNotEmpty()) {
            $strengths[] = $this->transBoth('education_found');
            foreach ($cv->education as $edu) {
                $degree = strtolower($edu->DegreeName ?? '');
                if (str_contains($degree, 'phd') || str_contains($degree, 'doctorate')) {
                    $educationScore = max($educationScore, 100);
                } elseif (str_contains($degree, 'master') || str_contains($degree, 'mba')) {
                    $educationScore = max($educationScore, 85);
                } elseif (str_contains($degree, 'bachelor')) {
                    $educationScore = max($educationScore, 70);
                } elseif (str_contains($degree, 'diploma')) {
                    $educationScore = max($educationScore, 50);
                }
            }
        } else {
            $gaps[] = $this->transBoth('no_education');
        }

        return [
            'score' => (int) round(($skillsScore * 0.4) + ($experienceScore * 0.35) + ($educationScore * 0.25)),
            'skills_score' => $skillsScore,
            'experience_score' => $experienceScore,
            'education_score' => $educationScore,
            'strengths' => $strengths,
            'gaps' => $gaps,
            'explanation' => $this->transBoth('explanation'),
        ];
    }

    /**
     * Helper to get translations in both Arabic and English.
     */
    protected function transBoth(string $key, array $replace = []): array
    {
        return [
            'en' => \Illuminate\Support\Facades\Lang::get('match_analysis.'.$key, $replace, 'en'),
            'ar' => \Illuminate\Support\Facades\Lang::get('match_analysis.'.$key, $replace, 'ar'),
        ];
    }

    /**
     * Get market trends (top skills and trending jobs).
     */
    #[OA\Get(
        path: '/market-trends',
        operationId: 'getMarketTrends',
        tags: ['AI Analytics', 'Market'],
        summary: 'Get market skill and job trends',
        description: 'Returns the most in-demand skills and trending jobs based on active job ads.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Market trends data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'top_skills',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'skill_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'skill_name', type: 'string', example: 'Laravel'),
                                    new OA\Property(property: 'demand_count', type: 'integer', example: 120),
                                    new OA\Property(property: 'popularity_percentage', type: 'number', format: 'float', example: 85.5),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'trending_jobs',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'JobTitle', type: 'string', example: 'Backend Developer'),
                                    new OA\Property(property: 'AverageSalary', type: 'number', format: 'float', example: 5500.00),
                                    new OA\Property(property: 'PostCount', type: 'integer', example: 45),
                                    new OA\Property(property: 'SnapshotDate', type: 'string', format: 'date', example: '2024-01-10'),
                                ]
                            )
                        ),
                    ]
                ),
                new OA\Property(
                    property: 'meta',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'snapshot_date', type: 'string', format: 'date', example: '2024-01-10'),
                        new OA\Property(property: 'total_active_jobs', type: 'integer', example: 500),
                    ]
                ),
            ]
        )
    )]
    public function marketTrends(Request $request): JsonResponse
    {
        $latestSnapshotDate = SkillDemandSnapshot::max('SnapshotDate');
        $totalActiveJobs = \App\Domain\Job\Models\JobAd::where('Status', 'Active')->count();
        $totalActiveJobs = max(1, $totalActiveJobs); // Prevent division by zero

        // Fetch Top Skills with Percentage Popularity
        $skillTrends = SkillDemandSnapshot::with('skill')
            ->where('SnapshotDate', $latestSnapshotDate)
            ->orderByDesc('DemandCount')
            ->take(15)
            ->get()
            ->map(function ($snapshot) use ($totalActiveJobs) {
                return [
                    'skill_id' => $snapshot->SkillID,
                    'skill_name' => $snapshot->skill->SkillName ?? 'Unknown',
                    'demand_count' => $snapshot->DemandCount,
                    'popularity_percentage' => round(($snapshot->DemandCount / $totalActiveJobs) * 100, 1),
                ];
            });

        // Fetch Trending Jobs with Salary Benchmarks
        $jobTrends = JobDemandSnapshot::where('SnapshotDate', $latestSnapshotDate)
            ->orderByDesc('PostCount')
            ->take(10)
            ->get();

        return response()->json([
            'data' => [
                'top_skills' => $skillTrends,
                'trending_jobs' => $jobTrends,
            ],
            'meta' => [
                'snapshot_date' => $latestSnapshotDate,
                'total_active_jobs' => $totalActiveJobs,
            ],
        ]);
    }

    /**
     * Generate a career roadmap for the authenticated user.
     * Calls Gemini AI synchronously and stores the result.
     */
    #[OA\Post(
        path: '/career-roadmap',
        operationId: 'generateCareerRoadmap',
        tags: ['AI Analytics'],
        summary: 'Generate a career roadmap',
        description: 'Uses AI to generate a step-by-step career roadmap based on the user\'s CV and a target role. The AI call is synchronous — the response may take several seconds.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['target_role', 'cv_id'],
            properties: [
                new OA\Property(property: 'target_role', type: 'string', example: 'Senior Backend Developer'),
                new OA\Property(property: 'cv_id', type: 'integer', example: 5),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Career roadmap generated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Career roadmap generated successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'user_id', type: 'integer', example: 155),
                        new OA\Property(property: 'title', type: 'string', example: 'Senior Backend Developer Roadmap'),
                        new OA\Property(property: 'target_role', type: 'string', example: 'Senior Backend Developer'),
                        new OA\Property(property: 'current_level', type: 'string', nullable: true, example: 'Junior Developer'),
                        new OA\Property(property: 'target_level', type: 'string', nullable: true, example: 'Senior Backend Developer'),
                        new OA\Property(
                            property: 'milestones',
                            type: 'array',
                            nullable: true,
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'title', type: 'string', example: 'Phase 1: Foundations'),
                                    new OA\Property(property: 'duration', type: 'string', example: '3 months'),
                                    new OA\Property(property: 'skills_to_learn', type: 'array', items: new OA\Items(type: 'string'), example: '["Design Patterns", "Testing"]'),
                                    new OA\Property(property: 'actions', type: 'array', items: new OA\Items(type: 'string'), example: '["Read Clean Code", "Write unit tests"]'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'total_estimated_time', type: 'string', nullable: true, example: '12 months'),
                        new OA\Property(property: 'generated_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Unauthorized — only job seekers can generate a roadmap',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Unauthorized: Only job seekers can generate a career roadmap.'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'The target role field is required.'),
                new OA\Property(property: 'errors', type: 'object'),
            ]
        )
    )]
    public function generateRoadmap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_role' => 'required|string|max:255',
            'cv_id' => 'required|integer|exists:cv,CVID',
        ]);

        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;

        if (! $jobSeekerProfile) {
            return response()->json(['message' => 'Unauthorized: Only job seekers can generate a career roadmap.'], 403);
        }

        $cv = CV::where('CVID', $validated['cv_id'])
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cv->load(['skills.skill', 'experiences', 'education', 'certifications', 'languages.language']);

        $userProfile = [
            'title' => $cv->Title,
            'summary' => $cv->PersonalSummary,
            'skills' => $cv->skills->map(fn ($s) => [
                'name' => $s->skill->SkillName ?? 'Unknown',
                'level' => $s->SkillLevel,
            ])->toArray(),
            'experience' => $cv->experiences->map(fn ($e) => [
                'job_title' => $e->JobTitle,
                'company' => $e->CompanyName,
                'start_date' => $e->StartDate,
                'end_date' => $e->EndDate,
            ])->toArray(),
            'education' => $cv->education->map(fn ($e) => [
                'institution' => $e->Institution,
                'degree' => $e->DegreeName,
                'major' => $e->Major,
                'graduation_year' => $e->GraduationYear,
            ])->toArray(),
            'certifications' => $cv->certifications->map(fn ($c) => [
                'name' => $c->CertificateName,
                'issuer' => $c->IssuingOrganization,
            ])->toArray(),
            'languages' => $cv->languages->map(fn ($l) => [
                'name' => $l->language->LanguageName ?? 'Unknown',
                'level' => $l->LanguageLevel,
            ])->toArray(),
        ];

        $aiService = app(GeminiAIService::class);
        $result = $aiService->generateCareerRoadmap($userProfile, $validated['target_role']);

        // Deactivate any previous active roadmap for this user
        Roadmap::where('user_id', $user->UserID)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $roadmap = Roadmap::create([
            'user_id' => $user->UserID,
            'title' => $validated['target_role'].' Roadmap',
            'target_role' => $validated['target_role'],
            'current_level' => $result['current_level'] ?? null,
            'target_level' => $result['target_level'] ?? null,
            'milestones' => $result['milestones'] ?? null,
            'total_estimated_time' => $result['total_estimated_time'] ?? null,
            'generated_at' => now(),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Career roadmap generated successfully.',
            'data' => new RoadmapResource($roadmap),
        ]);
    }

    /**
     * Get the user's currently active career roadmap.
     */
    #[OA\Get(
        path: '/career-roadmap',
        operationId: 'getCareerRoadmap',
        tags: ['AI Analytics'],
        summary: 'Get active career roadmap',
        description: 'Returns the user\'s most recent active career roadmap from the database.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Active career roadmap',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'user_id', type: 'integer', example: 155),
                        new OA\Property(property: 'title', type: 'string', example: 'Senior Backend Developer Roadmap'),
                        new OA\Property(property: 'target_role', type: 'string', example: 'Senior Backend Developer'),
                        new OA\Property(property: 'current_level', type: 'string', nullable: true),
                        new OA\Property(property: 'target_level', type: 'string', nullable: true),
                        new OA\Property(property: 'milestones', type: 'array', nullable: true, items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'total_estimated_time', type: 'string', nullable: true),
                        new OA\Property(property: 'generated_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'is_active', type: 'boolean', example: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'No active roadmap found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'No active career roadmap found. Generate one first.'),
                new OA\Property(property: 'data', type: 'string', nullable: true, example: null),
            ]
        )
    )]
    public function showRoadmap(Request $request): JsonResponse
    {
        $user = $request->user();

        $roadmap = Roadmap::where('user_id', $user->UserID)
            ->where('is_active', true)
            ->latest('generated_at')
            ->first();

        if (! $roadmap) {
            return response()->json([
                'message' => 'No active career roadmap found. Generate one first.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new RoadmapResource($roadmap),
        ]);
    }
}
