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

/**
 * CV Controller - Manages CV CRUD operations matching database structure.
 */
class CVController extends Controller
{
    /**
     * Get all CVs for current user.
     */
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
