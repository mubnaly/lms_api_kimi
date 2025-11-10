<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * Get certificate for completed enrollment
     */
    public function show(Enrollment $enrollment)
    {
        Gate::authorize('view', $enrollment);

        if (!$enrollment->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Course must be completed to access certificate',
                'progress' => $enrollment->progress,
            ], 403);
        }

        try {
            $certificateUrl = $this->certificateService->getCertificate($enrollment);

            return response()->json([
                'success' => true,
                'data' => [
                    'certificate_url' => $certificateUrl,
                    'student_name' => $enrollment->user->name,
                    'course_title' => $enrollment->course->title,
                    'completion_date' => $enrollment->completed_at->format('F d, Y'),
                    'certificate_id' => $this->certificateService->generateCertificateId($enrollment),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate certificate: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download certificate PDF
     */
    public function download(Enrollment $enrollment)
    {
        Gate::authorize('view', $enrollment);

        if (!$enrollment->isCompleted()) {
            abort(403, 'Course must be completed to download certificate');
        }

        $certificatePath = $enrollment->metadata['certificate_path'] ?? null;

        if (!$certificatePath) {
            // Generate if not exists
            $this->certificateService->generate($enrollment);
            $certificatePath = $enrollment->fresh()->metadata['certificate_path'];
        }

        return response()->download(
            storage_path('app/public/' . $certificatePath),
            sprintf(
                '%s-%s-certificate.pdf',
                str_replace(' ', '-', $enrollment->user->name),
                $enrollment->course->slug
            )
        );
    }

    /**
     * Verify certificate authenticity
     */
    public function verify(Request $request)
    {
        $request->validate([
            'certificate_id' => 'required|string',
        ]);

        $certificateData = $this->certificateService->verify($request->certificate_id);

        if (!$certificateData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid certificate ID or certificate not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $certificateData,
        ]);
    }

    /**
     * Get all certificates for authenticated user
     */
    public function index(Request $request)
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with('course')
            ->where('progress', 100)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->paginate(15);

        $certificates = $enrollments->map(function ($enrollment) {
            return [
                'enrollment_id' => $enrollment->id,
                'course_title' => $enrollment->course->title,
                'completion_date' => $enrollment->completed_at->format('F d, Y'),
                'certificate_url' => route('certificates.show', $enrollment),
                'download_url' => route('certificates.download', $enrollment),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $certificates,
            'meta' => [
                'total' => $enrollments->total(),
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
            ],
        ]);
    }
}
