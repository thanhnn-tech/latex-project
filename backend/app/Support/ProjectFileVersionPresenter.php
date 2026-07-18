<?php

namespace App\Support;

use App\Models\ProjectFileVersion;

class ProjectFileVersionPresenter
{
    public static function meta(ProjectFileVersion $version): array
    {
        return [
            'id' => $version->id,
            'size' => $version->size,
            'createdAt' => Time::toEpochMs($version->created_at),
        ];
    }

    public static function detail(ProjectFileVersion $version): array
    {
        return [
            ...self::meta($version),
            'content' => $version->content,
        ];
    }
}
