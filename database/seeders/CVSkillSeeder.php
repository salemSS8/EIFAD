<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVSkill;
use App\Domain\Skill\Models\Skill;

class CVSkillSeeder extends Seeder
{
    public function run(): void
    {
        $cvSkillMappings = [
            // Web Developer CV
            1 => ['PHP', 'Laravel', 'JavaScript', 'React', 'MySQL', 'Git', 'Docker'],
            // Designer CV
            2 => ['Photoshop', 'Illustrator', 'Figma', 'UI/UX Design', 'Adobe XD'],
            // Accountant CV
            3 => ['المحاسبة المالية', 'Excel المتقدم', 'SAP', 'الضرائب'],
            // Marketing CV
            4 => ['التسويق الرقمي', 'SEO', 'Google Ads', 'Facebook Ads', 'إدارة وسائل التواصل'],
            // Mobile Developer CV
            5 => ['Flutter', 'React Native', 'Kotlin', 'Swift', 'Git'],
        ];

        $levels = ['مبتدئ', 'متوسط', 'متقدم', 'خبير'];

        $cvs = CV::all();

        CVSkill::unguard();
        $cvSkillId = 1;

        foreach ($cvs as $cv) {
            if (isset($cvSkillMappings[$cv->CVID])) {
                foreach ($cvSkillMappings[$cv->CVID] as $index => $skillName) {
                    $skill = Skill::where('SkillName', $skillName)->first();
                    if ($skill) {
                        CVSkill::firstOrCreate(
                            ['CVID' => $cv->CVID, 'SkillID' => $skill->SkillID],
                            [
                                'CVSkillID' => $cvSkillId++,
                                'CVID' => $cv->CVID,
                                'SkillID' => $skill->SkillID,
                                'SkillLevel' => $levels[min($index, 3)],
                            ]
                        );
                    }
                }
            }
        }
        CVSkill::reguard();
    }
}
