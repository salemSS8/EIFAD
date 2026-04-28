<?php

namespace App\Jobs;

use App\Domain\AI\Models\SyncLog;
use App\Domain\AI\Services\SyncMarketTrendsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncMarketTrendsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?int $userId = null) {}

    /**
     * Execute the job.
     */
    public function handle(SyncMarketTrendsService $service): void
    {
        $service->aggregate($this->userId);
    }
}
