<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\CV\Models\CV;
use App\Domain\Job\Models\JobAd;
use App\Domain\Application\Models\JobApplication;

class JobApplicationSeeder extends Seeder
{
    public function run(): void
    {
        // Get job seekers with CVs
        $jobSeekers = JobSeekerProfile::all();
        $jobs = JobAd::all();

        $statuses = ['Pending', 'Reviewed', 'Interview', 'Accepted', 'Rejected'];

        foreach ($jobSeekers as $seeker) {
            $cv = CV::where('JobSeekerID', $seeker->JobSeekerID)->first();
            if (!$cv) continue;

            // Each job seeker applies to 2-4 random jobs
            $randomJobs = $jobs->random(min(rand(2, 4), $jobs->count()));

            foreach ($randomJobs as $job) {
                JobApplication::firstOrCreate(
                    ['JobAdID' => $job->JobAdID, 'JobSeekerID' => $seeker->JobSeekerID],
                    [
                        'JobAdID' => $job->JobAdID,
                        'JobSeekerID' => $seeker->JobSeekerID,
                        'CVID' => $cv->CVID,
                        'AppliedAt' => now()->subDays(rand(1, 20)),
                        'Status' => $statuses[array_rand($statuses)],
                        'MatchScore' => rand(50, 95),
                        'Notes' => null,
                    ]
                );
            }
        }
    }
}
