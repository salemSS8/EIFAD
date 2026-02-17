<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\FavoriteJob;

class FavoriteJobSeeder extends Seeder
{
    public function run(): void
    {
        $jobSeekers = JobSeekerProfile::all();
        $jobs = JobAd::all();

        FavoriteJob::unguard();
        $favId = 1;

        foreach ($jobSeekers as $seeker) {
            // Each job seeker favorites 2-5 random jobs
            $randomJobs = $jobs->random(min(rand(2, 5), $jobs->count()));

            foreach ($randomJobs as $job) {
                FavoriteJob::firstOrCreate(
                    ['JobSeekerID' => $seeker->JobSeekerID, 'JobAdID' => $job->JobAdID],
                    [
                        'FavoriteID' => $favId++,
                        'JobSeekerID' => $seeker->JobSeekerID,
                        'JobAdID' => $job->JobAdID,
                        'SavedAt' => now()->subDays(rand(1, 30)),
                    ]
                );
            }
        }
        FavoriteJob::reguard();
    }
}
