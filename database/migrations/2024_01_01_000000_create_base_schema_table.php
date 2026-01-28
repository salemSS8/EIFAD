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
        Schema::create('user', function (Blueprint $table) {
            $table->id('UserID');
            $table->string('FullName');
            $table->string('Email')->unique();
            $table->string('PasswordHash')->nullable();
            $table->string('Phone')->nullable();
            $table->string('Gender')->nullable();
            $table->date('DateOfBirth')->nullable();
            $table->boolean('IsVerified')->default(false);
            $table->dateTime('CreatedAt')->useCurrent();
            $table->string('FirebaseUID')->nullable(); // Will be renamed to ProviderID
            $table->string('AuthProvider')->default('email');
            $table->string('Avatar')->nullable();
            $table->boolean('IsBlocked')->default(false);
            $table->dateTime('BlockedAt')->nullable();
            $table->string('BlockReason')->nullable();
        });

        // 2. Role Table
        Schema::create('role', function (Blueprint $table) {
            $table->id('RoleID');
            $table->string('RoleName')->unique();
        });

        // 3. UserRole Table
        Schema::create('userrole', function (Blueprint $table) {
            $table->unsignedBigInteger('UserID');
            $table->unsignedBigInteger('RoleID');
            $table->dateTime('AssignedAt')->useCurrent();

            $table->foreign('UserID')->references('UserID')->on('user')->onDelete('cascade');
            $table->foreign('RoleID')->references('RoleID')->on('role')->onDelete('cascade');
            $table->primary(['UserID', 'RoleID']);
        });

        // 4. JobSeekerProfile Table
        Schema::create('jobseekerprofile', function (Blueprint $table) {
            $table->unsignedBigInteger('JobSeekerID')->primary();
            $table->string('PersonalPhoto')->nullable();
            $table->string('Location')->nullable();
            $table->text('ProfileSummary')->nullable();

            $table->foreign('JobSeekerID')->references('UserID')->on('user')->onDelete('cascade');
        });

        // 5. CompanyProfile Table
        Schema::create('companyprofile', function (Blueprint $table) {
            $table->unsignedBigInteger('CompanyID')->primary();
            $table->string('CompanyName')->nullable(); // Often same as User.FullName but can differ
            $table->string('OrganizationName')->nullable();
            $table->text('Address')->nullable();
            $table->text('Description')->nullable();
            $table->string('LogoPath')->nullable();
            $table->string('WebsiteURL')->nullable();
            $table->integer('EstablishedYear')->nullable();
            $table->integer('EmployeeCount')->nullable();
            $table->string('FieldOfWork')->nullable();
            $table->boolean('IsCompanyVerified')->default(false);

            $table->foreign('CompanyID')->references('UserID')->on('user')->onDelete('cascade');
        });

        // 6. CV Table
        Schema::create('cv', function (Blueprint $table) {
            $table->id('CVID');
            $table->unsignedBigInteger('JobSeekerID');
            $table->string('Title');
            $table->text('PersonalSummary')->nullable();
            $table->dateTime('CreatedAt')->useCurrent();
            $table->dateTime('UpdatedAt')->useCurrent()->nullable();

            $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile')->onDelete('cascade');
        });

        // 7. JobAd Table
        Schema::create('jobad', function (Blueprint $table) {
            $table->id('JobAdID');
            $table->unsignedBigInteger('CompanyID');
            $table->string('Title');
            $table->text('Description')->nullable();
            $table->string('Status')->default('Open'); // Active/Open/Closed
            $table->dateTime('PostedAt')->useCurrent();

            // Other fields to pass validation or logic
            $table->text('Requirements')->nullable();
            $table->text('Responsibilities')->nullable();
            $table->integer('SalaryMin')->nullable();
            $table->integer('SalaryMax')->nullable();
            $table->string('Location')->nullable();
            $table->string('WorkplaceType')->nullable(); // Remote, Onsite...
            $table->string('WorkType')->nullable(); // Full-time...
            $table->string('Currency')->default('USD');

            $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile')->onDelete('cascade');
        });

        // 8. JobApplication Table
        Schema::create('jobapplication', function (Blueprint $table) {
            $table->id('ApplicationID');
            $table->unsignedBigInteger('JobAdID');
            $table->unsignedBigInteger('JobSeekerID');
            $table->unsignedBigInteger('CVID');
            $table->dateTime('AppliedAt')->useCurrent();
            $table->string('Status')->default('Pending');
            $table->string('Notes')->nullable();
            $table->integer('MatchScore')->nullable();

            $table->foreign('JobAdID')->references('JobAdID')->on('jobad')->onDelete('cascade');
            $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile')->onDelete('cascade');
            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
        });

        // 9. FollowCompany Table (needed for Follow Company logic)
        Schema::create('followcompany', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('JobSeekerID');
            $table->unsignedBigInteger('CompanyID');
            $table->dateTime('FollowedAt')->useCurrent();

            $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile')->onDelete('cascade');
            $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile')->onDelete('cascade');
        });

        // 16. Experience Table
        Schema::create('experience', function (Blueprint $table) {
            $table->id('ExperienceID');
            $table->unsignedBigInteger('CVID');
            $table->string('JobTitle')->nullable();
            $table->string('CompanyName')->nullable();
            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
        });

        // 17. Favorite Job Table
        Schema::create('favoritejob', function (Blueprint $table) {
            $table->id('FavoriteID');
            $table->unsignedBigInteger('JobSeekerID');
            $table->unsignedBigInteger('JobAdID');
            $table->dateTime('SavedAt')->useCurrent();

            $table->foreign('JobSeekerID')->references('JobSeekerID')->on('jobseekerprofile')->onDelete('cascade');
            $table->foreign('JobAdID')->references('JobAdID')->on('jobad')->onDelete('cascade');
        });

        // 18. Company Specialization Table
        Schema::create('companyspecialization', function (Blueprint $table) {
            $table->id('SpecID');
            $table->string('SpecName');
        });

        // 19. Company Profile Specialization Pivot
        Schema::create('companyprofilespecialization', function (Blueprint $table) {
            $table->unsignedBigInteger('CompanyID');
            $table->unsignedBigInteger('SpecID');

            $table->foreign('CompanyID')->references('CompanyID')->on('companyprofile')->onDelete('cascade');
            $table->foreign('SpecID')->references('SpecID')->on('companyspecialization')->onDelete('cascade');
            $table->primary(['CompanyID', 'SpecID']);
        });

        // 20. Skill Table
        Schema::create('skill', function (Blueprint $table) {
            $table->id('SkillID');
            $table->string('SkillName');
            // CategoryID if needed
        });

        // 21. Job Skill Pivot
        Schema::create('jobskill', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('JobAdID');
            $table->unsignedBigInteger('SkillID');
            $table->string('RequiredLevel')->nullable();
            $table->boolean('IsMandatory')->default(false);

            $table->foreign('JobAdID')->references('JobAdID')->on('jobad')->onDelete('cascade');
            $table->foreign('SkillID')->references('SkillID')->on('skill')->onDelete('cascade');
        });

        // 22. CV Skill Pivot
        Schema::create('cvskill', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('CVID');
            $table->unsignedBigInteger('SkillID');
            $table->string('Level')->nullable();

            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
            $table->foreign('SkillID')->references('SkillID')->on('skill')->onDelete('cascade');
        });

        // 23. CV Job Match Table
        Schema::create('cvjobmatch', function (Blueprint $table) {
            $table->id('MatchID');
            $table->unsignedBigInteger('CVID');
            $table->unsignedBigInteger('JobAdID');
            $table->integer('MatchScore')->nullable();
            $table->dateTime('MatchDate')->useCurrent();

            // AI Fields (nullable)
            $table->string('CompatibilityLevel')->nullable();
            $table->text('Explanation')->nullable();

            $table->foreign('CVID')->references('CVID')->on('cv')->onDelete('cascade');
            $table->foreign('JobAdID')->references('JobAdID')->on('jobad')->onDelete('cascade');
        });

        // 15. Tokens Table (for verification/reset)
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // 7. Personal Access Tokens (Sanctum)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        // 24. Conversation Table
        Schema::create('conversation', function (Blueprint $table) {
            $table->id('ConversationID');
            $table->dateTime('LatestMessageAt')->nullable();
        });

        // 25. Conversation Participant Table
        Schema::create('conversationparticipant', function (Blueprint $table) {
            $table->id('ParticipantID');
            $table->unsignedBigInteger('ConversationID');
            $table->unsignedBigInteger('UserID');
            $table->dateTime('JoinedAt')->useCurrent();

            $table->foreign('ConversationID')->references('ConversationID')->on('conversation')->onDelete('cascade');
            $table->foreign('UserID')->references('UserID')->on('user')->onDelete('cascade');
        });

        // 26. Message Table
        Schema::create('message', function (Blueprint $table) {
            $table->id('MessageID');
            $table->unsignedBigInteger('ConversationID');
            $table->unsignedBigInteger('SenderID');
            $table->text('Content');
            $table->dateTime('SentAt')->useCurrent();
            $table->boolean('IsDeleted')->default(false);

            $table->foreign('ConversationID')->references('ConversationID')->on('conversation')->onDelete('cascade');
            $table->foreign('SenderID')->references('UserID')->on('user')->onDelete('cascade');
        });

        // 27. Notification Table
        Schema::create('notification', function (Blueprint $table) {
            $table->id('NotificationID');
            $table->unsignedBigInteger('UserID');
            $table->string('Type');
            $table->text('Data');
            $table->dateTime('ReadAt')->nullable();
            $table->dateTime('CreatedAt')->useCurrent();

            $table->foreign('UserID')->references('UserID')->on('user')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification');
        Schema::dropIfExists('message');
        Schema::dropIfExists('conversationparticipant');
        Schema::dropIfExists('conversation');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('email_verification_tokens');
        Schema::dropIfExists('cvjobmatch');
        Schema::dropIfExists('cvskill');
        Schema::dropIfExists('jobskill');
        Schema::dropIfExists('skill');
        Schema::dropIfExists('companyprofilespecialization');
        Schema::dropIfExists('companyspecialization');
        Schema::dropIfExists('favoritejob');
        Schema::dropIfExists('experience');
        Schema::dropIfExists('followcompany');
        Schema::dropIfExists('jobapplication');
        Schema::dropIfExists('jobad');
        Schema::dropIfExists('cv');
        Schema::dropIfExists('companyprofile');
        Schema::dropIfExists('jobseekerprofile');
        Schema::dropIfExists('userrole');
        Schema::dropIfExists('role');
        Schema::dropIfExists('user');
    }
};
