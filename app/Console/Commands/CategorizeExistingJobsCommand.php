<?php

namespace App\Console\Commands;

use App\Domain\Job\Models\JobAd;
use App\Jobs\CategorizeJobAdJob;
use Illuminate\Console\Command;

class CategorizeExistingJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:categorize {--all : Re-categorize all jobs, even those with an industry_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches AI categorization jobs for existing Job Ads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = JobAd::query();

        if (! $this->option('all')) {
            $query->whereNull('industry_id');
        }

        $jobs = $query->get();

        if ($jobs->isEmpty()) {
            $this->info('No jobs found needing categorization.');

            return;
        }

        $this->info("Dispatching categorization jobs for {$jobs->count()} Job Ads...");

        $bar = $this->output->createProgressBar($jobs->count());

        foreach ($jobs as $job) {
            CategorizeJobAdJob::dispatch($job);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All categorization jobs dispatched successfully!');
    }
}
