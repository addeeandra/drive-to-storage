<?php

declare(strict_types=1);

namespace Addeeandra\DriveToStorage;

use Illuminate\Support\Facades\Storage;

final class GoogleDriveStorage implements Contracts\StoreContract
{
    protected string $drivePublicUrl;

    protected array $storageConfig = [
        'driver' => 'local',
        'path' => '',
        'store_as' => '',
    ];

    public function useStorage(string $driver, string $storePath = '', string $storeAs = ''): self
    {
        $this->storageConfig = [
            'driver' => $driver,
            'path' => $storePath,
            'store_as' => $storeAs,
        ];

        return $this;
    }

    public function usePublicUrl(string $url): self
    {
        $this->drivePublicUrl = $url;

        return $this;
    }

    public function storeAs(string $fileName): self
    {
        $this->storageConfig['store_as'] = $fileName;

        return $this;
    }

    public function storePath(string $path): self
    {
        $this->storageConfig['path'] = $path;

        return $this;
    }

    public function execute(): void
    {
        $downloadUrl = $this->publicUrlToDownloadUrl($this->drivePublicUrl);

        $storePath = implode('/', [
            trim($this->storageConfig['path'], '/'),
            empty($this->storageConfig['store_as']) ? uniqid() : $this->storageConfig['store_as'],
        ]);

        $fileContent = file_get_contents($downloadUrl);

        if ($fileContent === false) {
            throw new \RuntimeException('Failed to download file');
        }

        Storage::disk($this->storageConfig['driver'])->put($storePath, $fileContent);
    }

    private function fileIdToDownloadUrl(string $fileId): string
    {
        return "https://drive.usercontent.google.com/download?id={$fileId}&export=download";
    }

    private function extractFileIdFromUrl(string $url): string
    {
        $urlPath = parse_url($url, PHP_URL_PATH);

        if (! $urlPath) {
            throw new \InvalidArgumentException('Invalid Google Drive URL');
        }

        assert(is_string($urlPath));

        $splitPath = explode('/', $urlPath);

        if (count($splitPath) < 3) {
            throw new \InvalidArgumentException('Invalid Google Drive URL Path');
        }

        $fileId = $splitPath[3];

        if (empty($fileId)) {
            throw new \InvalidArgumentException('File ID not found in URL');
        }

        return $fileId;
    }

    private function publicUrlToDownloadUrl(string $publicUrl): string
    {
        $fileId = $this->extractFileIdFromUrl($publicUrl);

        return $this->fileIdToDownloadUrl($fileId);
    }

    public static function fromPublicUrl(string $url): self
    {
        return (new GoogleDriveStorage)->usePublicUrl($url);
    }
}
