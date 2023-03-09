<?php

namespace Backpack\MediaLibraryUploads\Uploaders;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;

class RepeatableRelationship extends RepeatableUploader
{
    public function __construct(array $field) 
    {
        parent::__construct($field);
        $this->isRelationship = true;
    }

    public function save(Model $entry, $value = null)
    {
        $values = collect(request()->get($this->fieldName));
        $files = collect(request()->file($this->fieldName));

        $value = $this->mergeValuesRecursive($values, $files);

        $modelCount = CRUD::get('model_count_'.$this->fieldName);

        $value = collect($values)->slice($modelCount, 1);

        foreach ($this->repeatableUploads as $upload) {
            $this->performSave($entry, $upload, $value, $modelCount);
        }
        
        return $entry;
    }

    protected function performSave($entry, $upload, $value, $row = null)
    {
        if (isset($value[$row][$upload->fieldName])) {
            $entry->{$upload->fieldName} = $upload->save($entry, $value[$row][$upload->fieldName]);
        }
    }
}
