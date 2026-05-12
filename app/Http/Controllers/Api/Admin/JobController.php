<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Job\Models\JobAd;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Admin Job Controller - Manage job ads across the platform.
 */
class JobController extends Controller
{
    /**
     * Ensure user is Admin.
     */
    private function ensureIsAdmin(Request $request)
    {
        if (! $request->user()->roles()->where('RoleName', 'Admin')->exists()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * Get all jobs for admin management.
     */
    #[OA\Get(
        path: '/admin/jobs',
        operationId: 'adminGetJobs',
        tags: ['Admin Jobs Management'],
        summary: 'Get all job ads',
        description: 'Returns a paginated list of all job ads, including deleted ones. Filters: status, search, company_id.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (e.g., Active, Draft, Closed, Deleted)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by job title', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'company_id', in: 'query', description: 'Filter by company ID', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of jobs')]
    public function index(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $query = JobAd::withTrashed()->with('company');

        if ($request->filled('status')) {
            $query->where('Status', $request->status);
        }

        if ($request->filled('company_id')) {
            $query->where('CompanyID', $request->company_id);
        }

        if ($request->filled('search')) {
            $query->where('Title', 'like', '%' . $request->search . '%');
        }

        $jobs = $query->orderByDesc('PostedAt')->paginate(15);

        return response()->json($jobs);
    }

    /**
     * Get a single job's details.
     */
    #[OA\Get(
        path: '/admin/jobs/{id}',
        operationId: 'adminShowJob',
        tags: ['Admin Jobs Management'],
        summary: 'Get job details',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job details')]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $job = JobAd::withTrashed()->with(['company', 'skills.skill'])->findOrFail($id);

        return response()->json(['data' => $job]);
    }

    /**
     * Update job details (including Status and restoration).
     */
    #[OA\Put(
        path: '/admin/jobs/{id}',
        operationId: 'adminUpdateJob',
        tags: ['Admin Jobs Management'],
        summary: 'Update a job ad',
        description: 'Updates job ad fields. If status is updated to something other than Deleted, the job is restored. If updated to Deleted, it is soft-deleted.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'status', type: 'string', description: 'Status can be Active, Draft, Closed, Deleted, etc.'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Job updated successfully')]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $job = JobAd::withTrashed()->findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|string|max:50',
        ]);

        $status = $request->input('status', $job->Status);

        // Handle Restore or Delete based on Status
        if ($status !== 'Deleted' && $job->trashed()) {
            $job->restore();
        } elseif ($status === 'Deleted' && !$job->trashed()) {
            $job->delete();
        }

        $job->update([
            'Title' => $request->input('title', $job->Title),
            'Description' => $request->input('description', $job->Description),
            'Status' => $status,
        ]);

        return response()->json([
            'message' => 'Job updated successfully',
            'data' => $job->fresh(),
        ]);
    }

    /**
     * Delete a job (Soft delete + set Status to Deleted).
     */
    #[OA\Delete(
        path: '/admin/jobs/{id}',
        operationId: 'adminDeleteJob',
        tags: ['Admin Jobs Management'],
        summary: 'Delete a job ad',
        description: 'Soft deletes the job and changes its Status to "Deleted".',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Job deleted successfully')]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $job = JobAd::withTrashed()->findOrFail($id);

        if (!$job->trashed()) {
            $job->delete();
        }

        $job->update(['Status' => 'Deleted']);

        return response()->json(['message' => 'Job deleted successfully']);
    }
}
