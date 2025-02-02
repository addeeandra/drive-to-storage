<div align="center">
    <h3 align="center">Google Drive to Storage</h1>
    <p align="center">Store File From Google Drive Public URL into Laravel Storage</p>
</div>

## How to use

```php

// e.g. GOOGLE_DRIVE_SHARED_LINK=https://drive.google.com/file/d/123098123098123098/view?usp=share_link
$drive = \Addeeandra\DriveToStorage\GoogleDriveStorage::fromPublicUrl(GOOGLE_DRIVE_SHARED_LINK)
    ->useStorage('local') // Storage Driver e.g. local, s3, private, public
    ->storePath('documents')
    ->storeAs('my-document.pdf');

```

## License

MIT License.
