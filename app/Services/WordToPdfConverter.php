<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Reader\Word2007;
use PhpOffice\PhpWord\Settings;
use Throwable;

class WordToPdfConverter
{
    /**
     * Convert DOC or DOCX to PDF.
     * Uses LibreOffice first (supports both formats), falls back to PhpWord for DOCX only.
     *
     * @param  string  $wordPath  Full path (may have .bin if stored without correct extension)
     * @param  string|null  $resolvedExt  Resolved extension from original filename: 'doc' or 'docx'
     */
    public function convert(string $wordPath, ?string $resolvedExt = null): ?string
    {
        $wordPath = realpath($wordPath) ?: $wordPath;
        if (! is_readable($wordPath)) {
            Log::error('Word to PDF conversion failed - file not readable', ['path' => $wordPath]);

            return null;
        }

        $ext = $resolvedExt ?? strtolower(pathinfo($wordPath, PATHINFO_EXTENSION));
        $dir = dirname($wordPath);
        $baseName = pathinfo($wordPath, PATHINFO_FILENAME);
        $pdfPath = $dir.DIRECTORY_SEPARATOR.$baseName.'.pdf';

        // 1. Try LibreOffice first (supports both .doc and .docx)
        $result = $this->convertWithLibreOffice($wordPath, $pdfPath);
        if ($result !== null) {
            return $result;
        }

        // 2. Fallback to PhpWord only for DOCX (PhpWord does not support .doc)
        if ($ext === 'docx') {
            $result = $this->convertWithPhpWord($wordPath, $pdfPath);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    private function getLibreOfficeCommand(): ?string
    {
        $configured = config('services.libreoffice.path') ?: env('LIBREOFFICE_PATH');
        if (! empty($configured)) {
            $path = trim($configured);
            // Normalize slashes for Windows
            if (str_starts_with(PHP_OS, 'WIN')) {
                $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
            }
            if (file_exists($path)) {
                return str_contains($path, ' ') ? '"'.$path.'"' : $path;
            }
            Log::warning('LibreOffice path from config does not exist', ['path' => $path]);
        }

        if (! str_starts_with(PHP_OS, 'WIN')) {
            return 'soffice'; // Linux: assume in PATH
        }

        // Windows: try common install locations
        $candidates = [
            'C:\Program Files\LibreOffice\program\soffice.com',
            'C:\Program Files\LibreOffice\program\soffice.exe',
            'C:\Program Files (x86)\LibreOffice\program\soffice.com',
            'C:\Program Files (x86)\LibreOffice\program\soffice.exe',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return '"'.$candidate.'"';
            }
        }

        Log::warning('LibreOffice not found at any configured or common path');

        return null;
    }

    private function convertWithLibreOffice(string $wordPath, string $pdfPath): ?string
    {
        $soffice = $this->getLibreOfficeCommand();
        if ($soffice === null) {
            return null;
        }

        $dir = dirname($wordPath);
        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            $soffice,
            escapeshellarg($dir),
            escapeshellarg($wordPath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && file_exists($pdfPath)) {
            Log::info('LibreOffice conversion succeeded', ['path' => $wordPath]);

            return $pdfPath;
        }

        Log::warning('LibreOffice conversion failed', [
            'path' => $wordPath,
            'exit_code' => $exitCode,
            'output' => $output,
        ]);

        return null;
    }

    private function convertWithPhpWord(string $docxPath, string $pdfPath): ?string
    {
        $result = $this->convertWithRenderer($docxPath, $pdfPath, Settings::PDF_RENDERER_MPDF, 'vendor/mpdf/mpdf');
        if ($result !== null) {
            return $result;
        }

        $result = $this->convertWithRenderer($docxPath, $pdfPath, Settings::PDF_RENDERER_DOMPDF, 'vendor/dompdf/dompdf');
        if ($result !== null) {
            return $result;
        }

        return null;
    }

    private function convertWithRenderer(string $docxPath, string $pdfPath, string $rendererName, string $rendererPath): ?string
    {
        $fullPath = realpath(base_path($rendererPath)) ?: base_path($rendererPath);
        if (! is_dir($fullPath)) {
            Log::debug("PDF renderer path not found: {$rendererPath}");

            return null;
        }

        try {
            Settings::setPdfRendererName($rendererName);
            Settings::setPdfRendererPath($fullPath);

            $reader = new Word2007;
            $reader->setImageLoading(false);
            $phpWord = $reader->load($docxPath);

            $writer = IOFactory::createWriter($phpWord, 'PDF');
            $writer->save($pdfPath);

            return file_exists($pdfPath) ? $pdfPath : null;
        } catch (Throwable $e) {
            Log::warning("PhpWord conversion failed with {$rendererName}", [
                'path' => $docxPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
