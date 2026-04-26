<?php

namespace App\Console\Commands;

use App\Domain\AI\Models\JobDemandSnapshot;
use App\Domain\AI\Models\SkillDemandSnapshot;
use App\Domain\Job\Models\JobAd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMarketTrends extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:sync-trends';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze active job ads to sync skill and job title trends snapshots.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Market Trends Analysis...');
        $today = now()->toDateString();

        // 1. Process Skill Trends
        $this->info('Processing Skill Trends...');

        // Remove existing snapshots for today to allow re-runs
        SkillDemandSnapshot::where('SnapshotDate', $today)->delete();

        $skillTrends = DB::table('jobskill')
            ->join('jobad', 'jobskill.JobAdID', '=', 'jobad.JobAdID')
            ->where('jobad.Status', 'Active')
            ->select('jobskill.SkillID', DB::raw('count(*) as demand_count'))
            ->groupBy('jobskill.SkillID')
            ->get();

        foreach ($skillTrends as $trend) {
            SkillDemandSnapshot::create([
                'SkillID' => $trend->SkillID,
                'DemandCount' => $trend->demand_count,
                'SnapshotDate' => $today,
            ]);
        }

        // 2. Process Job Title Trends
        $this->info('Processing Job Title Trends...');

        JobDemandSnapshot::where('SnapshotDate', $today)->delete();

        $jobTrends = JobAd::where('Status', 'Active')
            ->select(
                'Title',
                DB::raw('count(*) as post_count'),
                DB::raw('AVG((SalaryMin + SalaryMax) / 2) as avg_salary')
            )
            ->groupBy('Title')
            ->orderByDesc('post_count')
            ->get();

        foreach ($jobTrends as $job) {
            JobDemandSnapshot::create([
                'JobTitle' => $job->Title,
                'AverageSalary' => $job->avg_salary,
                'PostCount' => $job->post_count,
                'SnapshotDate' => $today,
            ]);
        }

        $this->info('Market Trends Synced Successfully!');

        return Command::SUCCESS;
    }
}
