<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\AiAnalyticsController;
use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;

class TestAiAnalyticsController extends AiAnalyticsController {
    public function testCalculatePreMatch($cv, $job) {
        return $this->calculatePreMatch($cv, $job);
    }
}

try {
    $cv = CV::first();
    $job = JobAd::first();

    if (!$cv || !$job) {
        echo "Error: Need at least one CV and one JobAd in the database.\n";
        exit(1);
    }

    echo "Testing Match between CV #{$cv->CVID} and Job #{$job->JobID}\n";
    
    $controller = new TestAiAnalyticsController();
    $result = $controller->testCalculatePreMatch($cv, $job);

    echo "Match Result:\n";
    echo "Score: " . $result['score'] . "\n";
    echo "Strengths (EN): " . implode(", ", array_column($result['strengths'], 'en')) . "\n";
    echo "Strengths (AR): " . implode(", ", array_column($result['strengths'], 'ar')) . "\n";
    echo "Gaps (EN): " . implode(", ", array_column($result['gaps'], 'en')) . "\n";
    echo "Explanation (EN): " . $result['explanation']['en'] . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
