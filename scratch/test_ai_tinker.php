$ai = app(App\Domain\Shared\Services\GeminiAIService.class);
$cv = App\Domain\CV\Models\CV::with(['skills.skill', 'experiences', 'education', 'certifications', 'languages.language'])->find(5);
$job = App\Domain\Job\Models\JobAd::with('skills.skill')->first();

echo "\n--- [START AI INTEGRATION TEST] ---\n";

// 1. Career Roadmap
echo "Testing: generateCareerRoadmap...\n";
$roadmap = $ai->generateCareerRoadmap([
    'title' => $cv->Title,
    'summary' => $cv->PersonalSummary,
    'skills' => $cv->skills->map(fn($s) => ['name' => $s->skill->SkillName ?? 'Unknown', 'level' => $s->SkillLevel])->toArray(),
], 'Senior Mobile Developer');
echo "Status: " . (isset($roadmap['current_level']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Current Level Preview: " . mb_substr($roadmap['current_level'] ?? 'N/A', 0, 100) . "...\n\n";

// 2. CV Analysis Explanation
echo "Testing: explainCvAnalysis...\n";
$analysis = $ai->explainCvAnalysis($cv->toArray(), [
    'overall_score' => 85,
    'sections' => ['experience' => 90, 'skills' => 80]
]);
echo "Status: " . (isset($analysis['explanation']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Explanation Preview: " . mb_substr($analysis['explanation'] ?? 'N/A', 0, 100) . "...\n\n";

// 3. Skill Gaps
echo "Testing: identifySkillGaps...\n";
$gaps = $ai->identifySkillGaps($cv->toArray(), $job->toArray());
echo "Status: " . (isset($gaps['missing_skills']) ? "SUCCESS ✅" : "FAILED ❌") . "\n";
echo "Missing Skills: " . implode(', ', array_slice($gaps['missing_skills'] ?? [], 0, 5)) . (count($gaps['missing_skills'] ?? []) > 5 ? '...' : '') . "\n";

echo "--- [END AI INTEGRATION TEST] ---\n";
