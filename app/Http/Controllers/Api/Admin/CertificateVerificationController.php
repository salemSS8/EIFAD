<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\CV\Models\CVCertification;
use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCertificateJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CertificateVerificationController extends Controller
{
    /**
     * Ensure user is Admin.
     */
    private function ensureIsAdmin(Request $request): void
    {
        if (! $request->user()->roles()->where('RoleName', 'Admin')->exists()) {
            abort(403, 'Unauthorized access');
        }
    }

    /**
     * List all certificates with optional status filter.
     */
    #[OA\Get(
        path: '/admin/certificates',
        operationId: 'adminListCertificates',
        tags: ['Admin Certificate Verification'],
        summary: 'List all certificates for admin review',
        description: 'Returns a paginated list of all certificates (pending, ai_reviewed, verified, rejected). Use ?status= to filter.',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filter by status (pending, ai_reviewed, verified, rejected)', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by certificate name', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Paginated list of certificates')]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    public function index(Request $request): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $query = CVCertification::with(['cv.jobSeeker.user:UserID,FullName,Email', 'verifiedByAdmin:UserID,FullName']);

        if ($request->filled('status')) {
            $query->where('VerificationStatus', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('CertificateName', 'like', '%'.$request->input('search').'%');
        }

        $certificates = $query->orderByRaw("FIELD(VerificationStatus, 'pending', 'ai_reviewed', 'verified', 'rejected')")
            ->paginate(15);

        return response()->json($certificates);
    }

    /**
     * Show certificate details with AI analysis results.
     */
    #[OA\Get(
        path: '/admin/certificates/{id}',
        operationId: 'adminShowCertificate',
        tags: ['Admin Certificate Verification'],
        summary: 'Get certificate details with AI results',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Certificate details')]
    #[OA\Response(response: 404, description: 'Certificate not found')]
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $certification = CVCertification::with(['cv.jobSeeker.user:UserID,FullName,Email', 'verifiedByAdmin:UserID,FullName'])
            ->where('CertificationID', $id)
            ->firstOrFail();

        return response()->json(['data' => $certification]);
    }

    /**
     * Verify (approve) a certificate.
     */
    #[OA\Put(
        path: '/admin/certificates/{id}/verify',
        operationId: 'adminVerifyCertificate',
        tags: ['Admin Certificate Verification'],
        summary: 'Approve a certificate',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'notes', type: 'string', example: 'تم التحقق يدوياً'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Certificate verified')]
    public function verify(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $certification = CVCertification::where('CertificationID', $id)->firstOrFail();

        $certification->update([
            'VerificationStatus' => 'verified',
            'IsVerified' => true,
            'VerificationNotes' => $request->input('notes', 'تم التحقق والقبول من قبل الأدمن'),
            'VerifiedAt' => now(),
            'VerifiedBy' => $request->user()->UserID,
        ]);

        return response()->json([
            'message' => 'Certificate verified successfully',
            'data' => $certification->fresh(),
        ]);
    }

    /**
     * Reject a certificate.
     */
    #[OA\Put(
        path: '/admin/certificates/{id}/reject',
        operationId: 'adminRejectCertificate',
        tags: ['Admin Certificate Verification'],
        summary: 'Reject a certificate',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['reason'],
            properties: [
                new OA\Property(property: 'reason', type: 'string', example: 'الشهادة غير صالحة أو مزورة'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Certificate rejected')]
    public function reject(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $certification = CVCertification::where('CertificationID', $id)->firstOrFail();

        $certification->update([
            'VerificationStatus' => 'rejected',
            'IsVerified' => false,
            'VerificationNotes' => 'مرفوضة: '.$request->input('reason'),
            'VerifiedAt' => now(),
            'VerifiedBy' => $request->user()->UserID,
        ]);

        return response()->json([
            'message' => 'Certificate rejected',
            'data' => $certification->fresh(),
        ]);
    }

    /**
     * Re-analyze a certificate with AI.
     */
    #[OA\Post(
        path: '/admin/certificates/{id}/reanalyze',
        operationId: 'adminReanalyzeCertificate',
        tags: ['Admin Certificate Verification'],
        summary: 'Re-run AI analysis on a certificate',
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'AI re-analysis queued')]
    public function reanalyze(Request $request, int $id): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $certification = CVCertification::where('CertificationID', $id)->firstOrFail();

        $certification->update([
            'VerificationStatus' => 'pending',
            'VerificationNotes' => 'إعادة التحليل بالذكاء الاصطناعي...',
        ]);

        AnalyzeCertificateJob::dispatch($certification, 'job_seeker');

        return response()->json([
            'message' => 'AI re-analysis has been queued',
            'data' => $certification->fresh(),
        ]);
    }
}
