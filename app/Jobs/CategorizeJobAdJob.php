<?php

namespace App\Jobs;

use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\Industry;
use App\Domain\Shared\Services\GeminiAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CategorizeJobAdJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public JobAd $jobAd) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiAIService $aiService): void
    {
        try {
            Log::info('CategorizeJobAdJob: Starting AI categorization', ['JobAdID' => $this->jobAd->JobAdID]);

            $existingIndustries = Industry::pluck('name')->toArray();

            $jobData = [
                'Title' => $this->jobAd->Title,
                'Description' => $this->jobAd->Description,
                'Requirements' => $this->jobAd->Requirements,
                'Responsibilities' => $this->jobAd->Responsibilities,
            ];

            $categoryName = $aiService->categorizeJobAd($jobData, $existingIndustries);

            if (! empty($categoryName)) {
                $industry = Industry::firstOrCreate(['name' => trim($categoryName)]);
                $this->jobAd->update(['industry_id' => $industry->id]);

                Log::info('CategorizeJobAdJob: Successfully categorized job', [
                    'JobAdID' => $this->jobAd->JobAdID,
                    'Category' => $industry->name,
                ]);
            } else {
                Log::warning('CategorizeJobAdJob: AI returned empty category name', [
                    'JobAdID' => $this->jobAd->JobAdID,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('CategorizeJobAdJob: Failed to categorize job', [
                'JobAdID' => $this->jobAd->JobAdID,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
