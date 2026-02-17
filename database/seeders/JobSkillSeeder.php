<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Job\Models\JobAd;
use App\Domain\Job\Models\JobSkill;
use App\Domain\Skill\Models\Skill;

class JobSkillSeeder extends Seeder
{
    public function run(): void
    {
        $jobSkillMappings = [
            'مطور Laravel أول' => ['PHP', 'Laravel', 'MySQL', 'Git', 'Vue.js'],
            'مطور React Frontend' => ['JavaScript', 'React', 'Git'],
            'مهندس DevOps' => ['Docker', 'AWS', 'Linux', 'Git'],
            'أخصائي موارد بشرية' => ['إدارة الفرق', 'Excel المتقدم'],
            'محاسب مالي' => ['المحاسبة المالية', 'Excel المتقدم', 'SAP'],
            'مسؤول تسويق' => ['التسويق الرقمي', 'إدارة وسائل التواصل', 'SEO'],
            'مدرب برمجة' => ['PHP', 'Laravel', 'JavaScript'],
            'مصمم جرافيك' => ['Photoshop', 'Illustrator', 'Figma'],
            'أخصائي محتوى تعليمي' => ['التسويق بالمحتوى'],
        ];

        $levels = ['مبتدئ', 'متوسط', 'متقدم'];

        JobSkill::unguard();
        $jobSkillId = 1;

        foreach ($jobSkillMappings as $jobTitle => $skills) {
            $job = JobAd::where('Title', $jobTitle)->first();
            if ($job) {
                foreach ($skills as $index => $skillName) {
                    $skill = Skill::where('SkillName', $skillName)->first();
                    if ($skill) {
                        JobSkill::firstOrCreate(
                            ['JobAdID' => $job->JobAdID, 'SkillID' => $skill->SkillID],
                            [
                                'JobSkillID' => $jobSkillId++,
                                'JobAdID' => $job->JobAdID,
                                'SkillID' => $skill->SkillID,
                                'RequiredLevel' => $levels[min($index, 2)],
                                'ImportanceWeight' => 100 - ($index * 15),
                                'IsMandatory' => $index < 2,
                            ]
                        );
                    }
                }
            }
        }
        JobSkill::reguard();
    }
}
