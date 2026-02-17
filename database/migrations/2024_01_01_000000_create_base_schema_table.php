<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. User Table
        if (!Schema::hasTable('user')) {
            Schema::create('user', function (Blueprint $table) {
                $table->integer('UserID')->autoIncrement();
                $table->string('FullName')->nullable();
                $table->string('Email')->nullable()->unique();
                $table->string('ProviderID')->nullable()->unique();
                $table->string('PasswordHash')->nullable();
                $table->string('Phone')->nullable();
                $table->string('Gender')->nullable();
                $table->date('DateOfBirth')->nullable();
                $table->boolean('IsVerified')->nullable(); // Schema says tinyint(1), nullable
                $table->string('AuthProvider')->default('email');
                $table->string('Avatar')->nullable();
                $table->dateTime('CreatedAt')->nullable();
                $table->boolean('IsBlocked')->default(false);
                $table->dateTime('BlockedAt')->nullable();
                $table->string('BlockReason', 500)->nullable();
            });
        }

        // 2. Role Table
        if (!Schema::hasTable('role')) {
            Schema::create('role', function (Blueprint $table) {
                $table->integer('RoleID')->autoIncrement();
                $table->string('RoleName')->nullable();
            });
        }

        // 3. UserRole Table
        if (!Schema::hasTable('userrole')) {
            Schema::create('userrole', function (Blueprint $table) {
                $table->integer('UserRoleID')->autoIncrement();
                $table->integer('UserID')->nullable();
                $table->integer('RoleID')->nullable();
                $table->dateTime('AssignedAt')->nullable();

                $table->foreign('UserID')->references('UserID')->on('user')->nullOnDelete();
                $table->foreign('RoleID')->references('RoleID')->on('role')->nullOnDelete();
            });
        }

        // 4. JobSeekerProfile Table
        if (!Schema::hasTable('jobseekerprofile')) {
            Schema::create('jobseekerprofile', function (Blueprint $table) {
                $table->integer('JobSeekerID')->primary();
                $table->string('PersonalPhoto')->nullable();
                $table->string('Location')->nullable();
                $table->text('ProfileSummary')->nullable();

                $table->foreign('JobSeekerID')->references('UserID')->on('user')->onDelete('cascade');
            });
        }

        // 5. CompanyProfile Table
        if (!Schema::hasTable('companyprofile')) {
            Schema::create('companyprofile', function (Blueprint $table) {
                $table->integer('CompanyID')->primary();
                $table->string('CompanyName')->nullable();
                $table->string('OrganizationName')->nullable();
                $table->string('Address')->nullable();
                $table->text('Description')->nullable();
                $table->string('LogoPath')->nullable();
                $table->string('WebsiteURL')->nullable();
                $table->integer('EstablishedYear')->nullable();
                $table->integer('EmployeeCount')->nullable();
                $table->string('FieldOfWork')->nullable();
                $table->boolean('IsCompanyVerified')->nullable();

                $table->foreign('CompanyID')->references('UserID')->on('user')->onDelete('cascade');
            });
        }

        // 6. CV Table
        if (!Schema::hasTable('cv')) {
            Schema::create('cv', function (Blueprint $table) {
                $table->integer('CVID')->autoIncrement();
                $table->integer('JobSeekerID')->nullable();
                $table->string('Title')->nullable();
                $table->text('PersonalSummary')->nullable();
                $table->dateTime('CreatedAt')->nullable();
                $table->dateTime('UpdatedAt')->nullable();

                $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile');
            });
        }

        // 7. JobAd Table
        if (!Schema::hasTable('jobad')) {
            Schema::create('jobad', function (Blueprint $table) {
                $table->integer('JobAdID')->autoIncrement();
                $table->integer('CompanyID')->nullable();
                $table->string('Title')->nullable();
                $table->text('Description')->nullable();
                $table->text('Responsibilities')->nullable();
                $table->text('Requirements')->nullable();
                $table->string('Location')->nullable();
                $table->string('WorkplaceType')->nullable();
                $table->string('WorkType')->nullable();
                $table->integer('SalaryMin')->nullable();
                $table->integer('SalaryMax')->nullable();
                $table->string('Currency')->nullable();
                $table->dateTime('PostedAt')->nullable();
                $table->string('Status')->nullable();

                $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile');
            });
        }

        // 8. JobApplication Table
        if (!Schema::hasTable('jobapplication')) {
            Schema::create('jobapplication', function (Blueprint $table) {
                $table->integer('ApplicationID')->autoIncrement();
                $table->integer('JobAdID')->nullable();
                $table->integer('JobSeekerID')->nullable();
                $table->integer('CVID')->nullable();
                $table->dateTime('AppliedAt')->nullable();
                $table->string('Status')->nullable();
                $table->integer('MatchScore')->nullable();
                $table->text('Notes')->nullable();

                $table->foreign('JobAdID')->references('JobAdID')->on('jobad');
                $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile');
                $table->foreign('CVID')->references('CVID')->on('cv');
            });
        }

        // 9. FollowCompany Table
        if (!Schema::hasTable('followcompany')) {
            Schema::create('followcompany', function (Blueprint $table) {
                $table->integer('FollowID')->autoIncrement();
                $table->integer('JobSeekerID')->nullable();
                $table->integer('CompanyID')->nullable();
                $table->dateTime('FollowedAt')->nullable();

                $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile');
                $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile');
            });
        }

        // 10. Language Table
        if (!Schema::hasTable('language')) {
            Schema::create('language', function (Blueprint $table) {
                $table->integer('LanguageID')->autoIncrement();
                $table->string('LanguageName')->nullable();
            });
        }

        // 11. CV Language Table
        if (!Schema::hasTable('cvlanguage')) {
            Schema::create('cvlanguage', function (Blueprint $table) {
                $table->integer('CVLanguageID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->integer('LanguageID')->nullable();
                $table->string('LanguageLevel')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
                $table->foreign('LanguageID')->references('LanguageID')->on('language');
            });
        }

        // 12. Experience Table
        if (!Schema::hasTable('experience')) {
            Schema::create('experience', function (Blueprint $table) {
                $table->integer('ExperienceID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->string('JobTitle')->nullable();
                $table->string('CompanyName')->nullable();
                $table->date('StartDate')->nullable();
                $table->date('EndDate')->nullable();
                $table->text('Responsibilities')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
            });
        }

        // 13. Education Table
        if (!Schema::hasTable('education')) {
            Schema::create('education', function (Blueprint $table) {
                $table->integer('EducationID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->string('Institution')->nullable();
                $table->string('DegreeName')->nullable();
                $table->string('Major')->nullable();
                $table->integer('GraduationYear')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
            });
        }

        // 14. SkillCategory Table
        if (!Schema::hasTable('skillcategory')) {
            Schema::create('skillcategory', function (Blueprint $table) {
                $table->integer('CategoryID')->autoIncrement();
                $table->string('CategoryName', 100)->nullable();
            });
        }

        // 15. Skill Table
        if (!Schema::hasTable('skill')) {
            Schema::create('skill', function (Blueprint $table) {
                $table->integer('SkillID')->autoIncrement();
                $table->string('SkillName')->nullable();
                $table->integer('CategoryID')->nullable();

                $table->foreign('CategoryID')->references('CategoryID')->on('skillcategory');
            });
        }

        // 16. JobSkill Table
        if (!Schema::hasTable('jobskill')) {
            Schema::create('jobskill', function (Blueprint $table) {
                $table->integer('JobSkillID')->autoIncrement();
                $table->integer('JobAdID');
                $table->integer('SkillID');
                $table->string('RequiredLevel', 50)->nullable();
                $table->integer('ImportanceWeight')->nullable();
                $table->boolean('IsMandatory')->nullable();

                $table->foreign('JobAdID')->references('JobAdID')->on('jobad');
                $table->foreign('SkillID')->references('SkillID')->on('skill');
            });
        }

        // 17. CVSkill Table
        if (!Schema::hasTable('cvskill')) {
            Schema::create('cvskill', function (Blueprint $table) {
                $table->integer('CVSkillID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->integer('SkillID')->nullable();
                $table->string('SkillLevel')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
                $table->foreign('SkillID')->references('SkillID')->on('skill');
            });
        }

        // 18. FavoriteJob Table
        if (!Schema::hasTable('favoritejob')) {
            Schema::create('favoritejob', function (Blueprint $table) {
                $table->integer('FavoriteID')->autoIncrement();
                $table->integer('JobSeekerID')->nullable();
                $table->integer('JobAdID')->nullable();
                $table->dateTime('SavedAt')->nullable();

                $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile');
                $table->foreign('JobAdID')->references('JobAdID')->on('jobad');
            });
        }

        // 19. CompanySpecialization Table
        if (!Schema::hasTable('companyspecialization')) {
            Schema::create('companyspecialization', function (Blueprint $table) {
                $table->integer('SpecID')->autoIncrement();
                $table->string('SpecName')->nullable();
            });
        }

        // 20. CompanyProfileSpecialization Table
        if (!Schema::hasTable('companyprofilespecialization')) {
            Schema::create('companyprofilespecialization', function (Blueprint $table) {
                $table->integer('CompanySpecID')->autoIncrement();
                $table->integer('CompanyID')->nullable();
                $table->integer('SpecID')->nullable();

                $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile');
                $table->foreign('SpecID')->references('SpecID')->on('companyspecialization');
            });
        }

        // 21. Content Table (Articles/Posts)
        if (!Schema::hasTable('content')) {
            Schema::create('content', function (Blueprint $table) {
                $table->integer('ContentID')->autoIncrement();
                $table->integer('AuthorUserID')->nullable();
                $table->string('Title')->nullable();
                $table->text('BodyText')->nullable();
                $table->string('ImagePath')->nullable();
                $table->string('VideoPath')->nullable();
                $table->dateTime('CreatedAt')->nullable();
                $table->string('Status')->nullable();

                $table->foreign('AuthorUserID')->references('UserID')->on('user');
            });
        }

        // 22. Conversation Table
        if (!Schema::hasTable('conversation')) {
            Schema::create('conversation', function (Blueprint $table) {
                $table->integer('ConversationID')->autoIncrement();
                $table->string('Type')->nullable();
                $table->dateTime('CreatedAt')->nullable();
            });
        }

        // 23. ConversationParticipant Table
        if (!Schema::hasTable('conversationparticipant')) {
            Schema::create('conversationparticipant', function (Blueprint $table) {
                $table->integer('ParticipantID')->autoIncrement();
                $table->integer('ConversationID')->nullable();
                $table->integer('UserID')->nullable();
                $table->dateTime('JoinedAt')->nullable();
                $table->boolean('IsMuted')->nullable();
                $table->boolean('IsBlocked')->nullable();

                $table->foreign('ConversationID')->references('ConversationID')->on('conversation');
                $table->foreign('UserID')->references('UserID')->on('user');
            });
        }

        // 24. Message Table
        if (!Schema::hasTable('message')) {
            Schema::create('message', function (Blueprint $table) {
                $table->integer('MessageID')->autoIncrement();
                $table->integer('ConversationID')->nullable();
                $table->integer('SenderID')->nullable();
                $table->text('Content')->nullable();
                $table->dateTime('SentAt')->nullable();
                $table->boolean('IsDeleted')->nullable();

                $table->foreign('ConversationID')->references('ConversationID')->on('conversation');
                $table->foreign('SenderID')->references('UserID')->on('user');
            });
        }

        // 25. MessageRead Table
        if (!Schema::hasTable('messageread')) {
            Schema::create('messageread', function (Blueprint $table) {
                $table->integer('ReadID')->autoIncrement();
                $table->integer('MessageID')->nullable();
                $table->integer('UserID')->nullable();
                $table->dateTime('ReadAt')->nullable();

                $table->foreign('MessageID')->references('MessageID')->on('message');
                $table->foreign('UserID')->references('UserID')->on('user');
            });
        }

        // 26. Notification Table
        if (!Schema::hasTable('notification')) {
            Schema::create('notification', function (Blueprint $table) {
                $table->integer('NotificationID')->autoIncrement();
                $table->integer('UserID')->nullable();
                $table->string('Type')->nullable();
                $table->text('Content')->nullable();
                $table->boolean('IsRead')->nullable();
                $table->dateTime('CreatedAt')->nullable();

                $table->foreign('UserID')->references('UserID')->on('user');
            });
        }

        // 27. Course Table (Lookup?)
        if (!Schema::hasTable('course')) {
            Schema::create('course', function (Blueprint $table) {
                $table->integer('CourseID')->autoIncrement();
                $table->string('CourseName')->nullable();
            });
        }

        // 28. CourseAd Table
        if (!Schema::hasTable('coursead')) {
            Schema::create('coursead', function (Blueprint $table) {
                $table->integer('CourseAdID')->autoIncrement();
                $table->integer('CompanyID')->nullable();
                $table->string('CourseTitle')->nullable();
                $table->text('Topics')->nullable();
                $table->string('Duration')->nullable();
                $table->string('Location')->nullable();
                $table->string('Trainer')->nullable();
                $table->integer('Fees')->nullable();
                $table->date('StartDate')->nullable();
                $table->dateTime('CreatedAt')->nullable();
                $table->string('Status')->nullable();

                $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile');
            });
        }

        // 29. CourseEnrollment Table
        if (!Schema::hasTable('courseenrollment')) {
            Schema::create('courseenrollment', function (Blueprint $table) {
                $table->integer('EnrollmentID')->autoIncrement();
                $table->integer('CourseAdID')->nullable();
                $table->integer('JobSeekerID')->nullable();
                $table->dateTime('EnrolledAt')->nullable();
                $table->string('Status')->nullable();

                $table->foreign('CourseAdID')->references('CourseAdID')->on('coursead');
                $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile');
            });
        }

        // 30. CVCourse Table
        if (!Schema::hasTable('cvcourse')) {
            Schema::create('cvcourse', function (Blueprint $table) {
                $table->integer('CVCourseID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->integer('CourseID')->nullable();
                $table->string('PlaceTaken')->nullable();
                $table->date('DateTaken')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
                $table->foreign('CourseID')->references('CourseID')->on('course');
            });
        }

        // 31. Volunteering Table
        if (!Schema::hasTable('volunteering')) {
            Schema::create('volunteering', function (Blueprint $table) {
                $table->integer('VolunteeringID')->autoIncrement();
                $table->integer('CVID')->nullable();
                $table->string('Title')->nullable();
                $table->text('Description')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
            });
        }

        // 32. Verification & Reset Tables
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('email_verification_tokens')) {
            Schema::create('email_verification_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // 33. AI Tables
        if (!Schema::hasTable('cvjobmatch')) {
            Schema::create('cvjobmatch', function (Blueprint $table) {
                $table->integer('MatchID')->autoIncrement();
                $table->integer('CVID');
                $table->integer('JobAdID');
                $table->integer('MatchScore')->nullable();
                $table->timestamp('MatchDate')->nullable();
                $table->string('CompatibilityLevel')->nullable();
                $table->integer('SkillMatchScore')->nullable();
                $table->integer('ExperienceMatchScore')->nullable();
                $table->dateTime('CalculatedAt')->nullable();
                $table->integer('SkillsScore')->nullable();
                $table->integer('ExperienceScore')->nullable();
                $table->integer('EducationScore')->nullable();
                $table->json('ScoreBreakdown')->nullable();
                $table->string('ScoringMethod')->nullable();
                $table->text('Explanation')->nullable();
                $table->json('Strengths')->nullable();
                $table->json('Gaps')->nullable();
                $table->string('AIModel')->nullable();
                $table->timestamp('ExplainedAt')->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
                $table->foreign('JobAdID')->references('JobAdID')->on('jobad');
            });
        }

        if (!Schema::hasTable('matchdetail')) {
            Schema::create('matchdetail', function (Blueprint $table) {
                $table->integer('MatchDetailID')->autoIncrement();
                $table->integer('MatchID');
                $table->integer('SkillID');
                $table->string('CVLevel', 50)->nullable();
                $table->string('RequiredLevel', 50)->nullable();
                $table->boolean('IsMatched')->nullable();

                $table->foreign('MatchID')->references('MatchID')->on('cvjobmatch');
                $table->foreign('SkillID')->references('SkillID')->on('skill');
            });
        }

        if (!Schema::hasTable('skillgapanalysis')) {
            Schema::create('skillgapanalysis', function (Blueprint $table) {
                $table->integer('GapID')->autoIncrement();
                $table->integer('CVID');
                $table->integer('JobAdID');
                $table->integer('SkillID');
                $table->string('CVLevel', 50)->nullable();
                $table->string('RequiredLevel', 50)->nullable();
                $table->string('GapType', 50)->nullable();

                $table->foreign('CVID')->references('CVID')->on('cv');
                $table->foreign('JobAdID')->references('JobAdID')->on('jobad');
                $table->foreign('SkillID')->references('SkillID')->on('skill');
            });
        }

        if (!Schema::hasTable('skilldemandsnapshot')) {
            Schema::create('skilldemandsnapshot', function (Blueprint $table) {
                $table->integer('SnapshotID')->autoIncrement();
                $table->integer('SkillID');
                $table->integer('DemandCount')->nullable();
                $table->date('SnapshotDate')->nullable();

                $table->foreign('SkillID')->references('SkillID')->on('skill');
            });
        }

        if (!Schema::hasTable('analysisresult')) {
            Schema::create('analysisresult', function (Blueprint $table) {
                $table->integer('AnalysisID')->autoIncrement();
                $table->string('TargetType')->nullable();
                $table->integer('TargetID')->nullable();
                $table->integer('UserID')->nullable();
                $table->text('ResultText')->nullable();
                $table->integer('Score')->nullable();
                $table->string('ModelVersion')->nullable();
                $table->dateTime('CreatedAt')->nullable();

                $table->foreign('UserID')->references('UserID')->on('user');
            });
        }

        if (!Schema::hasTable('cv_analyses')) {
            Schema::create('cv_analyses', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('CVID')->nullable(); // Legacy
                $table->bigInteger('cv_id')->unique();
                $table->json('personal_info')->nullable();
                $table->text('summary')->nullable();
                $table->json('skills')->nullable();
                $table->json('experience')->nullable();
                $table->json('education')->nullable();
                $table->json('certifications')->nullable();
                $table->json('languages')->nullable();
                $table->integer('overall_score')->nullable();
                $table->json('strengths')->nullable();
                $table->json('areas_for_improvement')->nullable();
                $table->longText('raw_response')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->string('analysis_version')->default('1.0');
                $table->timestamps();

                // Extra fields from dump (Laravel convention mixed with project convention)
                $table->integer('OverallScore')->nullable();
                $table->integer('SkillsScore')->nullable();
                $table->integer('ExperienceScore')->nullable();
                $table->integer('EducationScore')->nullable();
                $table->integer('CompletenessScore')->nullable();
                $table->integer('ConsistencyScore')->nullable();
                $table->json('ScoreBreakdown')->nullable();
                $table->string('ScoringMethod')->nullable();
                $table->timestamp('ScoredAt')->nullable();
                $table->json('PotentialGaps')->nullable();
                $table->json('ImprovementRecommendations')->nullable();
                $table->text('AIExplanation')->nullable();
                $table->string('AIModel')->nullable();
                $table->timestamp('ExplainedAt')->nullable();

                // fk
                // $table->foreign('cv_id')->references('id')->on('cvs'); // cvs table?
            });
        }

        // 34. Other Tables present in dump
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->unique();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->string('cover_image')->nullable();
                $table->string('website')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('industry')->nullable();
                $table->enum('company_size', ['1-10', '11-50', '51-200', '201-500', '501-1000', '1001-5000', '5001+'])->nullable();
                $table->year('founded_year')->nullable();
                $table->string('linkedin_url')->nullable();
                $table->string('twitter_url')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->json('verification_documents')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('UserID')->on('user'); // users table?
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order of dependency
        Schema::dropIfExists('analysisresult');
        Schema::dropIfExists('skilldemandsnapshot');
        Schema::dropIfExists('skillgapanalysis');
        Schema::dropIfExists('matchdetail');
        Schema::dropIfExists('cvjobmatch');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('email_verification_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('volunteering');
        Schema::dropIfExists('cvcourse');
        Schema::dropIfExists('courseenrollment');
        Schema::dropIfExists('coursead');
        Schema::dropIfExists('course');
        Schema::dropIfExists('notification');
        Schema::dropIfExists('messageread');
        Schema::dropIfExists('message');
        Schema::dropIfExists('conversationparticipant');
        Schema::dropIfExists('conversation');
        Schema::dropIfExists('content');
        Schema::dropIfExists('companyprofilespecialization');
        Schema::dropIfExists('companyspecialization');
        Schema::dropIfExists('favoritejob');
        Schema::dropIfExists('cvskill');
        Schema::dropIfExists('jobskill');
        Schema::dropIfExists('skill');
        Schema::dropIfExists('skillcategory');
        Schema::dropIfExists('education');
        Schema::dropIfExists('experience');
        Schema::dropIfExists('cvlanguage');
        Schema::dropIfExists('language');
        Schema::dropIfExists('followcompany');
        Schema::dropIfExists('jobapplication');
        Schema::dropIfExists('jobad');
        Schema::dropIfExists('cv');
        Schema::dropIfExists('companyprofile');
        Schema::dropIfExists('jobseekerprofile');
        Schema::dropIfExists('userrole');
        Schema::dropIfExists('role');
        Schema::dropIfExists('user');

        // Extra drops from sync
        Schema::dropIfExists('cv_analyses');
        Schema::dropIfExists('companies');
    }
};
