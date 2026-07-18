<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'language',
        'size',
        'is_directory',
        'mime_type',
    ];

    protected $casts = [
        'is_directory' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectFileVersion::class);
    }

    public static function detectLanguage(string $name): string
    {
        $lower = strtolower($name);

        if (str_ends_with($lower, '.tex')) {
            return 'latex';
        }

        if (str_ends_with($lower, '.md') || str_ends_with($lower, '.markdown')) {
            return 'markdown';
        }

        return 'plaintext';
    }

    /**
     * Asset-manager classification, distinct from `language` (which only
     * drives Monaco syntax highlighting).
     */
    public static function detectKind(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match (true) {
            in_array($extension, ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'], true) => 'image',
            $extension === 'pdf' => 'pdf',
            $extension === 'bib' => 'bibliography',
            in_array($extension, ['tex', 'sty', 'cls', 'bst'], true) => 'latex',
            in_array($extension, ['csv', 'json', 'xml', 'txt'], true) => 'data',
            $extension === 'zip' => 'archive',
            default => 'text',
        };
    }

    public static function isBinaryKind(string $kind): bool
    {
        return in_array($kind, ['image', 'pdf', 'archive'], true);
    }

    public static function mimeTypeFor(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            default => 'text/plain',
        };
    }
}
