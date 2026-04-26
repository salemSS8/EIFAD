<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Company\Models\CompanyProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CompanyVerificationController extends Controller
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
     * Get all companies for verification management.
     */
    #[OA\Get(
        path: '/admin/companies',
        operationId: 'adminGetCompanies',
        tags: ['Admin', 'Company Verification'],
        summary: 'Get all companies for management',
        description: 'Returns a paginated list of companies including their verification status.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (Unverified, Pending, Verified, Rejected)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by company name', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'List of companies')]
    public function index(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $query = CompanyProfile::with('user:UserID,FullName,Email');

        if ($request->filled('status')) {
            $query->where('VerificationStatus', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('CompanyName', 'like', '%'.$request->input('search').'%');
        }

        $companies = $query->paginate(15);

        return response()->json($companies);
    }

    /**
     * Update company verification status.
     */
    #[OA\Put(
        path: '/admin/companies/{id}/verify',
        operationId: 'adminVerifyCompany',
        tags: ['Admin', 'Company Verification'],
        summary: 'Verify or reject a company',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['Verified', 'Rejected']),
                new OA\Property(property: 'notes', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated')]
    public function verify(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $request->validate([
            'status' => 'required|in:Verified,Rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $company = CompanyProfile::where('CompanyID', $id)->firstOrFail();

        $isVerified = ($request->input('status') === 'Verified');

        $company->update([
            'VerificationStatus' => $request->input('status'),
            'IsCompanyVerified' => $isVerified,
            'VerifiedAt' => $isVerified ? now() : null,
        ]);

        // Create Notification for the Company
        \App\Domain\Communication\Models\Notification::create([
            'UserID' => $company->CompanyID,
            'Type' => 'Verification Update',
            'Content' => "Your company verification status has been updated to: {$company->VerificationStatus}.",
            'IsRead' => false,
            'CreatedAt' => now(),
        ]);

        return response()->json([
            'message' => 'Company verification status updated',
            'data' => $company->fresh(),
        ]);
    }

    /**
     * Get a temporary link for a verification document.
     */
    #[OA\Get(
        path: '/admin/companies/{id}/documents/{index}',
        operationId: 'adminGetCompanyDocument',
        tags: ['Admin', 'Company Verification'],
        summary: 'Get temporary document link',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'index', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Temporary URL')]
    public function getDocument(Request $request, int $id, int $index): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $company = CompanyProfile::where('CompanyID', $id)->firstOrFail();
        $documents = $company->VerificationDocuments ?? [];

        if (! isset($documents[$index])) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $path = $documents[$index]['path'];

        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File not found on storage'], 404);
        }

        // Generate a temporary URL if using S3, or a signed local route
        // Since we are using 'local' disk which is not public, we serve it via a controller.
        $url = route('admin.company.document.serve', ['id' => $id, 'index' => $index]);

        return response()->json([
            'url' => $url,
            'name' => $documents[$index]['name'],
        ]);
    }

    /**
     * Serve the document file directly (Admin only).
     */
    public function serveDocument(Request $request, int $id, int $index)
    {
        $this->ensureIsAdmin($request);

        $company = CompanyProfile::where('CompanyID', $id)->firstOrFail();
        $documents = $company->VerificationDocuments ?? [];

        if (! isset($documents[$index])) {
            abort(404);
        }

        $path = $documents[$index]['path'];

        return Storage::disk('local')->response($path);
    }
}
