<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use App\Domain\User\Models\JobSeekerProfile;

class JobSeekerProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'email' => 'mohammed.ali@email.com',
                'PersonalPhoto' => 'photos/mohammed.jpg',
                'Location' => 'صنعاء، اليمن',
                'ProfileSummary' => 'مطور ويب متحمس مع خبرة 3 سنوات في تطوير تطبيقات الويب باستخدام Laravel و React. أبحث عن فرصة لتطوير مهاراتي في بيئة عمل محفزة.',
            ],
            [
                'email' => 'fatima.ahmed@email.com',
                'PersonalPhoto' => 'photos/fatima.jpg',
                'Location' => 'عدن، اليمن',
                'ProfileSummary' => 'مصممة جرافيك إبداعية مع خبرة 4 سنوات في تصميم الهوية البصرية والإعلانات. متخصصة في Adobe Creative Suite.',
            ],
            [
                'email' => 'omar.salem@email.com',
                'PersonalPhoto' => 'photos/omar.jpg',
                'Location' => 'تعز، اليمن',
                'ProfileSummary' => 'محاسب معتمد مع خبرة 5 سنوات في المحاسبة المالية وإعداد التقارير. حاصل على شهادة CPA.',
            ],
            [
                'email' => 'noura.khaled@email.com',
                'PersonalPhoto' => 'photos/noura.jpg',
                'Location' => 'صنعاء، اليمن',
                'ProfileSummary' => 'متخصصة في التسويق الرقمي مع خبرة 2 سنوات في إدارة حملات السوشيال ميديا وتحسين محركات البحث.',
            ],
            [
                'email' => 'yasser.abdulrahman@email.com',
                'PersonalPhoto' => 'photos/yasser.jpg',
                'Location' => 'الحديدة، اليمن',
                'ProfileSummary' => 'مهندس برمجيات مع خبرة 4 سنوات في تطوير تطبيقات الموبايل باستخدام Flutter و React Native.',
            ],
            [
                'email' => 'huda.mohammed@email.com',
                'PersonalPhoto' => 'photos/huda.jpg',
                'Location' => 'عدن، اليمن',
                'ProfileSummary' => 'خريجة حديثة في إدارة الأعمال أبحث عن فرصة للدخول في مجال الموارد البشرية أو إدارة المشاريع.',
            ],
        ];

        foreach ($profiles as $profile) {
            $user = User::where('Email', $profile['email'])->first();
            if ($user) {
                JobSeekerProfile::firstOrCreate(
                    ['JobSeekerID' => $user->UserID],
                    [
                        'JobSeekerID' => $user->UserID,
                        'PersonalPhoto' => $profile['PersonalPhoto'],
                        'Location' => $profile['Location'],
                        'ProfileSummary' => $profile['ProfileSummary'],
                    ]
                );
            }
        }
    }
}
