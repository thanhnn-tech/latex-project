<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\ProjectFileStorage;

class ProjectFilePresenter
{
    public static function meta(ProjectFile $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'language' => $file->language,
            'kind' => $file->is_directory ? 'directory' : ProjectFile::detectKind($file->name),
            'isDirectory' => (bool) $file->is_directory,
            'mimeType' => $file->mime_type,
            'size' => $file->size,
            'createdAt' => Time::toEpochMs($file->created_at),
            'updatedAt' => Time::toEpochMs($file->updated_at),
        ];
    }

    public static function detail(ProjectFile $file, ProjectFileStorage $storage, Project $project): array
    {
        $data = self::meta($file);

        $data['content'] = ($file->is_directory || ProjectFile::isBinaryKind($data['kind']))
            ? null
            : $storage->read($project, $file->name);

        return $data;
    }
}
