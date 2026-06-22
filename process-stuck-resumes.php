<?php

/**
 * Process completed resume files stuck in temp storage
 * This will upload them to R2 and clean up temp files
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ResumeFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

echo "Processing Stuck Resume Files...\n\n";

// Find completed resume files still in temp storage
$resumeFiles = ResumeFile::where('status', 'completed')
    ->where('stored_path', 'like', 'tmp/resumes/%')
    ->whereNotNull('candidate_id')
    ->orderBy('id', 'desc')
    ->get();

if ($resumeFiles->isEmpty()) {
    echo "No completed resume files found in temp storage.\n";
    exit;
}

echo "Found {$resumeFiles->count()} completed resume files in temp storage.\n\n";

$processed = 0;
$failed = 0;

foreach ($resumeFiles as $rf) {
    echo "Processing ResumeFile ID: {$rf->id}\n";
    echo "  Temp Path: {$rf->stored_path}\n";

    // Get candidate data for filename generation
    $candidate = $rf->candidate;
    if (! $candidate) {
        echo "  ✗ No candidate found, skipping...\n\n";
        $failed++;

        continue;
    }

    // Check if temp file exists
    if (! Storage::disk('local')->exists($rf->stored_path)) {
        echo "  ✗ Temp file doesn't exist, skipping...\n\n";
        $failed++;

        continue;
    }

    try {
        // Read file
        $fileContents = Storage::disk('local')->get($rf->stored_path);
        if (empty($fileContents)) {
            throw new \Exception('File is empty');
        }

        // Get parsed data from candidate
        $parsed = $candidate->parsed_data ?? [];
        if (empty($parsed)) {
            // Fallback: use candidate data
            $parsed = [
                'title' => $candidate->title ?? 'Resume',
                'full_name' => $candidate->full_name ?? '',
            ];
        }

        // Generate filename (simplified version of the method)
        $title = preg_replace('/[^a-zA-Z0-9._-]/', '-', $parsed['title'] ?? 'Resume');
        $fullName = trim($parsed['full_name'] ?? '');
        $nameParts = preg_split('/\s+/', $fullName);
        $firstName = $nameParts[0] ?? 'Unknown';
        $lastName = count($nameParts) > 1 ? end($nameParts) : '';

        $firstName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $firstName);
        $lastName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $lastName);

        $uploadDate = $rf->created_at ?? now();
        $dateFormatted = $uploadDate->format('m-d-Y');

        $extension = pathinfo($rf->original_filename, PATHINFO_EXTENSION) ?: 'pdf';
        $newFilename = $title.'-'.$firstName;
        if (! empty($lastName)) {
            $newFilename .= '-'.$lastName;
        }
        $newFilename .= '-'.$dateFormatted.'.'.strtolower($extension);

        // Get tenant ID
        $tenantId = $rf->resumeBatch->user->tenant_id ?? 'default';
        $r2Directory = $tenantId ? "resumes/{$tenantId}" : 'resumes/default';
        $r2Path = $r2Directory.'/'.$newFilename;

        echo "  Uploading to R2: {$r2Path}\n";

        // Upload to R2
        $uploaded = Storage::disk('r2')->put($r2Path, $fileContents);
        if (! $uploaded) {
            throw new \Exception('R2 upload returned false');
        }

        // Verify upload
        if (! Storage::disk('r2')->exists($r2Path)) {
            throw new \Exception('File does not exist on R2 after upload');
        }

        // Update stored_path
        $rf->update(['stored_path' => $r2Path]);

        // Delete temp file
        Storage::disk('local')->delete($rf->stored_path);

        echo "  ✓ Successfully processed and uploaded to R2\n\n";
        $processed++;

    } catch (\Exception $e) {
        echo "  ✗ Error: {$e->getMessage()}\n\n";
        $failed++;
        Log::error('Failed to process stuck resume', [
            'resume_file_id' => $rf->id,
            'error' => $e->getMessage(),
        ]);
    }
}

echo "\nProcessing Complete:\n";
echo "  Processed: {$processed}\n";
echo "  Failed: {$failed}\n";
