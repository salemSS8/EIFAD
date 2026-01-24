<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * Run: php artisan db:seed
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');

        // Phase 1: Base tables (no dependencies)
        $this->command->info('📋 Phase 1: Seeding base tables...');
        $this->call([
            RoleSeeder::class,
            SkillCategorySeeder::class,
            LanguageSeeder::class,
            CompanySpecializationSeeder::class,
            CourseSeeder::class,
        ]);

        // Phase 2: Skills (depends on SkillCategory)
        $this->command->info('💡 Phase 2: Seeding skills...');
        $this->call([
            SkillSeeder::class,
        ]);

        // Phase 3: Users & Companies
        $this->command->info('👤 Phase 3: Seeding users and companies...');
        $this->call([
            UserSeeder::class,
            UserRoleSeeder::class,
            JobSeekerProfileSeeder::class,
            CompanyProfileSeeder::class,
        ]);

        // Phase 4: CVs (depends on JobSeekerProfile)
        $this->command->info('📄 Phase 4: Seeding CVs...');
        $this->call([
            CVSeeder::class,
            EducationSeeder::class,
            ExperienceSeeder::class,
            CVSkillSeeder::class,
            CVLanguageSeeder::class,
        ]);

        // Phase 5: Jobs (depends on Company)
        $this->command->info('💼 Phase 5: Seeding jobs...');
        $this->call([
            JobAdSeeder::class,
            JobSkillSeeder::class,
            JobApplicationSeeder::class,
            FavoriteJobSeeder::class,
        ]);

        // Phase 6: Courses
        $this->command->info('📚 Phase 6: Seeding courses...');
        $this->call([
            CourseAdSeeder::class,
        ]);

        // Phase 7: Notifications
        $this->command->info('🔔 Phase 7: Seeding notifications...');
        $this->call([
            NotificationSeeder::class,
        ]);

        $this->command->info('✅ Database seeding completed!');
    }
}
