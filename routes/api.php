<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CVController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CourseController;

/*
|--------------------------------------------------------------------------
| API Routes for Job Search Platform
|--------------------------------------------------------------------------
|
| Routes are organized by domain and match the database schema exactly.
|
*/

// =========================================
// Public Routes (No Authentication)
// =========================================

// Health Check
Route::get('/health', fn() => response()->json([
    'status' => 'ok',
    'timestamp' => now(),
    'database' => 'final_project_database',
]));

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Social Authentication
    Route::get('/login/{provider}', [AuthController::class, 'redirectToProvider']);
    Route::get('/login/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

    // Password Reset
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Account Verification
    Route::post('/send-verification', [AuthController::class, 'sendVerification']);
    Route::post('/verify-account', [AuthController::class, 'verifyAccount']);
});

// Public Job Listings
Route::prefix('jobs')->group(function () {
    Route::get('/', [JobController::class, 'index']);
    Route::get('/{id}', [JobController::class, 'show']);
});

// Skills & Languages (for dropdowns)
Route::get('/skills', [SkillController::class, 'index']);
Route::get('/skill-categories', [SkillController::class, 'categories']);
Route::get('/languages', [SkillController::class, 'languages']);

// =========================================
// Protected Routes (Require Authentication)
// =========================================

Route::middleware('auth:sanctum')->group(function () {

    // ------- Auth -------
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/set-role', [AuthController::class, 'setRole']);
    });

    // ------- Profile -------
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::post('/', [ProfileController::class, 'store']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::delete('/', [ProfileController::class, 'destroy']);
    });

    // ------- Notifications -------
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });

    // =========================================
    // Job Seeker Routes
    // =========================================

    // CVs
    Route::prefix('cvs')->group(function () {
        Route::get('/', [CVController::class, 'index']);
        Route::post('/', [CVController::class, 'store']);
        Route::get('/{id}', [CVController::class, 'show']);
        Route::put('/{id}', [CVController::class, 'update']);
        Route::delete('/{id}', [CVController::class, 'destroy']);

        // CV Skills
        Route::post('/{cvId}/skills', [CVController::class, 'addSkill']);
        Route::delete('/{cvId}/skills/{skillId}', [CVController::class, 'removeSkill']);

        // CV Education
        Route::post('/{cvId}/education', [CVController::class, 'addEducation']);
        Route::delete('/{cvId}/education/{educationId}', [CVController::class, 'removeEducation']);

        // CV Experience
        Route::post('/{cvId}/experience', [CVController::class, 'addExperience']);
        Route::delete('/{cvId}/experience/{experienceId}', [CVController::class, 'removeExperience']);

        // CV Languages
        Route::post('/{cvId}/languages', [CVController::class, 'addLanguage']);
        Route::delete('/{cvId}/languages/{languageId}', [CVController::class, 'removeLanguage']);
    });

    // Favorites
    Route::prefix('favorites')->group(function () {
        Route::get('/', [JobController::class, 'favorites']);
        Route::post('/{jobId}', [JobController::class, 'addFavorite']);
        Route::delete('/{jobId}', [JobController::class, 'removeFavorite']);
    });

    // Applications (Job Seeker)
    Route::prefix('applications')->group(function () {
        Route::get('/', [ApplicationController::class, 'index']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/{id}', [ApplicationController::class, 'show']);
        Route::post('/{id}/withdraw', [ApplicationController::class, 'withdraw']);
    });

    // =========================================
    // Employer Routes
    // =========================================

    Route::prefix('employer')->group(function () {

        // Job Management
        Route::prefix('jobs')->group(function () {
            Route::get('/', [JobController::class, 'employerJobs']);
            Route::post('/', [JobController::class, 'store']);
            Route::put('/{id}', [JobController::class, 'update']);
            Route::post('/{id}/publish', [JobController::class, 'publish']);
            Route::post('/{id}/close', [JobController::class, 'close']);
            Route::delete('/{id}', [JobController::class, 'destroy']);

            // Applications for a job
            Route::get('/{jobId}/applications', [ApplicationController::class, 'jobApplications']);
        });

        // Application Management
        Route::put('/applications/{id}/status', [ApplicationController::class, 'updateStatus']);
    });

    // =========================================
    // Follow Companies
    // =========================================

    Route::prefix('companies')->group(function () {
        Route::get('/', function () {
            return response()->json(
                \App\Domain\Company\Models\CompanyProfile::where('IsCompanyVerified', true)
                    ->paginate(15)
            );
        });

        Route::get('/{id}', function (int $id) {
            return response()->json([
                'data' => \App\Domain\Company\Models\CompanyProfile::with('jobAds')
                    ->where('CompanyID', $id)
                    ->firstOrFail()
            ]);
        });

        Route::post('/{id}/follow', function (\Illuminate\Http\Request $request, int $id) {
            $profile = $request->user()->jobSeekerProfile;
            if (!$profile) {
                return response()->json(['message' => 'Only job seekers can follow companies'], 403);
            }

            \App\Domain\Company\Models\FollowCompany::firstOrCreate([
                'JobSeekerID' => $profile->JobSeekerID,
                'CompanyID' => $id,
            ], ['FollowedAt' => now()]);

            return response()->json(['message' => 'Company followed']);
        });

        Route::delete('/{id}/follow', function (\Illuminate\Http\Request $request, int $id) {
            $profile = $request->user()->jobSeekerProfile;

            \App\Domain\Company\Models\FollowCompany::where('JobSeekerID', $profile->JobSeekerID)
                ->where('CompanyID', $id)
                ->delete();

            return response()->json(['message' => 'Unfollowed company']);
        });
    });

    // =========================================
    // Courses (Job Seeker)
    // =========================================

    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::get('/my-enrollments', [CourseController::class, 'myEnrollments']);
        Route::get('/{id}', [CourseController::class, 'show']);
        Route::post('/{id}/enroll', [CourseController::class, 'enroll']);
        Route::delete('/{id}/enroll', [CourseController::class, 'unenroll']);
    });

    // =========================================
    // Employer: Course Management
    // =========================================

    Route::prefix('employer/courses')->group(function () {
        Route::get('/', [CourseController::class, 'employerCourses']);
        Route::post('/', [CourseController::class, 'store']);
        Route::put('/{id}', [CourseController::class, 'update']);
        Route::post('/{id}/publish', [CourseController::class, 'publish']);
        Route::post('/{id}/close', [CourseController::class, 'close']);
        Route::delete('/{id}', [CourseController::class, 'destroy']);
        Route::get('/{id}/enrollments', [CourseController::class, 'enrollments']);
        Route::post('/{id}/notify', [CourseController::class, 'notifyParticipants']);
    });

    // =========================================
    // Admin Panel (Requires Admin Role)
    // =========================================

    Route::prefix('admin')->group(function () {
        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::get('/statistics', [AdminController::class, 'statistics']);
            Route::get('/{id}', [AdminController::class, 'show']);
            Route::post('/', [AdminController::class, 'store']);
            Route::put('/{id}', [AdminController::class, 'update']);
            Route::post('/{id}/block', [AdminController::class, 'block']);
            Route::post('/{id}/unblock', [AdminController::class, 'unblock']);
        });
    });

    // =========================================
    // Messaging (Basic)
    // =========================================

    Route::prefix('conversations')->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request) {
            $userId = $request->user()->UserID;

            return response()->json(
                \App\Domain\Communication\Models\Conversation::whereHas('participants', function ($q) use ($userId) {
                    $q->where('UserID', $userId);
                })
                    ->with(['participants.user:UserID,FullName', 'messages' => function ($q) {
                        $q->latest('SentAt')->limit(1);
                    }])
                    ->paginate(20)
            );
        });

        Route::get('/{id}/messages', function (\Illuminate\Http\Request $request, int $id) {
            $userId = $request->user()->UserID;

            // Verify user is participant
            $isParticipant = \App\Domain\Communication\Models\ConversationParticipant::where('ConversationID', $id)
                ->where('UserID', $userId)
                ->exists();

            if (!$isParticipant) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json(
                \App\Domain\Communication\Models\Message::with('sender:UserID,FullName')
                    ->where('ConversationID', $id)
                    ->where('IsDeleted', false)
                    ->orderByDesc('SentAt')
                    ->paginate(50)
            );
        });

        Route::post('/{id}/messages', function (\Illuminate\Http\Request $request, int $id) {
            $request->validate(['content' => 'required|string|max:5000']);

            $userId = $request->user()->UserID;

            // Verify user is participant
            $isParticipant = \App\Domain\Communication\Models\ConversationParticipant::where('ConversationID', $id)
                ->where('UserID', $userId)
                ->exists();

            if (!$isParticipant) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $message = \App\Domain\Communication\Models\Message::create([
                'ConversationID' => $id,
                'SenderID' => $userId,
                'Content' => $request->input('content'),
                'SentAt' => now(),
                'IsDeleted' => false,
            ]);

            return response()->json([
                'message' => 'Message sent',
                'data' => $message,
            ], 201);
        });
    });


    // =========================================
    // Notifications
    // =========================================

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
    });
});
