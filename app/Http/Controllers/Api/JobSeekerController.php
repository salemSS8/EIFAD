<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class JobSeekerController extends Controller
{
    /**
     * Display a listing of job seekers with filtering and search.
     */
    #[OA\Get(
        path: '/job-seekers',
        operationId: 'getJobSeekers',
        tags: ['Job Seekers'],
        summary: 'Search and filter job seekers',
        description: 'Returns a paginated list of job seekers with optional search and location filters.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by name or email', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'location', in: 'query', description: 'Filter by location', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page (default 15)', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Paginated list of job seekers')]
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $location = $request->input('location');
        $perPage = min($request->integer('per_page', 15), 50);

        $query = \App\Domain\User\Models\JobSeekerProfile::with('user:UserID,FullName,Email,Phone,Avatar');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('FullName', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%");
            });
        }

        if ($location) {
            $query->where('Location', 'like', "%{$location}%");
        }

        $jobSeekers = $query->paginate($perPage);

        return response()->json($jobSeekers);
    }
}
