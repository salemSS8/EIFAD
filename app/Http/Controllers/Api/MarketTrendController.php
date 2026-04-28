<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Models\JobDemandSnapshot;
use App\Domain\AI\Models\SkillDemandSnapshot;
use App\Domain\Job\Models\Industry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * Market Trend Controller - Exposes labor market analytics.
 */
class MarketTrendController extends Controller
{
    /**
     * Get market trends data.
     */
    #[OA\Get(
        path: '/market-trends',
        operationId: 'getMarketTrendsData',
        tags: ['Market Trends'],
        summary: 'Get market trends data',
        description: 'Returns trending jobs and top 3 in-demand skills related to the seeker\'s field.',
    )]
    #[OA\Parameter(name: 'period', in: 'query', required: false, description: 'Time period (7d, 30d, 90d)', schema: new OA\Schema(type: 'string', default: '30d'))]
    #[OA\Parameter(name: 'industry_id', in: 'query', required: false, description: 'Filter by industry ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'city_name', in: 'query', required: false, description: 'Filter by city name', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Market trends data')]
    public function index(Request $request)
    {
        $period = $request->query('period', '30d');
        $industryId = $request->query('industry_id');
        $cityName = $request->query('city_name');

        // If industry_id is not provided, try to detect it from the authenticated job seeker
        if (!$industryId && auth('sanctum')->check()) {
            $user = auth('sanctum')->user();
            $industryId = $this->getJobSeekerIndustryId($user);
        }

        // If still no industry_id, we return empty results as requested ("not all jobs")
        if (!$industryId) {
            return response()->json([
                'trending_jobs' => ['labels' => [], 'values' => [], 'salaries' => []],
                'in_demand_skills' => ['labels' => [], 'values' => []],
                'message' => 'Please select an industry to view relevant trends.',
            ]);
        }

        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days)->toDateString();

        // Trending Jobs (filtered by industry)
        $jobs = JobDemandSnapshot::where('SnapshotDate', '>=', $startDate)
            ->where('industry_id', $industryId)
            ->when($cityName, fn ($q) => $q->where('city_name', $cityName))
            ->select('JobTitle', DB::raw('SUM(PostCount) as total_posts'), DB::raw('AVG(AverageSalary) as avg_salary'))
            ->groupBy('JobTitle')
            ->orderByDesc('total_posts')
            ->limit(10)
            ->get();

        // In-demand Skills (Top 3, filtered by industry)
        $skills = SkillDemandSnapshot::with('skill')
            ->where('SnapshotDate', '>=', $startDate)
            ->where('industry_id', $industryId)
            ->when($cityName, fn ($q) => $q->where('city_name', $cityName))
            ->select('SkillID', DB::raw('SUM(DemandCount) as total_demand'))
            ->groupBy('SkillID')
            ->orderByDesc('total_demand')
            ->limit(3)
            ->get();

        return response()->json([
            'trending_jobs' => [
                'labels' => $jobs->pluck('JobTitle'),
                'values' => $jobs->pluck('total_posts')->map(fn($v) => (int)$v),
                'salaries' => $jobs->pluck('avg_salary')->map(fn($v) => (float)$v),
            ],
            'in_demand_skills' => [
                'labels' => $skills->pluck('skill.SkillName'),
                'values' => $skills->pluck('total_demand')->map(fn($v) => (int)$v),
            ],
        ]);
    }

    /**
     * Detect job seeker's industry from their profile or applications.
     */
    private function getJobSeekerIndustryId($user): ?int
    {
        $latestJob = \App\Domain\Application\Models\JobApplication::where('JobSeekerID', $user->UserID)
            ->with('jobAd.company')
            ->latest('ApplicationID')
            ->first();

        if ($latestJob && $latestJob->jobAd && $latestJob->jobAd->company) {
            $industryName = $latestJob->jobAd->company->FieldOfWork;
            return Industry::where('name', $industryName)->value('id');
        }

        return null;
    }

    /**
     * Get available filters for market trends.
     */
    #[OA\Get(
        path: '/market-trends/filters',
        operationId: 'getMarketTrendFilters',
        tags: ['Market Trends'],
        summary: 'Get available filters',
        description: 'Returns list of industries and cities available for filtering trends.',
    )]
    #[OA\Response(response: 200, description: 'Available filters')]
    public function filters()
    {
        $industries = Industry::orderBy('name')->get(['id', 'name']);

        $cities = DB::table('jobdemandsnapshot')
            ->whereNotNull('city_name')
            ->distinct()
            ->pluck('city_name');

        return response()->json([
            'industries' => $industries,
            'cities' => $cities,
        ]);
    }
}
