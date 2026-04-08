<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Skill\Models\Skill;
use App\Domain\Skill\Models\SkillCategory;
use App\Domain\Skill\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Skill Controller - Provides skill and language catalogs.
 */

use OpenApi\Attributes as OA;

/**
 * Skill Controller - Provides skill and language catalogs.
 */
class SkillController extends Controller
{
    /**
     * Get all skills.
     */
    #[OA\Get(
        path: "/skills",
        operationId: "getSkills",
        tags: ["Skills"],
        summary: "Get list of skills",
        description: "Returns a list of skills, optionally filtered by category or search term."
    )]
    #[OA\Parameter(name: "category_id", in: "query", description: "Filter by category ID", required: false, schema: new OA\Schema(type: "integer"))]
    #[OA\Parameter(name: "search", in: "query", description: "Search by skill name", required: false, schema: new OA\Schema(type: "string"))]
    #[OA\Response(response: 200, description: "List of skills")]
    public function index(Request $request): JsonResponse
    {
        $query = Skill::with('category');

        if ($request->filled('category_id')) {
            $query->where('CategoryID', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $query->where('SkillName', 'like', "%{$request->input('search')}%");
        }

        $skills = $query->orderBy('SkillName')->get();

        return response()->json(['data' => $skills]);
    }

    /**
     * Create a new skill in the system catalog.
     */
    #[OA\Post(
        path: "/skills",
        operationId: "createSkill",
        tags: ["Skills"],
        summary: "Create a new skill",
        description: "Creates a new skill in the system catalog. Useful when a user cannot find a skill they want to add.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['skill_name'],
            properties: [
                new OA\Property(property: 'skill_name', type: 'string', example: 'Vue.js'),
                new OA\Property(property: 'category_id', type: 'integer', nullable: true, example: 1)
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Skill created successfully")]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'skill_name' => 'required|string|max:255|unique:skill,SkillName',
            'category_id' => 'nullable|exists:skillcategory,CategoryID'
        ]);

        $skill = Skill::create([
            'SkillName' => $request->input('skill_name'),
            'CategoryID' => $request->input('category_id')
        ]);

        return response()->json([
            'message' => 'Skill created successfully in the system',
            'data' => $skill->load('category')
        ], 201);
    }

    /**
     * Get skill categories.
     */
    #[OA\Get(
        path: "/skill-categories",
        operationId: "getSkillCategories",
        tags: ["Skills"],
        summary: "Get skill categories",
        description: "Returns a list of skill categories."
    )]
    #[OA\Response(response: 200, description: "List of skill categories")]
    public function categories(): JsonResponse
    {
        $categories = SkillCategory::withCount('skills')
            ->orderBy('CategoryName')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Get all languages.
     */
    #[OA\Get(
        path: "/languages",
        operationId: "getLanguages",
        tags: ["Skills"],
        summary: "Get languages",
        description: "Returns a list of available languages."
    )]
    #[OA\Response(response: 200, description: "List of languages")]
    public function languages(): JsonResponse
    {
        $languages = Language::orderBy('LanguageName')->get();

        return response()->json(['data' => $languages]);
    }

    /**
     * Create a new language in the system catalog.
     */
    #[OA\Post(
        path: "/languages",
        operationId: "createLanguage",
        tags: ["Skills"],
        summary: "Create a new language",
        description: "Creates a new language in the system catalog. Useful when a user cannot find a language they want to add.",
        security: [["bearerAuth" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['language_name'],
            properties: [
                new OA\Property(property: 'language_name', type: 'string', example: 'German')
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Language created successfully")]
    public function storeLanguage(Request $request): JsonResponse
    {
        $request->validate([
            'language_name' => 'required|string|max:255|unique:language,LanguageName'
        ]);

        $language = Language::create([
            'LanguageName' => $request->input('language_name')
        ]);

        return response()->json([
            'message' => 'Language created successfully in the system',
            'data' => $language
        ], 201);
    }
}
