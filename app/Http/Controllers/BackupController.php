<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function downloadSql()
    {
        // 1. Run the backup command
        Artisan::call('backup:run', [
            '--only-db' => true,
        ]);

        // 2. Find the latest backup file
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $files = $disk->files('SferaPOS');
        $backupFiles = array_filter($files, function ($file) {
            return str_ends_with($file, '.zip');
        });
        if (empty($backupFiles)) {
            abort(500, 'No backup files found.');
        }
        // Sort by modified time, descending
        usort($backupFiles, function ($a, $b) use ($disk) {
            return $disk->lastModified($b) <=> $disk->lastModified($a);
        });
        $latestBackup = $backupFiles[0];
        $filename = basename($latestBackup);

        // 3. Return the file as a download
        return $disk->download($latestBackup, $filename);
    }
}
