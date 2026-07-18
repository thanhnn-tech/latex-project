<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFileVersion;

class FileVersionService
{
    /**
     * Autosave fires on nearly every keystroke, so a version is only checkpointed
     * once this many minutes have passed since the last one (and the content
     * actually changed) — otherwise the history would be one row per keystroke.
     */
    private const CHECKPOINT_INTERVAL_MINUTES = 5;

    public function __construct(
        private readonly ProjectFileStorage $storage,
    ) {}

    /**
     * Snapshots the file's current (pre-write) content as a checkpoint, if enough
     * time has passed since the last one and the content actually changed. Call
     * this BEFORE overwriting the file with new content.
     */
    public function checkpointBeforeWrite(Project $project, ProjectFile $file): void
    {
        $lastVersion = $file->versions()->latest('id')->first();

        if ($lastVersion !== null && $lastVersion->created_at->gt(now()->subMinutes(self::CHECKPOINT_INTERVAL_MINUTES))) {
            return;
        }

        $this->createIfChanged($project, $file, $lastVersion);
    }

    /**
     * Snapshots the file's current content regardless of the throttle window —
     * used right before a restore so the pre-restore state is never lost.
     */
    public function forceCheckpoint(Project $project, ProjectFile $file): void
    {
        $this->createIfChanged($project, $file, $file->versions()->latest('id')->first());
    }

    private function createIfChanged(Project $project, ProjectFile $file, ?ProjectFileVersion $lastVersion): void
    {
        $currentContent = $this->storage->read($project, $file->name);

        if ($lastVersion === null && $currentContent === '') {
            return;
        }

        if ($lastVersion !== null && $lastVersion->content === $currentContent) {
            return;
        }

        $file->versions()->create([
            'content' => $currentContent,
            'size' => strlen($currentContent),
        ]);
    }
}
