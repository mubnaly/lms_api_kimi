<?php

namespace App\Services;

use App\Models\{Enrollment, User};
use Illuminate\Support\Facades\{Storage, Log};
use Barryvdh\DomPDF\Facade\Pdf;

class CertificateService
{
    protected string $storagePath = 'certificates';

    /**
     * Generate completion certificate for enrollment
     */
    public function generate(Enrollment $enrollment): string
    {
        if (!$enrollment->isCompleted()) {
            throw new \Exception('Course must be completed before generating certificate');
        }

        $certificateData = $this->prepareCertificateData($enrollment);
        $pdf = $this->createPDF($certificateData);

        return $this->saveCertificate($pdf, $enrollment);
    }

    /**
     * Prepare certificate data
     */
    protected function prepareCertificateData(Enrollment $enrollment): array
    {
        return [
            'student_name' => $enrollment->user->name,
            'course_title' => $enrollment->course->title,
            'instructor_name' => $enrollment->course->instructor->name,
            'completion_date' => $enrollment->completed_at->format('F d, Y'),
            'certificate_id' => $this->generateCertificateId($enrollment),
            'tenant_name' => tenant()?->name ?? config('app.name'),
            'tenant_logo' => $this->getTenantLogo(),
            'duration' => format_duration($enrollment->course->duration),
            'rating' => $enrollment->course->rating,
        ];
    }

    /**
     * Create PDF from certificate template
     */
    protected function createPDF(array $data): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('certificates.template', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable_remote', true);
    }

    /**
     * Save certificate to storage
     */
    protected function saveCertificate(\Barryvdh\DomPDF\PDF $pdf, Enrollment $enrollment): string
    {
        $filename = $this->generateFilename($enrollment);
        $path = "{$this->storagePath}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        // Update enrollment metadata
        $enrollment->update([
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'certificate_path' => $path,
                'certificate_generated_at' => now()->toISOString(),
            ])
        ]);

        Log::info('Certificate generated', [
            'enrollment_id' => $enrollment->id,
            'path' => $path,
        ]);

        return Storage::disk('public')->url($path);
    }

    /**
     * Generate unique certificate ID
     */
    protected function generateCertificateId(Enrollment $enrollment): string
    {
        return strtoupper(sprintf(
            'CERT-%s-%s-%s',
            tenant()?->id ?? 'MAIN',
            $enrollment->course_id,
            $enrollment->id
        ));
    }

    /**
     * Generate certificate filename
     */
    protected function generateFilename(Enrollment $enrollment): string
    {
        $studentName = str_replace(' ', '-', $enrollment->user->name);
        $courseSlug = $enrollment->course->slug;

        return "{$studentName}-{$courseSlug}-certificate.pdf";
    }

    /**
     * Get tenant logo URL
     */
    protected function getTenantLogo(): ?string
    {
        if ($tenant = tenant()) {
            return $tenant->logo_url;
        }

        return asset('images/default-logo.png');
    }

    /**
     * Verify certificate authenticity
     */
    public function verify(string $certificateId): ?array
    {
        // Extract enrollment ID from certificate ID
        $parts = explode('-', $certificateId);
        $enrollmentId = end($parts);

        $enrollment = Enrollment::with(['user', 'course.instructor'])
            ->where('id', $enrollmentId)
            ->where('progress', 100)
            ->first();

        if (!$enrollment) {
            return null;
        }

        return [
            'valid' => true,
            'student_name' => $enrollment->user->name,
            'course_title' => $enrollment->course->title,
            'instructor_name' => $enrollment->course->instructor->name,
            'completion_date' => $enrollment->completed_at->format('F d, Y'),
            'certificate_id' => $certificateId,
        ];
    }

    /**
     * Get certificate for enrollment
     */
    public function getCertificate(Enrollment $enrollment): ?string
    {
        if (!$enrollment->isCompleted()) {
            return null;
        }

        $certificatePath = $enrollment->metadata['certificate_path'] ?? null;

        if (!$certificatePath || !Storage::disk('public')->exists($certificatePath)) {
            return $this->generate($enrollment);
        }

        return Storage::disk('public')->url($certificatePath);
    }

    /**
     * Delete certificate
     */
    public function delete(Enrollment $enrollment): bool
    {
        $certificatePath = $enrollment->metadata['certificate_path'] ?? null;

        if ($certificatePath && Storage::disk('public')->exists($certificatePath)) {
            Storage::disk('public')->delete($certificatePath);

            $enrollment->update([
                'metadata' => array_merge($enrollment->metadata ?? [], [
                    'certificate_path' => null,
                    'certificate_deleted_at' => now()->toISOString(),
                ])
            ]);

            return true;
        }

        return false;
    }
}
