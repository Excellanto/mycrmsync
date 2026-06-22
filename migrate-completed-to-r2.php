<?php

/**
 * Migrate completed resume files from local storage to R2
 * Run: php migrate-completed-to-r2.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ResumeFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

echo "Migrating Completed Resume Files to R2...\n\n";

$resumeFiles = ResumeFile::where('status', 'completed')
    ->whereNotNull('candidate_id')
    ->orderBy('id', 'desc')
    ->get();

if ($resumeFiles->isEmpty()) {
    echo "No completed resume files found.\n";
    exit;
}

echo "Found {$resumeFiles->count()} completed resume files.\n\n";

$migrated = 0;
$skipped = 0;
$failed = 0;

foreach ($resumeFiles as $rf) {
    echo "Processing ResumeFile ID: {$rf->id}\n";
    echo "  Path: {$rf->stored_path}\n";

    // Check if already on R2
    if (Storage::disk('r2')->exists($rf->stored_path)) {
        echo "  Already on R2, skipping...\n\n";
        $skipped++;

        continue;
    }

    // Check if exists locally
    if (! Storage::disk('local')->exists($rf->stored_path)) {
        echo "  Not found locally, skipping...\n\n";
        $skipped++;

        continue;
    }

    try {
        // Read file from local
        $fileContents = Storage::disk('local')->get($rf->stored_path);

        if (empty($fileContents)) {
            echo "  File is empty, skipping...\n\n";
            $skipped++;

            continue;
        }

        // Upload to R2 (keep same path for now)
        echo "  Uploading to R2...\n";
        $uploaded = Storage::disk('r2')->put($rf->stored_path, $fileContents);

        if (! $uploaded) {
            throw new \Exception('Upload returned false');
        }

        // Verify upload
        if (! Storage::disk('r2')->exists($rf->stored_path)) {
            throw new \Exception('File does not exist on R2 after upload');
        }

        // Delete local file
        Storage::disk('local')->delete($rf->stored_path);

        echo "  ✓ Successfully migrated to R2\n\n";
        $migrated++;

    } catch (\Exception $e) {
        echo "  ✗ Error: {$e->getMessage()}\n\n";
        $failed++;
        Log::error('Failed to migrate resume to R2', [
            'resume_file_id' => $rf->id,
            'stored_path' => $rf->stored_path,
            'error' => $e->getMessage(),
        ]);
    }
}

echo "\nMigration Complete:\n";
echo "  Migrated: {$migrated}\n";
echo "  Skipped: {$skipped}\n";
echo "  Failed: {$failed}\n";
