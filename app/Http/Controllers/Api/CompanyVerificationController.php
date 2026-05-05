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
        path: '/employer/verify/documents',
        operationId: 'uploadVerificationDocuments',
        tags: ['Company Verification'],
        summary: 'Upload verification documents (Fixed Indices)',
        description: 'Allows a company to upload verification files to specific fixed slots: Index 0, 1, or 2.
        - Index 0: Commercial Register (السجل التجاري)
        - Index 1: Tax Card (شهادة الضريبة)
        - Index 2: Additional/ID (رخصة الشركة)',
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'document_0', type: 'string', format: 'binary', description: 'Commercial Register (Index 0)'),
                    new OA\Property(property: 'document_1', type: 'string', format: 'binary', description: 'Tax Card (Index 1)'),
                    new OA\Property(property: 'document_2', type: 'string', format: 'binary', description: 'Additional/ID (Index 2)'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Documents uploaded successfully')]
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'documents' => 'nullable|array',
            'documents.0' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
            'documents.1' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
            'documents.2' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
            'document_0' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
            'document_1' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
            'document_2' => 'nullable|file|mimes:pdf,jpg,png,jpeg|max:10240',
        ]);

        $user = $request->user();
        $company = $user->companyProfile;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $documents = $company->VerificationDocuments ?? [];
        if (!is_array($documents)) { $documents = []; }

        $uploadedAny = false;

        foreach ([0, 1, 2] as $index) {
            // Check both documents[index] and document_index for compatibility
            $file = $request->file("documents.$index") ?? $request->file("document_$index");

            if ($file) {
                // Delete old file if exists
                if (isset($documents[$index]['path'])) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($documents[$index]['path']);
                }

                $path = $file->store('company_verifications/'.$company->CompanyID, 'local');
                
                $documents[$index] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
                $uploadedAny = true;
            }
        }

        if ($uploadedAny) {
            $company->update([
                'VerificationDocuments' => $documents,
                'VerificationStatus' => 'Pending',
            ]);
        }

        return response()->json([
            'message' => 'Documents uploaded successfully.',
            'data' => [
                'status' => $company->VerificationStatus,
                'documents' => $this->formatDocuments($company->VerificationDocuments),
            ],
        ]);
    }

    /**
     * Get verification status and list of uploaded documents.
     */
    #[OA\Get(
        path: '/employer/verify/status',
        operationId: 'getVerificationStatus',
        tags: ['Company Verification'],
        summary: 'Get verification status and document list',
        description: 'Returns the current verification status and a list of uploaded documents with fixed indices (0, 1, 2).',
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
                'documents' => $this->formatDocuments($company->VerificationDocuments),
            ],
        ]);
    }

    /**
     * Helper to format documents with fixed indices.
     */
    private function formatDocuments(?array $documents): array
    {
        $result = [];
        foreach ([0, 1, 2] as $index) {
            if (isset($documents[$index])) {
                $result[$index] = [
                    'index' => $index,
                    'name' => $documents[$index]['name'],
                    'uploaded_at' => $documents[$index]['uploaded_at'],
                    'url' => route('employer.verify.documents.serve', ['index' => $index]),
                ];
            } else {
                $result[$index] = null;
            }
        }
        return $result;
    }

    /**
     * Serve a specific verification document (Employer only).
     */
    #[OA\Get(
        path: '/employer/verify/documents/{index}',
        operationId: 'serveVerificationDocument',
        tags: ['Company Verification'],
        summary: 'Download a verification document',
        description: 'Streams the uploaded verification file directly to the browser.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'index', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'File stream')]
    public function serveDocument(Request $request, int $index)
    {
        $company = $request->user()->companyProfile;

        if (! $company) {
            abort(404, 'Company profile not found');
        }

        $documents = $company->VerificationDocuments ?? [];

        if (! isset($documents[$index])) {
            abort(404, 'Document not found');
        }

        $path = $documents[$index]['path'];

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404, 'File not found on storage');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path);
    }
}
