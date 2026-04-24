<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

#[Signature('photos:backup')]
#[Description('Zip all fountain photos and upload the archive to Google Drive')]
class BackupPhotosToDrive extends Command
{
    public function handle(): int
    {
        $clientId = config('services.google_drive.client_id');
        $clientSecret = config('services.google_drive.client_secret');
        $refreshToken = config('services.google_drive.refresh_token');
        $folderId = config('services.google_drive.folder_id');

        foreach ([
            'GOOGLE_DRIVE_CLIENT_ID' => $clientId,
            'GOOGLE_DRIVE_CLIENT_SECRET' => $clientSecret,
            'GOOGLE_DRIVE_REFRESH_TOKEN' => $refreshToken,
            'GOOGLE_DRIVE_FOLDER_ID' => $folderId,
        ] as $name => $value) {
            if (! $value) {
                $this->error("{$name} is not set. Run `php artisan drive:authorize` to mint a refresh token.");

                return self::FAILURE;
            }
        }

        $photosDir = Storage::disk('public')->path('fountain_photos');
        if (! is_dir($photosDir)) {
            $this->info('No photos directory found — nothing to back up.');

            return self::SUCCESS;
        }

        $archiveName = 'fountain-photos-'.now()->format('Y-m-d').'.zip';
        $archivePath = storage_path('app/'.$archiveName);

        $this->info("Creating archive {$archiveName}…");
        $fileCount = $this->createZip($photosDir, $archivePath);
        $this->info("Archived {$fileCount} file(s), ".$this->humanBytes(filesize($archivePath)).'.');

        try {
            $this->info('Uploading to Google Drive…');
            $driveFileId = $this->upload($clientId, $clientSecret, $refreshToken, $folderId, $archiveName, $archivePath);
            $this->info("Uploaded: {$driveFileId}");
        } finally {
            @unlink($archivePath);
        }

        return self::SUCCESS;
    }

    private function createZip(string $sourceDir, string $archivePath): int
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not open {$archivePath} for writing.");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $count = 0;
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $zip->addFile($file->getPathname(), ltrim(str_replace($sourceDir, '', $file->getPathname()), DIRECTORY_SEPARATOR));
            $count++;
        }

        $zip->close();

        return $count;
    }

    private function upload(string $clientId, string $clientSecret, string $refreshToken, string $folderId, string $name, string $path): string
    {
        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->addScope(GoogleDrive::DRIVE_FILE);
        $client->refreshToken($refreshToken);

        $drive = new GoogleDrive($client);

        $metadata = new DriveFile([
            'name' => $name,
            'parents' => [$folderId],
        ]);

        $file = $drive->files->create($metadata, [
            'data' => file_get_contents($path),
            'mimeType' => 'application/zip',
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        return $file->id;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
