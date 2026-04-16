<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Models\SkillGapAnalysis;
use App\Domain\Application\Models\JobApplication;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVAnalysis;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiMatchResource;
use App\Http\Resources\CvAnalysisResource;
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
    #[OA\Response(response: 200, description: 'CV analysis results')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'No analysis found for this CV')]
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
            $matchData = [
                'job_id' => $jobId,
                'cv_id' => $cvModel->CVID,
                'match_score' => $preview['score'],
                'strengths' => $preview['strengths'],
                'gaps' => $preview['gaps'],
                'explanation' => $preview['explanation'],
            ];
        }

        return response()->json([
            'data' => $matchData,
        ]);
    }

    private function calculatePreMatch($cv, $job): array
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
                $strengths[] = 'Your CV contains matching skills: '.implode(', ', array_slice($matchedSkills, 0, 3));
            }
            if (! empty($missingSkills)) {
                $gaps[] = 'Consider adding missing skills: '.implode(', ', array_slice($missingSkills, 0, 3));
            }

            $skillsScore = min(100, (int) round((count($matchedSkills) / count($jobSkills)) * 100 + max(0, min(10, count($cvSkills) - count($jobSkills)))));
        } else {
            $strengths[] = 'No specific skills required for this job.';
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
                $strengths[] = 'Experience titles closely match the job role.';
            } else {
                $gaps[] = 'Your previous job titles do not perfectly match the advertised role.';
            }

            $strengths[] = 'You have approximately '.round($totalYears, 1).' years of experience.';
        } else {
            $gaps[] = 'No experience listed on your CV.';
        }

        $educationScore = 30;
        if ($cv->education->isNotEmpty()) {
            $strengths[] = 'You have an educational background.';
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
            $gaps[] = 'No formal education listed.';
        }

        return [
            'score' => (int) round(($skillsScore * 0.4) + ($experienceScore * 0.35) + ($educationScore * 0.25)),
            'strengths' => $strengths,
            'gaps' => $gaps,
            'explanation' => 'Calculated heuristically based on your CV details against job requirements.',
        ];
    }

    /**
     * Get market trends (top skills).
     * (For Job Seekers to know which skills are popular)
     */
    #[OA\Get(
        path: '/market-trends',
        operationId: 'getMarketTrends',
        tags: ['AI Analytics', 'Market'],
        summary: 'Get market skill trends',
        description: 'Returns the most in-demand skills in the market based on AI analysis.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of trending skills')]
    public function marketTrends(Request $request): JsonResponse
    {
        $latestSnapshotDate = \App\Domain\AI\Models\SkillDemandSnapshot::max('SnapshotDate');

        $trends = \App\Domain\AI\Models\SkillDemandSnapshot::with('skill.category')
            ->where('SnapshotDate', $latestSnapshotDate)
            ->orderByDesc('DemandCount')
            ->take(20)
            ->get();

        return response()->json([
            'data' => $trends,
            'meta' => [
                'snapshot_date' => $latestSnapshotDate,
            ],
        ]);
    }
}
