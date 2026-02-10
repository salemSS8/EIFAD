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
}
