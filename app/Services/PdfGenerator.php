<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PdfGenerator
{
    public static function generateExamSummaryPdf(string $title, array $exam): string
    {
        // Normalize dates to d/m/Y H:i:s (America/Lima)
        if (! empty($exam['started_at'])) {
            try {
                $exam['started_at'] = Carbon::parse($exam['started_at'])->setTimezone('America/Lima')->format('d/m/Y H:i:s');
            } catch (\Throwable $e) {
                // keep original if parsing fails
            }
        }

        if (! empty($exam['completed_at'])) {
            try {
                $exam['completed_at'] = Carbon::parse($exam['completed_at'])->setTimezone('America/Lima')->format('d/m/Y H:i:s');
            } catch (\Throwable $e) {
                // keep original if parsing fails
            }
        }

        // Prefer storage logo file for Dompdf compatibility (provided by user)
        $storageLogo = storage_path('app/public/logo_drbank.png');
        if (file_exists($storageLogo)) {
            // Dompdf on Windows prefers file:/// paths with forward slashes
            $logo = 'file:///' . str_replace('\\', '/', $storageLogo);
        } else {
            // fallback to public logo or remote URL
            $localLogo = public_path('assets/images/logo/logo.png');
            $logo = file_exists($localLogo) ? $localLogo : 'https://drbank.startupdev.tech/assets/images/logo/logo.png';
        }

        $data = [
            'exam' => $exam,
            'title' => $title,
            'logo' => $logo,
        ];

        $pdf = Pdf::loadView('pdfs.exam_summary', $data);

        return $pdf->output();
    }
}
