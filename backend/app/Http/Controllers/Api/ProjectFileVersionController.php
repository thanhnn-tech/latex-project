<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFileVersion;
use App\Services\FileVersionService;
use App\Services\ProjectFileStorage;
use App\Support\ProjectFilePresenter;
use App\Support\ProjectFileVersionPresenter;

class ProjectFileVersionController extends Controller
{
    public function __construct(
        private readonly FileVersionService $versions,
        private readonly ProjectFileStorage $storage,
    ) {}

    public function index(Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        $versions = $file->versions()->orderByDesc('id')->get();

        return response()->json($versions->map(fn (ProjectFileVersion $version) => ProjectFileVersionPresenter::meta($version)));
    }

    public function show(Project $project, ProjectFile $file, ProjectFileVersion $version)
    {
        $this->assertBelongsToProject($project, $file);
        $this->assertBelongsToFile($file, $version);

        return response()->json(ProjectFileVersionPresenter::detail($version));
    }

    public function restore(Project $project, ProjectFile $file, ProjectFileVersion $version)
    {
        $this->assertBelongsToProject($project, $file);
        $this->assertBelongsToFile($file, $version);

        $this->versions->forceCheckpoint($project, $file);

        $this->storage->write($project, $file->name, $version->content);
        $file->update(['size' => $version->size]);
        $project->touch();

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project));
    }

    private function assertBelongsToProject(Project $project, ProjectFile $file): void
    {
        abort_unless($file->project_id === $project->id, 404);
    }

    private function assertBelongsToFile(ProjectFile $file, ProjectFileVersion $version): void
    {
        abort_unless($version->project_file_id === $file->id, 404);
    }
}
