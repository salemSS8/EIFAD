<?php

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\JobDemandSnapshot;
use App\Domain\AI\Models\SkillDemandSnapshot;
use App\Domain\AI\Models\SyncLog;
use App\Domain\Job\Models\Industry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMarketTrendsService
{
    /**
     * Aggregate active job ads into snapshots.
     */
    public function aggregate(?int $triggeredBy = null): void
    {
        $lock = Cache::lock('sync_market_trends', 600);

        if (! $lock->get()) {
            Log::warning('Market Trends Sync is already running.');
            return;
        }

        $syncLog = SyncLog::create([
            'status' => 'pending',
            'triggered_by' => $triggeredBy,
            'started_at' => now(),
        ]);

        try {
            $today = now()->toDateString();

        // 1. Process Job Title Trends
        $jobTrends = DB::table('jobad')
            ->join('companyprofile', 'jobad.CompanyID', '=', 'companyprofile.CompanyID')
            ->where('jobad.Status', 'Active')
            ->whereNotNull('jobad.Title')
            ->select(
                'jobad.Title',
                'jobad.Location',
                'companyprofile.FieldOfWork as industry_name',
                DB::raw('count(*) as post_count'),
                DB::raw('AVG((SalaryMin + SalaryMax) / 2) as avg_salary')
            )
            ->groupBy('jobad.Title', 'jobad.Location', 'companyprofile.FieldOfWork')
            ->get();

        foreach ($jobTrends as $trend) {
            $industryId = $this->getIndustryId($trend->industry_name);

            JobDemandSnapshot::updateOrCreate(
                [
                    'JobTitle' => $trend->Title,
                    'industry_id' => $industryId,
                    'city_name' => $trend->Location,
                    'SnapshotDate' => $today,
                ],
                [
                    'AverageSalary' => $trend->avg_salary,
                    'PostCount' => $trend->post_count,
                ]
            );
        }

        // 2. Process Skill Trends
        $skillTrends = DB::table('jobskill')
            ->join('jobad', 'jobskill.JobAdID', '=', 'jobad.JobAdID')
            ->join('companyprofile', 'jobad.CompanyID', '=', 'companyprofile.CompanyID')
            ->where('jobad.Status', 'Active')
            ->select(
                'jobskill.SkillID',
                'jobad.Location',
                'companyprofile.FieldOfWork as industry_name',
                DB::raw('count(*) as demand_count')
            )
            ->groupBy('jobskill.SkillID', 'jobad.Location', 'companyprofile.FieldOfWork')
            ->get();

        foreach ($skillTrends as $trend) {
            $industryId = $this->getIndustryId($trend->industry_name);

            SkillDemandSnapshot::updateOrCreate(
                [
                    'SkillID' => $trend->SkillID,
                    'industry_id' => $industryId,
                    'city_name' => $trend->Location,
                    'SnapshotDate' => $today,
                ],
                [
                    'DemandCount' => $trend->demand_count,
                ]
            );
        }

        $syncLog->update([
            'status' => 'completed',
            'finished_at' => now(),
        ]);
    } catch (\Exception $e) {
        Log::error('Market Trends Sync failed: '.$e->getMessage(), [
            'exception' => $e,
        ]);
        $syncLog->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $e->getMessage(),
        ]);
        throw $e;
    } finally {
        $lock->release();
    }
}

    /**
     * Map industry name to ID.
     */
    private function getIndustryId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return Industry::firstOrCreate(['name' => $name])->id;
    }
}
