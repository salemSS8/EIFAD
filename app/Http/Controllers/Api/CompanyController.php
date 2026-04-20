<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Models\CompanyProfile;
use App\Domain\Company\Models\FollowCompany;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Company Controller - Manages public company listings and followers.
 */
class CompanyController extends Controller
{
    /**
     * Get a list of verified companies with optional filtering.
     */
    #[OA\Get(
        path: '/companies',
        operationId: 'getCompanies',
        tags: ['Companies'],
        summary: 'Get verified companies',
        description: 'Returns a paginated list of all verified companies with optional filtering by name, location, and field.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'name', in: 'query', description: 'Filter by company name', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'location', in: 'query', description: 'Filter by address/location', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'field', in: 'query', description: 'Filter by job field/field of work', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'List of companies')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['name', 'location', 'field']);

        $companies = (new \App\Domain\Company\Actions\SearchCompaniesAction)->execute($filters);

        return response()->json($companies);
    }

    /**
     * Get details of a specific company.
     */
    #[OA\Get(
        path: '/companies/{id}',
        operationId: 'getCompanyDetails',
        tags: ['Companies'],
        summary: 'Get company details',
        description: 'Returns details of a specific company along with its active job ads.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Company ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Company details')]
    #[OA\Response(response: 404, description: 'Company not found')]
    public function show(int $id): JsonResponse
    {
        $company = CompanyProfile::with('jobAds')
            ->where('CompanyID', $id)
            ->firstOrFail();

        return response()->json(['data' => $company]);
    }

    /**
     * Follow a company.
     */
    #[OA\Post(
        path: '/companies/{id}/follow',
        operationId: 'followCompany',
        tags: ['Companies', 'Follow'],
        summary: 'Follow a company',
        description: 'Allows a job seeker to follow a specific company.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Company ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Company followed successfully')]
    #[OA\Response(response: 403, description: 'Only job seekers can follow companies')]
    public function follow(Request $request, int $id): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;
        if (! $profile) {
            return response()->json(['message' => 'Only job seekers can follow companies'], 403);
        }

        FollowCompany::firstOrCreate([
            'JobSeekerID' => $profile->JobSeekerID,
            'CompanyID' => $id,
        ], ['FollowedAt' => now()]);

        return response()->json(['message' => 'Company followed']);
    }

    /**
     * Unfollow a company.
     */
    #[OA\Delete(
        path: '/companies/{id}/follow',
        operationId: 'unfollowCompany',
        tags: ['Companies', 'Follow'],
        summary: 'Unfollow a company',
        description: 'Allows a job seeker to unfollow a company.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Company ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Unfollowed company successfully')]
    public function unfollow(Request $request, int $id): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;

        if ($profile) {
            FollowCompany::where('JobSeekerID', $profile->JobSeekerID)
                ->where('CompanyID', $id)
                ->delete();
        }

        return response()->json(['message' => 'Unfollowed company']);
    }

    /**
     * Get companies followed by the authenticated user.
     */
    #[OA\Get(
        path: '/companies/following',
        operationId: 'getFollowingCompanies',
        tags: ['Companies', 'Follow'],
        summary: 'Get followed companies',
        description: 'Returns a list of companies followed by the current job seeker.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'List of followed companies')]
    public function following(Request $request): JsonResponse
    {
        $profile = $request->user()->jobSeekerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Only job seekers can follow companies'], 403);
        }

        $following = FollowCompany::with('company')
            ->where('JobSeekerID', $profile->JobSeekerID)
            ->paginate(15);

        return response()->json($following);
    }
}
