<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CompanyVerificationController extends Controller
{
    /**
     * Upload verification documents for a company.
     */
    #[OA\Post(
        path: '/company/verify/documents',
        operationId: 'uploadVerificationDocuments',
        tags: ['Company Verification'],
        summary: 'Upload verification documents',
        description: 'Allows a company to upload verification files (PDF/Images) to a private storage.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['documents'],
                properties: [
                    new OA\Property(
                        property: 'documents',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'binary'),
                        description: 'Verification files (PDF, JPG, PNG)'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Documents uploaded successfully')]
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'required|file|mimes:pdf,jpg,png,jpeg|max:10240', // 10MB limit
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $uploadedPaths = $company->VerificationDocuments ?? [];

        foreach ($request->file('documents') as $file) {
            $path = $file->store('company_verifications/'.$company->CompanyID, 'local');
            $uploadedPaths[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        $company->update([
            'VerificationDocuments' => $uploadedPaths,
            'VerificationStatus' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Documents uploaded successfully. Your verification is now pending.',
            'data' => [
                'status' => $company->VerificationStatus,
                'documents_count' => count($uploadedPaths),
            ],
        ]);
    }

    /**
     * Get verification status for the authenticated company.
     */
    #[OA\Get(
        path: '/company/verify/status',
        operationId: 'getVerificationStatus',
        tags: ['Company Verification'],
        summary: 'Get verification status',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Response(response: 200, description: 'Verification status details')]
    public function status(Request $request): JsonResponse
    {
        $company = $request->user()->companyProfile;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        return response()->json([
            'data' => [
                'is_verified' => $company->IsCompanyVerified,
                'status' => $company->VerificationStatus,
                'verified_at' => $company->VerifiedAt,
                'documents' => collect($company->VerificationDocuments)->map(fn ($doc) => ['name' => $doc['name'], 'uploaded_at' => $doc['uploaded_at']]),
            ],
        ]);
    }
}
