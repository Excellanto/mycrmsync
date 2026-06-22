<?php

namespace App\Scribe;

use Illuminate\Support\Facades\File;
use Knuckles\Scribe\Writing\Writer;

/**
 * Scribe's default Writer uses PHP rename() with mixed relative/absolute paths,
 * which fails on Windows with "Access is denied (code: 5)".
 */
final class WindowsSafeWriter extends Writer
{
    protected function performFinalTasksForLaravelType(): void
    {
        File::ensureDirectoryExists($this->laravelTypeOutputPath);

        $assetsDestination = public_path(trim(str_replace('\\', '/', $this->laravelAssetsPath), '/'));
        $staticOutput = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $this->staticTypeOutputPath), DIRECTORY_SEPARATOR);

        File::ensureDirectoryExists(dirname($assetsDestination));

        $indexSource = $staticOutput.DIRECTORY_SEPARATOR.'index.html';
        $indexDestination = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $this->laravelTypeOutputPath), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'index.blade.php';

        if (is_file($indexSource)) {
            File::move($indexSource, $indexDestination);
        }

        if (is_dir($assetsDestination)) {
            File::deleteDirectory($assetsDestination);
        }

        if (is_dir($staticOutput)) {
            File::copyDirectory($staticOutput, $assetsDestination);
            File::deleteDirectory($staticOutput);
            File::ensureDirectoryExists($staticOutput);
        }

        $contents = file_get_contents($indexDestination);

        $contents = preg_replace(
            '#href="\.\./docs/css/(.+?)"#',
            'href="{{ asset("'.$this->laravelAssetsPath.'/css/$1") }}"',
            $contents
        );
        $contents = preg_replace(
            '#src="\.\./docs/(js|images)/(.+?)"#',
            'src="{{ asset("'.$this->laravelAssetsPath.'/$1/$2") }}"',
            $contents
        );
        $contents = str_replace(
            'href="../docs/collection.json"',
            'href="{{ route("'.$this->paths->outputPath('postman', '.').'") }}"',
            $contents
        );
        $contents = str_replace(
            'href="../docs/openapi.yaml"',
            'href="{{ route("'.$this->paths->outputPath('openapi', '.').'") }}"',
            $contents
        );
        $contents = str_replace(
            'url="../docs/openapi.yaml"',
            'url="{{ route("'.$this->paths->outputPath('openapi', '.').'") }}"',
            $contents
        );
        $contents = str_replace(
            'Url="../docs/openapi.yaml"',
            'Url="{{ route("'.$this->paths->outputPath('openapi', '.').'") }}"',
            $contents
        );

        file_put_contents($indexDestination, $contents);
    }
}
