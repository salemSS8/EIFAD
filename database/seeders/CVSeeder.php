<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use App\Domain\User\Models\JobSeekerProfile;
use App\Domain\CV\Models\CV;

class CVSeeder extends Seeder
{
    public function run(): void
    {
        $cvs = [
            [
                'email' => 'mohammed.ali@email.com',
                'Title' => 'مطور ويب Full Stack',
                'PersonalSummary' => 'مطور ويب متخصص في Laravel و React مع خبرة 3 سنوات في بناء تطبيقات الويب المتكاملة. شغوف بالتعلم المستمر وتطبيق أفضل الممارسات في البرمجة.',
            ],
            [
                'email' => 'fatima.ahmed@email.com',
                'Title' => 'مصممة جرافيك وUI/UX',
                'PersonalSummary' => 'مصممة مبدعة متخصصة في تصميم واجهات المستخدم وتجربة المستخدم. خبرة 4 سنوات في تصميم الهوية البصرية للشركات.',
            ],
            [
                'email' => 'omar.salem@email.com',
                'Title' => 'محاسب مالي معتمد',
                'PersonalSummary' => 'محاسب معتمد مع خبرة واسعة في المحاسبة المالية وإعداد القوائم المالية. حاصل على شهادة CPA.',
            ],
            [
                'email' => 'noura.khaled@email.com',
                'Title' => 'متخصصة تسويق رقمي',
                'PersonalSummary' => 'متخصصة في التسويق الرقمي وإدارة حملات السوشيال ميديا. خبرة في تحسين محركات البحث والإعلانات المدفوعة.',
            ],
            [
                'email' => 'yasser.abdulrahman@email.com',
                'Title' => 'مطور تطبيقات موبايل',
                'PersonalSummary' => 'مهندس برمجيات متخصص في تطوير تطبيقات Android و iOS باستخدام Flutter و React Native.',
            ],
        ];

        CV::unguard();
        $cvId = 1;

        foreach ($cvs as $cv) {
            $user = User::where('Email', $cv['email'])->first();
            if ($user) {
                $profile = JobSeekerProfile::where('JobSeekerID', $user->UserID)->first();
                if ($profile) {
                    CV::firstOrCreate(
                        ['JobSeekerID' => $profile->JobSeekerID, 'Title' => $cv['Title']],
                        [
                            'CVID' => $cvId++,
                            'JobSeekerID' => $profile->JobSeekerID,
                            'Title' => $cv['Title'],
                            'PersonalSummary' => $cv['PersonalSummary'],
                            'CreatedAt' => now(),
                            'UpdatedAt' => now(),
                        ]
                    );
                }
            }
        }
        CV::reguard();
    }
}
