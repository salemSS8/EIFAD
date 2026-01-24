<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\User\Models\User;
use App\Domain\Communication\Models\Notification;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $notificationTemplates = [
            ['Type' => 'application_status', 'Content' => 'تم تحديث حالة طلبك للوظيفة. تحقق من التفاصيل.'],
            ['Type' => 'new_job_match', 'Content' => 'وظيفة جديدة تتطابق مع ملفك الشخصي! اطلع عليها الآن.'],
            ['Type' => 'profile_view', 'Content' => 'شاهدت شركة ملفك الشخصي.'],
            ['Type' => 'message', 'Content' => 'لديك رسالة جديدة.'],
            ['Type' => 'reminder', 'Content' => 'لا تنس تحديث سيرتك الذاتية!'],
        ];

        foreach ($users as $user) {
            // Create 1-3 notifications per user
            $count = rand(1, 3);
            for ($i = 0; $i < $count; $i++) {
                $template = $notificationTemplates[array_rand($notificationTemplates)];
                Notification::firstOrCreate(
                    ['UserID' => $user->UserID, 'Content' => $template['Content']],
                    [
                        'UserID' => $user->UserID,
                        'Type' => $template['Type'],
                        'Content' => $template['Content'],
                        'IsRead' => rand(0, 1),
                        'CreatedAt' => now()->subDays(rand(0, 10)),
                    ]
                );
            }
        }
    }
}
