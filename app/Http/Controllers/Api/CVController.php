<?php

namespace App\Http\Controllers\Api;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Models\CVCertification;
use App\Domain\CV\Models\CVCustomSection;
use App\Domain\CV\Models\CVLanguage;
use App\Domain\CV\Models\CVSkill;
use App\Domain\CV\Models\Education;
use App\Domain\CV\Models\Experience;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * CV Controller - Manages CV CRUD operations matching database structure.
 */
class CVController extends Controller
{
    /**
     * Get the authenticated user's job seeker profile or abort with 404.
     */
    private function getJobSeekerProfile(Request $request): \App\Domain\User\Models\JobSeekerProfile
    {
        $profile = $request->user()->jobSeekerProfile;

        if (! $profile) {
            abort(404, 'Job seeker profile not found');
        }

        return $profile;
    }

    /**
     * Get all CVs for current user.
     */
    #[OA\Get(
        path: '/cvs',
        operationId: 'getCVs',
        tags: ['CVs'],
        summary: 'Get my CVs',
        description: 'Returns a list of CVs created by the authenticated job seeker.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of CVs')]
    public function index(Request $request): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);

        $cvs = CV::where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->orderByDesc('CreatedAt')
            ->get();

        return response()->json(['data' => $cvs]);
    }

    /**
     * Get a specific CV with all related data.
     */
    #[OA\Get(
        path: '/cvs/{id}',
        operationId: 'getCV',
        tags: ['CVs'],
        summary: 'Get CV details',
        description: 'Returns full details of a specific CV, including skills, education, experience, etc.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'CV details')]
    public function show(Request $request, int $id): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);

        $cv = CV::with([
            'skills.skill',
            'languages.language',
            'courses.course',
            'education',
            'experiences',
            'volunteering',
            'certifications',
            'customSections',
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
        path: '/cvs',
        operationId: 'createCV',
        tags: ['CVs'],
        summary: 'Create a new CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Full Stack Developer CV'),
                new OA\Property(property: 'personal_summary', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'CV created successfully')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'personal_summary' => 'nullable|string',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);

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
        path: '/cvs/{id}',
        operationId: 'updateCV',
        tags: ['CVs'],
        summary: 'Update CV details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'personal_summary', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'CV updated successfully')]
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'personal_summary' => 'nullable|string',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $id)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{id}',
        operationId: 'deleteCV',
        tags: ['CVs'],
        summary: 'Delete a CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'CV deleted successfully')]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $id)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{cvId}/skills',
        operationId: 'addCVSkill',
        tags: ['CVs', 'Skills'],
        summary: 'Add skill to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['skill_id'],
            properties: [
                new OA\Property(property: 'skill_id', type: 'integer'),
                new OA\Property(property: 'skill_level', type: 'string', example: 'Intermediate'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Skill added to CV')]
    public function addSkill(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'skill_id' => [
                'required',
                'exists:skill,SkillID',
                // التأكد من أن المهارة لم تضف مسبقاً لهذه السيرة الذاتية
                Rule::unique('cvskill', 'SkillID')->where(function ($query) use ($cvId) {
                    return $query->where('CVID', $cvId);
                }),
            ],
            'skill_level' => 'nullable|string|max:255',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{cvId}/skills/{skillId}',
        operationId: 'removeCVSkill',
        tags: ['CVs', 'Skills'],
        summary: 'Remove skill from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'skillId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Skill removed from CV')]
    public function removeSkill(Request $request, int $cvId, int $skillId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        CVSkill::where('CVID', $cv->CVID)
            ->where('SkillID', $skillId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Skill removed from CV']);
    }

    /**
     * Update a CV skill.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/skills/{skillId}',
        operationId: 'updateCVSkill',
        tags: ['CVs', 'Skills'],
        summary: 'Update skill in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'skillId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'skill_level', type: 'string', example: 'Advanced'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Skill updated in CV')]
    public function updateSkill(Request $request, int $cvId, int $skillId): JsonResponse
    {
        $request->validate([
            'skill_level' => 'required|string|max:255',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cvSkill = CVSkill::where('CVID', $cv->CVID)
            ->where('SkillID', $skillId)
            ->firstOrFail();

        $cvSkill->update([
            'SkillLevel' => $request->input('skill_level'),
        ]);

        return response()->json([
            'message' => 'Skill updated in CV',
            'data' => $cvSkill->load('skill'),
        ]);
    }

    // ==========================================
    // CV Education
    // ==========================================

    /**
     * Add education to CV.
     */
    #[OA\Post(
        path: '/cvs/{cvId}/education',
        operationId: 'addCVEducation',
        tags: ['CVs', 'Education'],
        summary: 'Add education to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['institution', 'degree_name'],
            properties: [
                new OA\Property(property: 'institution', type: 'string'),
                new OA\Property(property: 'degree_name', type: 'string'),
                new OA\Property(property: 'major', type: 'string'),
                new OA\Property(property: 'graduation_year', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Education added to CV')]
    public function addEducation(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'institution' => 'required|string|max:255',
            'degree_name' => 'required|string|max:255',
            'major' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|integer|min:1950|max:2050',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{cvId}/education/{educationId}',
        operationId: 'removeCVEducation',
        tags: ['CVs', 'Education'],
        summary: 'Remove education from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'educationId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Education removed from CV')]
    public function removeEducation(Request $request, int $cvId, int $educationId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        Education::where('EducationID', $educationId)
            ->where('CVID', $cv->CVID)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Education removed from CV']);
    }

    /**
     * Update an education entry in CV.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/education/{educationId}',
        operationId: 'updateCVEducation',
        tags: ['CVs', 'Education'],
        summary: 'Update education entry in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'educationId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'institution', type: 'string'),
                new OA\Property(property: 'degree_name', type: 'string'),
                new OA\Property(property: 'major', type: 'string'),
                new OA\Property(property: 'graduation_year', type: 'integer'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Education updated successfully')]
    public function updateEducation(Request $request, int $cvId, int $educationId): JsonResponse
    {
        $request->validate([
            'institution' => 'sometimes|string|max:255',
            'degree_name' => 'sometimes|string|max:255',
            'major' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|integer|min:1950|max:2050',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $education = Education::where('EducationID', $educationId)
            ->where('CVID', $cv->CVID)
            ->firstOrFail();

        $education->update([
            'Institution' => $request->input('institution', $education->Institution),
            'DegreeName' => $request->input('degree_name', $education->DegreeName),
            'Major' => $request->input('major', $education->Major),
            'GraduationYear' => $request->input('graduation_year', $education->GraduationYear),
        ]);

        return response()->json([
            'message' => 'Education updated successfully',
            'data' => $education,
        ]);
    }

    // ==========================================
    // CV Experience
    // ==========================================

    /**
     * Add experience to CV.
     */
    #[OA\Post(
        path: '/cvs/{cvId}/experience',
        operationId: 'addCVExperience',
        tags: ['CVs', 'Experience'],
        summary: 'Add experience to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['job_title', 'company_name', 'start_date'],
            properties: [
                new OA\Property(property: 'job_title', type: 'string'),
                new OA\Property(property: 'company_name', type: 'string'),
                new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true),
                new OA\Property(property: 'responsibilities', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Experience added to CV')]
    public function addExperience(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'job_title' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'responsibilities' => 'nullable|string',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{cvId}/experience/{experienceId}',
        operationId: 'removeCVExperience',
        tags: ['CVs', 'Experience'],
        summary: 'Remove experience from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'experienceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Experience removed from CV')]
    public function removeExperience(Request $request, int $cvId, int $experienceId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        Experience::where('ExperienceID', $experienceId)
            ->where('CVID', $cv->CVID)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Experience removed from CV']);
    }

    /**
     * Update an experience entry in CV.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/experience/{experienceId}',
        operationId: 'updateCVExperience',
        tags: ['CVs', 'Experience'],
        summary: 'Update experience entry in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'experienceId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'job_title', type: 'string'),
                new OA\Property(property: 'company_name', type: 'string'),
                new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                new OA\Property(property: 'responsibilities', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Experience updated successfully')]
    public function updateExperience(Request $request, int $cvId, int $experienceId): JsonResponse
    {
        $request->validate([
            'job_title' => 'sometimes|string|max:255',
            'company_name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date',
            'responsibilities' => 'nullable|string',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $experience = Experience::where('ExperienceID', $experienceId)
            ->where('CVID', $cv->CVID)
            ->firstOrFail();

        $experience->update([
            'JobTitle' => $request->input('job_title', $experience->JobTitle),
            'CompanyName' => $request->input('company_name', $experience->CompanyName),
            'StartDate' => $request->input('start_date', $experience->StartDate),
            'EndDate' => $request->input('end_date', $experience->EndDate),
            'Responsibilities' => $request->input('responsibilities', $experience->Responsibilities),
        ]);

        return response()->json([
            'message' => 'Experience updated successfully',
            'data' => $experience,
        ]);
    }

    // ==========================================
    // CV Languages
    // ==========================================

    /**
     * Add language to CV.
     */
    #[OA\Post(
        path: '/cvs/{cvId}/languages',
        operationId: 'addCVLanguage',
        tags: ['CVs', 'Languages'],
        summary: 'Add language to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['language_id'],
            properties: [
                new OA\Property(property: 'language_id', type: 'integer'),
                new OA\Property(property: 'language_level', type: 'string', example: 'Native'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Language added to CV')]
    public function addLanguage(Request $request, int $cvId): JsonResponse
    {
        $request->validate([
            'language_id' => [
                'required',
                'exists:language,LanguageID',
                Rule::unique('cvlanguage', 'LanguageID')->where(function ($query) use ($cvId) {
                    return $query->where('CVID', $cvId);
                }),
            ],
            'language_level' => 'nullable|string|max:255',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
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
        path: '/cvs/{cvId}/languages/{languageId}',
        operationId: 'removeCVLanguage',
        tags: ['CVs', 'Languages'],
        summary: 'Remove language from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'languageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Language removed from CV')]
    public function removeLanguage(Request $request, int $cvId, int $languageId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        CVLanguage::where('CVID', $cv->CVID)
            ->where('LanguageID', $languageId)
            ->delete();

        return response()->json(['message' => 'Language removed from CV']);
    }

    /**
     * Update a language entry in CV.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/languages/{languageId}',
        operationId: 'updateCVLanguage',
        tags: ['CVs', 'Languages'],
        summary: 'Update language entry in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'languageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'language_id', type: 'integer'),
                new OA\Property(property: 'language_level', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Language updated successfully')]
    public function updateLanguage(Request $request, int $cvId, int $languageId): JsonResponse
    {
        $request->validate([
            'language_id' => 'sometimes|exists:language,LanguageID',
            'language_level' => 'nullable|string|max:255',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $cvLanguage = CVLanguage::where('CVID', $cv->CVID)
            ->where('LanguageID', $languageId)
            ->firstOrFail();

        $cvLanguage->update([
            'LanguageID' => $request->input('language_id', $cvLanguage->LanguageID),
            'LanguageLevel' => $request->input('language_level', $cvLanguage->LanguageLevel),
        ]);

        return response()->json([
            'message' => 'Language updated successfully',
            'data' => $cvLanguage->load('language'),
        ]);
    }

    // ==========================================
    // CV Certifications
    // ==========================================

    /**
     * Add certification to CV
     */
    #[OA\Post(
        path: '/cvs/{cvId}/certifications',
        operationId: 'addCertification',
        tags: ['CVs'],
        summary: 'Add certification to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['certificate_name'],
            properties: [
                new OA\Property(property: 'certificate_name', type: 'string', example: 'PMP Certification'),
                new OA\Property(property: 'issuing_organization', type: 'string', example: 'PMI'),
                new OA\Property(property: 'is_verified', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Certification added successfully')]
    public function addCertification(Request $request, int $cvId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $validated = $request->validate([
            'certificate_name' => 'required|string|max:255',
            'issuing_organization' => 'nullable|string|max:255',
            'is_verified' => 'nullable|boolean',
            'issue_date' => 'nullable|date',
        ]);

        $certification = CVCertification::create([
            'CVID' => $cv->CVID,
            'CertificateName' => $validated['certificate_name'],
            'IssuingOrganization' => $validated['issuing_organization'] ?? null,
            'IsVerified' => $validated['is_verified'] ?? false,
            'IssueDate' => $validated['issue_date'] ?? null,
        ]);

        return response()->json([
            'message' => 'Certification added successfully',
            'data' => $certification,
        ], 201);
    }

    /**
     * Remove certification from CV
     */
    #[OA\Delete(
        path: '/cvs/{cvId}/certifications/{certId}',
        operationId: 'removeCertification',
        tags: ['CVs'],
        summary: 'Remove certification from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'certId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Certification removed successfully')]
    public function removeCertification(Request $request, int $cvId, int $certId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $certification = CVCertification::where('CVID', $cvId)
            ->where('CertificationID', $certId)
            ->firstOrFail();

        $certification->delete();

        return response()->json(['message' => 'Certification removed successfully']);
    }

    /**
     * Update a certification entry in CV.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/certifications/{certId}',
        operationId: 'updateCVCertification',
        tags: ['CVs', 'Certifications'],
        summary: 'Update certification entry in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'certId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'certificate_name', type: 'string'),
                new OA\Property(property: 'issuing_organization', type: 'string'),
                new OA\Property(property: 'is_verified', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Certification updated successfully')]
    public function updateCertification(Request $request, int $cvId, int $certId): JsonResponse
    {
        $request->validate([
            'certificate_name' => 'sometimes|string|max:255',
            'issuing_organization' => 'nullable|string|max:255',
            'is_verified' => 'nullable|boolean',
            'issue_date' => 'nullable|date',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $certification = CVCertification::where('CVID', $cv->CVID)
            ->where('CertificationID', $certId)
            ->firstOrFail();

        $certification->update([
            'CertificateName' => $request->input('certificate_name', $certification->CertificateName),
            'IssuingOrganization' => $request->input('issuing_organization', $certification->IssuingOrganization),
            'IsVerified' => $request->input('is_verified', $certification->IsVerified),
            'IssueDate' => $request->input('issue_date', $certification->IssueDate),
        ]);

        return response()->json([
            'message' => 'Certification updated successfully',
            'data' => $certification,
        ]);
    }

    // ==========================================
    // CV Custom Sections
    // ==========================================

    /**
     * Add generic custom section to CV
     */
    #[OA\Post(
        path: '/cvs/{cvId}/custom-sections',
        operationId: 'addCustomSection',
        tags: ['CVs'],
        summary: 'Add custom section to CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['SectionType', 'Title'],
            properties: [
                new OA\Property(property: 'SectionType', type: 'string', example: 'Volunteering'),
                new OA\Property(property: 'Title', type: 'string', example: 'Red Cross Volunteer'),
                new OA\Property(property: 'Description', type: 'string', example: 'Assisted in...'),
                new OA\Property(property: 'StartDate', type: 'string', format: 'date', example: '2022-01-01'),
                new OA\Property(property: 'EndDate', type: 'string', format: 'date', example: '2022-12-31'),
                new OA\Property(
                    property: 'content_data',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                    example: ['key1' => 'value1', 'key2' => 'value2']
                ),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Custom section added successfully')]
    public function addCustomSection(Request $request, int $cvId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $validated = $request->validate([
            'SectionType' => 'required|string|max:100',
            'Title' => 'required|string|max:255',
            'Description' => 'nullable|string',
            'StartDate' => 'nullable|date',
            'EndDate' => 'nullable|date|after_or_equal:StartDate',
            'content_data' => 'nullable|array',
        ]);

        $section = CVCustomSection::create([
            'CVID' => $cv->CVID,
            'SectionType' => $validated['SectionType'],
            'Title' => $validated['Title'],
            'Description' => $validated['Description'] ?? null,
            'StartDate' => $validated['StartDate'] ?? null,
            'EndDate' => $validated['EndDate'] ?? null,
            'content_data' => $validated['content_data'] ?? null,
        ]);

        return response()->json([
            'message' => 'Custom section added successfully',
            'data' => $section,
        ], 201);
    }

    /**
     * Remove custom section from CV
     */
    #[OA\Delete(
        path: '/cvs/{cvId}/custom-sections/{sectionId}',
        operationId: 'removeCustomSection',
        tags: ['CVs'],
        summary: 'Remove custom section from CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'sectionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Custom section removed successfully')]
    public function removeCustomSection(Request $request, int $cvId, int $sectionId): JsonResponse
    {
        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $section = CVCustomSection::where('CVID', $cvId)
            ->where('CustomSectionID', $sectionId)
            ->firstOrFail();

        $section->delete();

        return response()->json(['message' => 'Custom section removed successfully']);
    }

    /**
     * Update a custom section in CV.
     */
    #[OA\Put(
        path: '/cvs/{cvId}/custom-sections/{sectionId}',
        operationId: 'updateCustomSection',
        tags: ['CVs'],
        summary: 'Update custom section in CV',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'cvId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'sectionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'SectionType', type: 'string'),
                new OA\Property(property: 'Title', type: 'string'),
                new OA\Property(property: 'Description', type: 'string'),
                new OA\Property(property: 'StartDate', type: 'string', format: 'date'),
                new OA\Property(property: 'EndDate', type: 'string', format: 'date'),
                new OA\Property(property: 'content_data', type: 'array', items: new OA\Items(type: 'string')),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Custom section updated successfully')]
    public function updateCustomSection(Request $request, int $cvId, int $sectionId): JsonResponse
    {
        $request->validate([
            'SectionType' => 'sometimes|string|max:255',
            'Title' => 'sometimes|string|max:255',
            'Description' => 'nullable|string',
            'StartDate' => 'nullable|date',
            'EndDate' => 'nullable|date|after_or_equal:StartDate',
            'content_data' => 'nullable|array',
        ]);

        $jobSeekerProfile = $this->getJobSeekerProfile($request);
        $cv = CV::where('CVID', $cvId)
            ->where('JobSeekerID', $jobSeekerProfile->JobSeekerID)
            ->firstOrFail();

        $section = CVCustomSection::where('CVID', $cvId)
            ->where('CustomSectionID', $sectionId)
            ->firstOrFail();

        $section->update([
            'SectionType' => $request->input('SectionType', $section->SectionType),
            'Title' => $request->input('Title', $section->Title),
            'Description' => $request->input('Description', $section->Description),
            'StartDate' => $request->input('StartDate', $section->StartDate),
            'EndDate' => $request->input('EndDate', $section->EndDate),
            'content_data' => $request->input('content_data', $section->content_data),
        ]);

        return response()->json([
            'message' => 'Custom section updated successfully',
            'data' => $section,
        ]);
    }

    // ==========================================
    // Resume Parsing (AI Extraction)
    // ==========================================

    /**
     * Parse CV file into structured data using AI (Affinda)
     */
    #[OA\Post(
        path: '/cvs/parse',
        operationId: 'parseCVFile',
        tags: ['CVs'],
        summary: 'Extract structured data from a CV file',
        description: 'Upload a PDF or DOCX file to extract its contents automatically using AI.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'The CV document (PDF, DOC, DOCX)'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Parsed CV data')]
    public function parse(Request $request, \App\Domain\CV\Services\AffindaResumeParser $parser): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240', // Max 10MB
        ], [
            'file.required' => 'الرجاء إرفاق ملف السيرة الذاتية',
            'file.mimes' => 'يجب أن يكون الملف بصيغة PDF أو Word',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميغابايت',
        ]);

        $file = $request->file('file');

        // Save temporarily
        $path = $file->storeAs('tmp', 'resume_'.time().'.'.$file->getClientOriginalExtension());
        $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);

        try {
            $parsedData = $parser->parseFile($absolutePath);

            // Cleanup temp file
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            if (! $parsedData) {
                return response()->json([
                    'message' => 'حدث خطأ أثناء محاولة تحليل السيرة الذاتية. يرجى التأكد من أن الملف مقروء ومعد بشكل صحيح.',
                ], 422);
            }

            return response()->json([
                'message' => 'تم استخراج البيانات بنجاح',
                'data' => $parsedData->toArray(),
            ]);
        } catch (\RuntimeException $e) {
            // Cleanup Temp file
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            // Cleanup on error too
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }

            return response()->json([
                'message' => 'حدث خطأ غير متوقع أثناء معالجة الملف',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
