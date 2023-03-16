<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class MultipleFiles extends Uploader
{
    public static function for(array $field, $configuration)
    {
        return (new self($field, $configuration))->multiple();
    }

    public function save(Model $entry, $value = null)
    {
        return $this->isRepeatable && ! $this->isRelationship ? $this->saveRepeatableMutipleFiles($entry, $value) : $this->saveMultipleFiles($entry, $value);
    }

    private function saveMultipleFiles($entry, $value = null)
    {
        $filesToDelete = request()->get('clear_'.$this->name);

        $value = $value ?? request()->file($this->name);

        $previousFiles = $entry->getOriginal($this->name) ?? [];

        if (! is_array($previousFiles) && is_string($previousFiles)) {
            $previousFiles = json_decode($previousFiles, true);
        }

        if ($filesToDelete) {
            foreach ($previousFiles as $previousFile) {
                if (in_array($previousFile, $filesToDelete)) {
                    Storage::disk($this->disk)->delete($previousFile);

                    $previousFiles = Arr::where($previousFiles, function ($value, $key) use ($previousFile) {
                        return $value != $previousFile;
                    });
                }
            }
        }

        foreach ($value ?? [] as $file) {
            if ($file && is_file($file)) {
                $fileName = $this->getFileNameWithExtension($file);

                $file->storeAs($this->path, $fileName, $this->disk);

                $previousFiles[] = $this->path.$fileName;
            }
        }

        return isset($entry->getCasts()[$this->name]) ? $previousFiles : json_encode($previousFiles);
    }

    private function saveRepeatableMutipleFiles($entry, $files)
    {
        $previousFiles = $this->getPreviousRepeatableValues($entry);

        $fileOrder = $this->getFileOrderFromRequest();

        foreach ($files as $row => $files) {
            foreach ($files ?? [] as $file) {
                if ($file && is_file($file)) {
                    $fileName = $this->getFileNameWithExtension($file);

                    $file->storeAs($this->path, $fileName, $this->disk);
                    $fileOrder[$row][] = $this->path.$fileName;
                }
            }
        }

        foreach ($previousFiles as $previousRow => $files) {
            foreach ($files ?? [] as $key => $file) {
                $key = array_search($file, $fileOrder, true);
                if ($key === false) {
                    Storage::disk($this->disk)->delete($file);
                }
            }
        }

        return $fileOrder;
    }
}