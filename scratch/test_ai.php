<?php
$ai = app(App\Domain\Shared\Services\GeminiAIService::class);
$cv = App\Domain\CV\Models\CV::with(['skills.skill', 'experiences', 'education'])->find(5);

echo "--- 1. Testing Career Roadmap (Text Generation) ---\n";
$roadmap = $ai->generateCareerRoadmap([
    'title' => $cv->Title,
    'summary' => $cv->PersonalSummary,
    'skills' => $cv->skills->map(fn($s) => ['name' => $s->skill->SkillName ?? 'Unknown', 'level' => $s->SkillLevel])->toArray(),
], 'Senior Mobile Developer');
echo "Status: " . (isset($roadmap['current_level']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Preview: " . mb_substr($roadmap['current_level'] ?? 'N/A', 0, 100) . "...\n\n";

echo "--- 2. Testing CV Analysis Explanation (Human Reasoning) ---\n";
// The service expects pre-calculated context
$analysis = $ai->explainCvAnalysis([
    'cv_title' => $cv->Title,
    'skills' => $cv->skills->map(fn($s) => $s->skill->SkillName)->toArray(),
    'overall_score' => 85,
    'scores' => [
        'experience' => 90,
        'education' => 80,
        'skills' => 85
    ]
]);
echo "Status: " . (isset($analysis['strengths']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Strengths: " . mb_substr($analysis['strengths'] ?? 'N/A', 0, 100) . "...\n";
echo "Gaps: " . mb_substr($analysis['potential_gaps'] ?? 'N/A', 0, 100) . "...\n\n";

echo "--- 3. Testing Match Explanation (Compatibility Reasoning) ---\n";
$match = $ai->explainCompatibility([
    'job_title' => 'Senior Flutter Developer',
    'compatibility_score' => 75,
    'missing_skills' => ['Docker', 'AWS'],
    'matched_skills' => ['Flutter', 'Dart', 'Git']
]);
echo "Status: " . (isset($match['explanation']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Explanation: " . mb_substr($match['explanation'] ?? 'N/A', 0, 100) . "...\n";
