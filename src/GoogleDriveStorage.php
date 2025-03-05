<?php

declare(strict_types=1);

namespace Addeeandra\DriveToStorage;

use Illuminate\Support\Facades\Storage;

final class GoogleDriveStorage implements Contracts\StoreContract
{
    protected string $drivePublicUrl;

    protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

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

        $fileContent = $this->getFileContent($downloadUrl);

        if ($fileContent === false) {
            throw new \RuntimeException('Failed to download file');
        }

        Storage::disk($this->storageConfig['driver'])->put($storePath, $fileContent);
    }

    private function getFileContent(string $url): string|false
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ' . $this->userAgent,
            ]
        ]);

        $content = file_get_contents($url, false, $context);

        if ($content === false) {
            return false;
        }

        // Check if response is HTML (contains antivirus warning form)
        if ($this->isHtmlResponse($content)) {
            return $this->handleHtmlResponse($content, $url);
        }

        return $content;
    }

    private function isHtmlResponse(string $content): bool
    {
        return str_contains($content, '<html') &&
            str_contains($content, 'download-form') &&
            str_contains($content, 'uc-download-link');
    }

    private function handleHtmlResponse(string $htmlContent, string $baseUrl): string|false
    {
        $formParams = $this->extractFormParameters($htmlContent);

        if (empty($formParams)) {
            return false;
        }

        $fileId = $formParams['id'] ?? '';
        $export = $formParams['export'] ?? 'download';
        $confirm = $formParams['confirm'] ?? '';
        $uuid = $formParams['uuid'] ?? '';
        $at = $formParams['at'] ?? '';

        $newUrl = "https://drive.usercontent.google.com/download?id={$fileId}&export={$export}";

        if (!empty($confirm)) {
            $newUrl .= "&confirm={$confirm}";
        }

        if (!empty($uuid)) {
            $newUrl .= "&uuid={$uuid}";
        }

        if (!empty($at)) {
            $newUrl .= "&at={$at}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ' . $this->userAgent,
            ]
        ]);

        return file_get_contents($newUrl, false, $context);
    }

    private function extractFormParameters(string $htmlContent): array
    {
        $params = [];

        // Match all input elements with hidden type
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]+)">/', $htmlContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (isset($match[1]) && isset($match[2])) {
                $params[$match[1]] = $match[2];
            }
        }

        return $params;
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
