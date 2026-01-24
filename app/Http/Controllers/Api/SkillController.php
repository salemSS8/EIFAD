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
class SkillController extends Controller
{
    /**
     * Get all skills.
     */
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
    public function languages(): JsonResponse
    {
        $languages = Language::orderBy('LanguageName')->get();

        return response()->json(['data' => $languages]);
    }
}
