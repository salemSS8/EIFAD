<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVSkill;
use App\Domain\CV\Models\CVLanguage;
use App\Domain\CV\Models\CVCourse;
use App\Domain\CV\Models\Education;
use App\Domain\CV\Models\Experience;
use App\Domain\CV\Models\Volunteering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use OpenApi\Attributes as OA;

/**
 * CV Controller - Manages CV CRUD operations matching database structure.
 */
class CVController extends Controller
{
    /**
     * Get all CVs for current user.
     */
    #[OA\Get(
        path: "/cvs",
        operationId: "getCVs",
        tags: ["CVs"],
        summary: "Get my CVs",
        description: "Returns a list of CVs created by the authenticated job seeker.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "List of CVs")]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get job seeker ID
        $jobSeekerProfile = $user->jobSeekerProfile;
        if (!$jobSeekerProfile) {
            return response()->json(['message' => 'Job seeker profile not found'], 404);
        }

        $cvs = CV::where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->orderByDesc('CreatedAt')
            ->get();

        return response()->json(['data' => $cvs]);
    }

    /**
     * Get a specific CV with all related data.
     */
    #[OA\Get(
        path: "/cvs/{id}",
        operationId: "getCV",
        tags: ["CVs"],
        summary: "Get CV details",
        description: "Returns full details of a specific CV, including skills, education, experience, etc.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "CV details")]
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;

        $cv = CV::with([
            'skills.skill',
            'languages.language',
            'courses.course',
            'education',
            'experiences',
            'volunteering',
        ])
            ->where('CVID', $id)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        return response()->json(['data' => $cv]);
    }

    /**
     * Create a new CV.
     */
    #[OA\Post(
        path: "/cvs",
        operationId: "createCV",
        tags: ["CVs"],
        summary: "Create a new CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["title"],
            properties: [
                new OA\Property(property: "title", type: "string", example: "Full Stack Developer CV"),
                new OA\Property(property: "personal_summary", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "CV created successfully")]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'personal_summary' => 'nullable|string',
        ]);

        $user = $request->user();
        $jobSeekerProfile = $user->jobSeekerProfile;

        if (!$jobSeekerProfile) {
            return response()->json(['message' => 'Job seeker profile not found'], 404);
        }

        $cv = CV::create([
            'JobSeekerID' => $jobSeekerProfile->JobSeekerID,
            'Title' => $request->input('title'),
            'PersonalSummary' => $request->input('personal_summary'),
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        return response()->json([
            'message' => 'CV created successfully',
            'data' => $cv,
        ], 201);
    }

    /**
     * Update a CV.
     */
    #[OA\Put(
        path: "/cvs/{id}",
        operationId: "updateCV",
        tags: ["CVs"],
        summary: "Update CV details",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "title", type: "string"),
                new OA\Property(property: "personal_summary", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 200, description: "CV updated successfully")]
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'personal_summary' => 'nullable|string',
        ]);

        $user = $request->user();
        $cv = CV::where('CVID', $id)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cv->update([
            'Title' => $request->input('title', $cv->Title),
            'PersonalSummary' => $request->input('personal_summary', $cv->PersonalSummary),
            'UpdatedAt' => now(),
        ]);

        return response()->json([
            'message' => 'CV updated successfully',
            'data' => $cv->fresh(),
        ]);
    }

    /**
     * Delete a CV.
     */
    #[OA\Delete(
        path: "/cvs/{id}",
        operationId: "deleteCV",
        tags: ["CVs"],
        summary: "Delete a CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "CV deleted successfully")]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $cv = CV::where('CVID', $id)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cv->delete();

        return response()->json(['message' => 'CV deleted successfully']);
    }

    // ==========================================
    // CV Skills
    // ==========================================

    /**
     * Add a skill to CV.
     */
    #[OA\Post(
        path: "/cvs/{cvId}/skills",
        operationId: "addCVSkill",
        tags: ["CVs", "Skills"],
        summary: "Add skill to CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["skill_id"],
            properties: [
                new OA\Property(property: "skill_id", type: "integer"),
                new OA\Property(property: "skill_level", type: "string", example: "Intermediate"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Skill added to CV")]
    public function addSkill(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'skill_id' => 'required|exists:skill,SkillID',
            'skill_level' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cvSkill = CVSkill::create([
            'CVID' => $cv->CVID,
            'SkillID' => $request->input('skill_id'),
            'SkillLevel' => $request->input('skill_level'),
        ]);

        return response()->json([
            'message' => 'Skill added to CV',
            'data' => $cvSkill->load('skill'),
        ], 201);
    }

    /**
     * Remove a skill from CV.
     */
    #[OA\Delete(
        path: "/cvs/{cvId}/skills/{skillId}",
        operationId: "removeCVSkill",
        tags: ["CVs", "Skills"],
        summary: "Remove skill from CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "skillId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Skill removed from CV")]
    public function removeSkill(Request $request, int $cvId, int $skillId): JsonResponse
    {
        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        CVSkill::where('CVID', $cv->CVID)
            ->where('SkillID', $skillId)
            ->delete();

        return response()->json(['message' => 'Skill removed from CV']);
    }

    // ==========================================
    // CV Education
    // ==========================================

    /**
     * Add education to CV.
     */
    #[OA\Post(
        path: "/cvs/{cvId}/education",
        operationId: "addCVEducation",
        tags: ["CVs", "Education"],
        summary: "Add education to CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["institution", "degree_name"],
            properties: [
                new OA\Property(property: "institution", type: "string"),
                new OA\Property(property: "degree_name", type: "string"),
                new OA\Property(property: "major", type: "string"),
                new OA\Property(property: "graduation_year", type: "integer"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Education added to CV")]
    public function addEducation(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'institution' => 'required|string|max:255',
            'degree_name' => 'required|string|max:255',
            'major' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|integer|min:1950|max:2050',
        ]);

        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $education = Education::create([
            'CVID' => $cv->CVID,
            'Institution' => $request->input('institution'),
            'DegreeName' => $request->input('degree_name'),
            'Major' => $request->input('major'),
            'GraduationYear' => $request->input('graduation_year'),
        ]);

        return response()->json([
            'message' => 'Education added to CV',
            'data' => $education,
        ], 201);
    }

    /**
     * Remove education from CV.
     */
    #[OA\Delete(
        path: "/cvs/{cvId}/education/{educationId}",
        operationId: "removeCVEducation",
        tags: ["CVs", "Education"],
        summary: "Remove education from CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "educationId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Education removed from CV")]
    public function removeEducation(Request $request, int $cvId, int $educationId): JsonResponse
    {
        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        Education::where('EducationID', $educationId)
            ->where('CVID', $cv->CVID)
            ->delete();

        return response()->json(['message' => 'Education removed from CV']);
    }

    // ==========================================
    // CV Experience
    // ==========================================

    /**
     * Add experience to CV.
     */
    #[OA\Post(
        path: "/cvs/{cvId}/experience",
        operationId: "addCVExperience",
        tags: ["CVs", "Experience"],
        summary: "Add experience to CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["job_title", "company_name", "start_date"],
            properties: [
                new OA\Property(property: "job_title", type: "string"),
                new OA\Property(property: "company_name", type: "string"),
                new OA\Property(property: "start_date", type: "string", format: "date"),
                new OA\Property(property: "end_date", type: "string", format: "date", nullable: true),
                new OA\Property(property: "responsibilities", type: "string"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Experience added to CV")]
    public function addExperience(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'job_title' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'responsibilities' => 'nullable|string',
        ]);

        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $experience = Experience::create([
            'CVID' => $cv->CVID,
            'JobTitle' => $request->input('job_title'),
            'CompanyName' => $request->input('company_name'),
            'StartDate' => $request->input('start_date'),
            'EndDate' => $request->input('end_date'),
            'Responsibilities' => $request->input('responsibilities'),
        ]);

        return response()->json([
            'message' => 'Experience added to CV',
            'data' => $experience,
        ], 201);
    }

    /**
     * Remove experience from CV.
     */
    #[OA\Delete(
        path: "/cvs/{cvId}/experience/{experienceId}",
        operationId: "removeCVExperience",
        tags: ["CVs", "Experience"],
        summary: "Remove experience from CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "experienceId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Experience removed from CV")]
    public function removeExperience(Request $request, int $cvId, int $experienceId): JsonResponse
    {
        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        Experience::where('ExperienceID', $experienceId)
            ->where('CVID', $cv->CVID)
            ->delete();

        return response()->json(['message' => 'Experience removed from CV']);
    }

    // ==========================================
    // CV Languages
    // ==========================================

    /**
     * Add language to CV.
     */
    #[OA\Post(
        path: "/cvs/{cvId}/languages",
        operationId: "addCVLanguage",
        tags: ["CVs", "Languages"],
        summary: "Add language to CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["language_id"],
            properties: [
                new OA\Property(property: "language_id", type: "integer"),
                new OA\Property(property: "language_level", type: "string", example: "Native"),
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Language added to CV")]
    public function addLanguage(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'language_id' => 'required|exists:language,LanguageID',
            'language_level' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cvLanguage = CVLanguage::create([
            'CVID' => $cv->CVID,
            'LanguageID' => $request->input('language_id'),
            'LanguageLevel' => $request->input('language_level'),
        ]);

        return response()->json([
            'message' => 'Language added to CV',
            'data' => $cvLanguage->load('language'),
        ], 201);
    }

    /**
     * Remove language from CV.
     */
    #[OA\Delete(
        path: "/cvs/{cvId}/languages/{languageId}",
        operationId: "removeCVLanguage",
        tags: ["CVs", "Languages"],
        summary: "Remove language from CV",
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "cvId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "languageId", in: "path", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "Language removed from CV")]
    public function removeLanguage(Request $request, int $cvId, int $languageId): JsonResponse
    {
        $user = $request->user();
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $user->jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        CVLanguage::where('CVID', $cv->CVID)
            ->where('LanguageID', $languageId)
            ->delete();

        return response()->json(['message' => 'Language removed from CV']);
    }
}
