<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Course\Models\CourseAd;
use App\Domain\Course\Models\CourseEnrollment;
use App\Domain\Communication\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Course Controller - Manages course ads and enrollments.
 * User Stories: Create Course Ads, Submit & Cancel Course Registration, Notify Course Participants
 */

use OpenApi\Attributes as OA;

/**
 * Course Controller - Manages course ads and enrollments.
 * User Stories: Create Course Ads, Submit & Cancel Course Registration, Notify Course Participants
 */
class CourseController extends Controller
{
    // ==========================================
    // Public Course Viewing
    // ==========================================

    /**
     * Get all active courses.
     */
    #[OA\Get(
        path: "/courses",
        operationId: "getCourses",
        tags: ["Courses"],
        summary: "Get active courses",
        description: "Returns a list of all active courses available for enrollment."
    )]
    #[OA\Response(response: 200, description: "List of courses")]
    public function index(): JsonResponse
    {
        $courses = CourseAd::with('company:CompanyID,CompanyName,LogoPath')
            ->where('Status', 'Active')
            ->orderByDesc('StartDate')
            ->paginate(15);

        return response()->json($courses);
    }

    /**
     * Get course details.
     */
    #[OA\Get(
        path: "/courses/{id}",
        operationId: "getCourseDetails",
        tags: ["Courses"],
        summary: "Get course details",
        description: "Returns detailed information about a specific course."
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Course details")]
    public function show(int $id): JsonResponse
    {
        $course = CourseAd::with(['company', 'enrollments'])
            ->where('CourseAdID', $id)
            ->firstOrFail();

        return response()->json(['data' => $course]);
    }

    // ==========================================
    // Job Seeker: Enrollment Management
    // ==========================================

    /**
     * Enroll in a course.
     */
    #[OA\Post(
        path: "/courses/{id}/enroll",
        operationId: "enrollCourse",
        tags: ["Courses"],
        summary: "Enroll in a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 201, description: "Enrolled successfully")]
    public function enroll(Request $request, int $id): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'فقط الباحثين عن عمل يمكنهم التسجيل في الدورات',
            ], 403);
        }

        $course = CourseAd::where('CourseAdID', $id)
            ->where('Status', 'Active')
            ->first();

        if (!$course) {
            return response()->json([
                'message' => 'الدورة غير متاحة للتسجيل',
            ], 404);
        }

        // Check if already enrolled
        $existingEnrollment = CourseEnrollment::where('CourseAdID', $id)
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->first();

        if ($existingEnrollment) {
            if ($existingEnrollment->Status === 'Enrolled') {
                return response()->json([
                    'message' => 'أنت مسجّل مسبقاً في هذه الدورة',
                ], 422);
            }

            // Re-enroll if previously cancelled
            $existingEnrollment->update([
                'Status' => 'Enrolled',
                'EnrolledAt' => now(),
            ]);

            return response()->json([
                'message' => 'تم إعادة التسجيل في الدورة بنجاح',
                'data' => $existingEnrollment->fresh(),
            ]);
        }

        $enrollment = CourseEnrollment::create([
            'CourseAdID' => $id,
            'JobSeekerID' => $profile->JobSeekerID,
            'EnrolledAt' => now(),
            'Status' => 'Enrolled',
        ]);

        return response()->json([
            'message' => 'تم التسجيل في الدورة بنجاح',
            'data' => $enrollment,
        ], 201);
    }

    /**
     * Cancel enrollment (unenroll).
     */
    #[OA\Delete(
        path: "/courses/{id}/enroll",
        operationId: "unenrollCourse",
        tags: ["Courses"],
        summary: "Unenroll from a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Unenrolled successfully")]
    public function unenroll(Request $request, int $id): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'غير مصرح',
            ], 403);
        }

        $enrollment = CourseEnrollment::where('CourseAdID', $id)
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'أنت غير مسجّل في هذه الدورة',
            ], 404);
        }

        if ($enrollment->Status === 'Cancelled') {
            return response()->json([
                'message' => 'التسجيل ملغي مسبقاً',
            ], 422);
        }

        $enrollment->update([
            'Status' => 'Cancelled',
        ]);

        return response()->json([
            'message' => 'تم إلغاء التسجيل من الدورة بنجاح',
        ]);
    }

    /**
     * Get my enrollments.
     */
    #[OA\Get(
        path: "/courses/my-enrollments",
        operationId: "getMyEnrollments",
        tags: ["Courses"],
        summary: "Get my enrollments",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of enrollments")]
    public function myEnrollments(Request $request): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'غير مصرح',
            ], 403);
        }

        $enrollments = CourseEnrollment::with('course.company:CompanyID,CompanyName')
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->orderByDesc('EnrolledAt')
            ->paginate(15);

        return response()->json($enrollments);
    }

    // ==========================================
    // Employer: Course Ads Management (CRUD)
    // ==========================================

    /**
     * Get employer's courses.
     */
    #[OA\Get(
        path: "/employer/courses",
        operationId: "getEmployerCourses",
        tags: ["Employer", "Courses"],
        summary: "Get employer's courses",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of employer's courses")]
    public function employerCourses(Request $request): JsonResponse
    {
        $company = $request->user()->companyProfile;

        if (!$company) {
            return response()->json([
                'message' => 'ملف الشركة غير موجود',
            ], 404);
        }

        $courses = CourseAd::where('CompanyID', $company->CompanyID)
            ->withCount('enrollments')
            ->orderByDesc('CreatedAt')
            ->paginate(15);

        return response()->json($courses);
    }

    /**
     * Create a new course ad.
     */
    #[OA\Post(
        path: "/employer/courses",
        operationId: "createCourse",
        tags: ["Employer", "Courses"],
        summary: "Create a new course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["title"],
            properties: [
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "topics", type: "string"),
                new OA\Property(property: "duration", type: "string"),
                new OA\Property(property: "location", type: "string"),
                new OA\Property(property: "trainer", type: "string"),
                new OA\Property(property: "fees", type: "number"),
                new OA\Property(property: "start_date", type: "string", format: "date"),
                new OA\Property(property: "status", type: "string", enum: ["Draft", "Active"]),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Course created successfully")]
    public function store(Request $request): JsonResponse
    {
        $company = $request->user()->companyProfile;

        if (!$company) {
            return response()->json([
                'message' => 'ملف الشركة غير موجود',
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'topics' => 'nullable|string',
            'duration' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'trainer' => 'nullable|string|max:255',
            'fees' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'status' => 'nullable|in:Draft,Active,Closed',
        ], [
            'title.required' => 'عنوان الدورة مطلوب',
        ]);

        $course = CourseAd::create([
            'CompanyID' => $company->CompanyID,
            'CourseTitle' => $request->input('title'),
            'Topics' => $request->input('topics'),
            'Duration' => $request->input('duration'),
            'Location' => $request->input('location'),
            'Trainer' => $request->input('trainer'),
            'Fees' => $request->input('fees', 0),
            'StartDate' => $request->input('start_date'),
            'Status' => $request->input('status', 'Draft'),
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'message' => 'تم إنشاء إعلان الدورة بنجاح',
            'data' => $course,
        ], 201);
    }

    /**
     * Update a course ad.
     */
    #[OA\Put(
        path: "/employer/courses/{id}",
        operationId: "updateCourse",
        tags: ["Employer", "Courses"],
        summary: "Update course details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "topics", type: "string"),
                new OA\Property(property: "status", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Course updated successfully")]
    public function update(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        if (!$company) {
            return response()->json([
                'message' => 'ملف الشركة غير موجود',
            ], 404);
        }

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'topics' => 'nullable|string',
            'duration' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'trainer' => 'nullable|string|max:255',
            'fees' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'status' => 'nullable|in:Draft,Active,Closed',
        ]);

        $course->update([
            'CourseTitle' => $request->input('title', $course->CourseTitle),
            'Topics' => $request->input('topics', $course->Topics),
            'Duration' => $request->input('duration', $course->Duration),
            'Location' => $request->input('location', $course->Location),
            'Trainer' => $request->input('trainer', $course->Trainer),
            'Fees' => $request->input('fees', $course->Fees),
            'StartDate' => $request->input('start_date', $course->StartDate),
            'Status' => $request->input('status', $course->Status),
        ]);

        return response()->json([
            'message' => 'تم تحديث إعلان الدورة بنجاح',
            'data' => $course->fresh(),
        ]);
    }

    /**
     * Publish a course (change status to Active).
     */
    #[OA\Post(
        path: "/employer/courses/{id}/publish",
        operationId: "publishCourse",
        tags: ["Employer", "Courses"],
        summary: "Publish a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Course published")]
    public function publish(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $course->update(['Status' => 'Active']);

        return response()->json([
            'message' => 'تم نشر الدورة بنجاح',
            'data' => $course->fresh(),
        ]);
    }

    /**
     * Close a course.
     */
    #[OA\Post(
        path: "/employer/courses/{id}/close",
        operationId: "closeCourse",
        tags: ["Employer", "Courses"],
        summary: "Close a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Course closed")]
    public function close(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $course->update(['Status' => 'Closed']);

        return response()->json([
            'message' => 'تم إغلاق الدورة بنجاح',
            'data' => $course->fresh(),
        ]);
    }

    /**
     * Delete a course ad.
     */
    #[OA\Delete(
        path: "/employer/courses/{id}",
        operationId: "deleteCourse",
        tags: ["Employer", "Courses"],
        summary: "Delete a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Course deleted")]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        // Check for enrollments
        if ($course->enrollments()->where('Status', 'Enrolled')->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الدورة وهناك مشتركين مسجلين، يرجى إغلاقها بدلاً من ذلك',
            ], 422);
        }

        $course->delete();

        return response()->json([
            'message' => 'تم حذف إعلان الدورة بنجاح',
        ]);
    }

    /**
     * Get course enrollments (for employer).
     */
    #[OA\Get(
        path: "/employer/courses/{id}/enrollments",
        operationId: "getCourseEnrollments",
        tags: ["Employer", "Courses"],
        summary: "Get enrollments for a course",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "List of enrollments")]
    public function enrollments(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $enrollments = CourseEnrollment::with('jobSeeker.user:UserID,FullName,Email,Phone')
            ->where('CourseAdID', $id)
            ->orderByDesc('EnrolledAt')
            ->paginate(20);

        return response()->json($enrollments);
    }

    /**
     * Notify course participants.
     */
    #[OA\Post(
        path: "/employer/courses/{id}/notify",
        operationId: "notifyCourseParticipants",
        tags: ["Employer", "Courses"],
        summary: "Notify course participants",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["title", "message"],
            properties: [
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "message", type: "string"),
                new OA\Property(property: "type", type: "string", enum: ["reminder", "update", "cancellation", "info"]),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "Notification sent")]
    public function notifyParticipants(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->companyProfile;

        $course = CourseAd::where('CourseAdID', $id)
            ->where('CompanyID', $company->CompanyID)
            ->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'nullable|in:reminder,update,cancellation,info',
        ], [
            'title.required' => 'عنوان الإشعار مطلوب',
            'message.required' => 'نص الإشعار مطلوب',
        ]);

        $enrollments = CourseEnrollment::with('jobSeeker')
            ->where('CourseAdID', $id)
            ->where('Status', 'Enrolled')
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json([
                'message' => 'لا يوجد مشتركين لإرسال الإشعار لهم',
            ], 422);
        }

        $notificationType = $request->input('type', 'info');
        $notificationContent = [
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'course_id' => $course->CourseAdID,
            'course_title' => $course->CourseTitle,
            'type' => $notificationType,
        ];

        $sentCount = 0;
        foreach ($enrollments as $enrollment) {
            Notification::create([
                'UserID' => $enrollment->jobSeeker->JobSeekerID,
                'Type' => 'course_notification',
                'Content' => json_encode($notificationContent),
                'IsRead' => false,
                'CreatedAt' => now(),
            ]);
            $sentCount++;
        }

        return response()->json([
            'message' => "تم إرسال الإشعار إلى {$sentCount} مشترك بنجاح",
            'sent_count' => $sentCount,
        ]);
    }
}
