<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    /**
     * Store an array (or nested arrays) of uploaded files and return the persisted models.
     *
     * @param  array<int, UploadedFile|array|null>  $files
     * @return \Illuminate\Support\Collection<int, File>
     */
    public function storeUploadedFiles(array $files): Collection
    {
        $flattened = $this->flattenUploads($files);

        return $flattened->map(function (UploadedFile $uploadedFile) {
            $configuredDisk = config('filesystems.default', 'public');
            if (! config()->has('filesystems.disks.'.$configuredDisk)) {
                $configuredDisk = 'public';
            }
            $disk = method_exists(Storage::disk($configuredDisk), 'url') ? $configuredDisk : 'public';
            $directory = Carbon::now()->format('pg/uploads/Y/m/d');
            $path = $uploadedFile->store($directory, $disk);

            /** @var FilesystemAdapter $adapter */
            $adapter = Storage::disk($disk);
            $url = method_exists($adapter, 'url') ? $adapter->url($path) : $adapter->path($path);

            return File::create([
                'name' => $uploadedFile->getClientOriginalName(),
                'extension' => $uploadedFile->getClientOriginalExtension(),
                'disk' => $disk,
                'path' => $path,
                'url' => $url,
            ]);
        });
    }

    /**
     * @param  array<int, UploadedFile|array|null>  $files
     * @return \Illuminate\Support\Collection<int, UploadedFile>
     */
    protected function flattenUploads(array $files): Collection
    {
        $collection = collect();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $collection->push($file);

                continue;
            }

            if (is_array($file)) {
                $collection = $collection->merge($this->flattenUploads($file));
            }
        }

        return $collection;
    }
}
