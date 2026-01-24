<?php

namespace App\Domain\Skill\Jobs;

use App\Domain\CV\Models\CV;
use App\Domain\Skill\Models\Skill;
use App\Domain\AI\Models\SkillGapAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Compute Skill Gap using Rule-Based Set Comparison (NO AI).
 * 
 * This job compares user's current skills with target role requirements
 * using deterministic set operations.
 * 
 * Output:
 * - missing_skills: skills required but not present
 * - matching_skills: skills that match requirements
 * - skill_coverage: percentage of required skills covered
 * 
 * NO AI is used in this job.
 */
class ComputeSkillGapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public CV $cv,
        public string $targetRole,
        public array $targetSkillIds = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        try {
            $this->cv->load('skills.skill');

            // Get target skills
            $targetSkills = $this->getTargetSkills();

            // Get user's current skills
            $currentSkills = $this->cv->skills
                ->pluck('skill.SkillName', 'skill.SkillID')
                ->toArray();

            // Calculate gaps
            $result = $this->calculateSkillGap($currentSkills, $targetSkills);

            // Persist result
            $this->persistResult($result);

            Log::info('ComputeSkillGapJob: Skill gap computed', [
                'cv_id' => $this->cv->CVID,
                'target_role' => $this->targetRole,
                'coverage' => $result['skill_coverage'],
            ]);

            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            Log::error('ComputeSkillGapJob failed', [
                'cv_id' => $this->cv->CVID,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get target skills based on role or provided IDs.
     */
    private function getTargetSkills(): array
    {
        if (!empty($this->targetSkillIds)) {
            return Skill::whereIn('SkillID', $this->targetSkillIds)
                ->pluck('SkillName', 'SkillID')
                ->toArray();
        }

        // Get skills from jobs with similar titles
        $roleKeywords = array_filter(explode(' ', strtolower($this->targetRole)));

        $relevantJobSkillIds = \App\Domain\Job\Models\JobAd::where('Status', 'Active')
            ->where(function ($q) use ($roleKeywords) {
                foreach ($roleKeywords as $keyword) {
                    if (strlen($keyword) > 3) {
                        $q->orWhere('Title', 'like', "%{$keyword}%");
                    }
                }
            })
            ->with('skills')
            ->take(20)
            ->get()
            ->flatMap(fn($job) => $job->skills->pluck('SkillID'))
            ->unique()
            ->toArray();

        return Skill::whereIn('SkillID', $relevantJobSkillIds)
            ->pluck('SkillName', 'SkillID')
            ->toArray();
    }

    /**
     * Calculate skill gap using set operations.
     */
    private function calculateSkillGap(array $currentSkills, array $targetSkills): array
    {
        $currentNames = array_map('strtolower', array_values($currentSkills));
        $targetNames = array_map('strtolower', array_values($targetSkills));

        // Find matching skills
        $matchingNames = array_intersect($targetNames, $currentNames);

        // Find missing skills
        $missingNames = array_diff($targetNames, $currentNames);

        // Find extra skills (not required but present)
        $extraNames = array_diff($currentNames, $targetNames);

        // Calculate coverage
        $coverage = empty($targetNames)
            ? 100
            : round((count($matchingNames) / count($targetNames)) * 100);

        // Map back to original casing
        $matching = [];
        $missing = [];

        foreach ($targetSkills as $id => $name) {
            if (in_array(strtolower($name), $matchingNames)) {
                $matching[$id] = $name;
            } else {
                $missing[$id] = $name;
            }
        }

        return [
            'target_role' => $this->targetRole,
            'skill_coverage' => $coverage,
            'matching_skills' => array_values($matching),
            'matching_count' => count($matching),
            'missing_skills' => array_values($missing),
            'missing_count' => count($missing),
            'extra_skills' => array_values(array_filter($currentSkills, function ($name) use ($extraNames) {
                return in_array(strtolower($name), $extraNames);
            })),
            'total_target_skills' => count($targetSkills),
            'total_current_skills' => count($currentSkills),
        ];
    }

    /**
     * Persist result to database.
     */
    private function persistResult(array $result): void
    {
        SkillGapAnalysis::updateOrCreate(
            [
                'CVID' => $this->cv->CVID,
                'TargetRole' => $this->targetRole,
            ],
            [
                'SkillCoverage' => $result['skill_coverage'],
                'MatchingSkills' => json_encode($result['matching_skills']),
                'MissingSkills' => json_encode($result['missing_skills']),
                'ExtraSkills' => json_encode($result['extra_skills']),
                'AnalysisMethod' => 'rule_based_set_comparison',
                'AnalyzedAt' => now(),
            ]
        );
    }
}
