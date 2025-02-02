<?php

declare(strict_types=1);

namespace Addeeandra\DriveToStorage\Contracts;

interface StoreContract
{
    public function useStorage(string $driver, string $storePath = '', string $storeAs = ''): self;

    public function usePublicUrl(string $url): self;

    public function storePath(string $path): self;

    public function storeAs(string $fileName): self;

    /**
     * Download from
     * https://drive.usercontent.google.com/download?id={FILE_ID]&export=download
     */
    public function execute(): void;

    /**
     * Example url
     * https://drive.google.com/file/d/{FILE_ID}/view?usp=share_link
     */
    public static function fromPublicUrl(string $url): StoreContract;
}
