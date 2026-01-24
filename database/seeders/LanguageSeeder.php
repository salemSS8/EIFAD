<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Skill\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['LanguageName' => 'العربية'],
            ['LanguageName' => 'الإنجليزية'],
            ['LanguageName' => 'الفرنسية'],
            ['LanguageName' => 'الألمانية'],
            ['LanguageName' => 'الإسبانية'],
            ['LanguageName' => 'التركية'],
            ['LanguageName' => 'الصينية'],
            ['LanguageName' => 'اليابانية'],
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(['LanguageName' => $language['LanguageName']], $language);
        }
    }
}
