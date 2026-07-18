<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\FileVersionService;
use App\Services\ProjectFileStorage;
use App\Support\PathValidator;
use App\Support\ProjectFilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProjectFileController extends Controller
{
    public function __construct(
        private readonly ProjectFileStorage $storage,
        private readonly FileVersionService $versions,
    ) {}

    public function index(Project $project)
    {
        return response()->json($project->files->map(fn (ProjectFile $file) => ProjectFilePresenter::meta($file)));
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:1000', $this->pathRule(),
                Rule::unique('project_files', 'name')->where('project_id', $project->id),
            ],
            'content' => ['nullable', 'string'],
        ]);

        $content = $data['content'] ?? '';
        $this->storage->write($project, $data['name'], $content);

        $file = ProjectFile::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'language' => ProjectFile::detectLanguage($data['name']),
            'size' => strlen($content),
            'is_directory' => false,
            'mime_type' => ProjectFile::mimeTypeFor($data['name']),
        ]);

        $project->touch();

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project), Response::HTTP_CREATED);
    }

    public function upload(Request $request, Project $project)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'path' => ['nullable', 'string', 'max:1000', $this->pathRule()],
        ]);

        $uploaded = $data['file'];
        $name = $data['path'] ?? $uploaded->getClientOriginalName();

        if (! PathValidator::isValid($name)) {
            abort(422, 'Invalid file name.');
        }

        if ($project->files()->where('name', $name)->exists()) {
            abort(409, 'A file with that name already exists.');
        }

        $size = $this->storage->writeUploadedFile($project, $name, $uploaded);

        $file = ProjectFile::create([
            'project_id' => $project->id,
            'name' => $name,
            'language' => ProjectFile::detectLanguage($name),
            'size' => $size,
            'is_directory' => false,
            'mime_type' => ProjectFile::mimeTypeFor($name),
        ]);

        $project->touch();

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project), Response::HTTP_CREATED);
    }

    public function storeFolder(Request $request, Project $project)
    {
        $data = $request->validate([
            'path' => [
                'required', 'string', 'max:1000', $this->pathRule(),
                Rule::unique('project_files', 'name')->where('project_id', $project->id),
            ],
        ]);

        $this->storage->makeDirectory($project, $data['path']);

        $folder = ProjectFile::create([
            'project_id' => $project->id,
            'name' => $data['path'],
            'language' => 'plaintext',
            'size' => 0,
            'is_directory' => true,
            'mime_type' => null,
        ]);

        $project->touch();

        return response()->json(ProjectFilePresenter::meta($folder), Response::HTTP_CREATED);
    }

    public function show(Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project));
    }

    public function raw(Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);
        abort_if($file->is_directory, 404);

        return response($this->storage->read($project, $file->name), 200, [
            'Content-Type' => $file->mime_type ?? ProjectFile::mimeTypeFor($file->name),
        ]);
    }

    public function update(Request $request, Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        $data = $request->validate([
            // Laravel's ConvertEmptyStringsToNull middleware turns "" into null
            // before validation runs, and `required` also treats "" as absent —
            // both would break autosaving a file down to empty, so: nullable +
            // string, then coalesce null back to "" below.
            'content' => ['present', 'nullable', 'string'],
        ]);
        $content = $data['content'] ?? '';

        $this->versions->checkpointBeforeWrite($project, $file);
        $this->storage->write($project, $file->name, $content);
        $file->update(['size' => strlen($content)]);
        $project->touch();

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project));
    }

    public function move(Request $request, Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        $data = $request->validate([
            'path' => [
                'required', 'string', 'max:1000', $this->pathRule(),
                Rule::unique('project_files', 'name')->where('project_id', $project->id)->ignore($file->id),
            ],
        ]);

        $oldPath = $file->name;
        $newPath = $data['path'];

        $this->storage->rename($project, $oldPath, $newPath);

        if ($file->is_directory) {
            foreach ($project->files()->where('name', 'like', $oldPath.'/%')->get() as $descendant) {
                $descendant->update([
                    'name' => $newPath.substr($descendant->name, strlen($oldPath)),
                ]);
            }
        }

        $file->update([
            'name' => $newPath,
            'language' => ProjectFile::detectLanguage($newPath),
            'mime_type' => $file->is_directory ? null : ProjectFile::mimeTypeFor($newPath),
        ]);
        $project->touch();

        return response()->json(ProjectFilePresenter::detail($file, $this->storage, $project));
    }

    public function duplicate(Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        $newName = $this->generateDuplicateName($project, $file->name);

        if ($file->is_directory) {
            $this->storage->copy($project, $file->name, $newName);
            foreach ($project->files()->where('name', 'like', $file->name.'/%')->get() as $descendant) {
                ProjectFile::create([
                    'project_id' => $project->id,
                    'name' => $newName.substr($descendant->name, strlen($file->name)),
                    'language' => $descendant->language,
                    'size' => $descendant->size,
                    'is_directory' => $descendant->is_directory,
                    'mime_type' => $descendant->mime_type,
                ]);
            }
        } else {
            $this->storage->copy($project, $file->name, $newName);
        }

        $newFile = ProjectFile::create([
            'project_id' => $project->id,
            'name' => $newName,
            'language' => $file->language,
            'size' => $file->size,
            'is_directory' => $file->is_directory,
            'mime_type' => $file->mime_type,
        ]);

        $project->touch();

        return response()->json(ProjectFilePresenter::detail($newFile, $this->storage, $project), Response::HTTP_CREATED);
    }

    public function destroy(Project $project, ProjectFile $file)
    {
        $this->assertBelongsToProject($project, $file);

        if ($file->is_directory) {
            $project->files()->where('name', 'like', $file->name.'/%')->delete();
            $this->storage->deleteDirectory($project, $file->name);
        } else {
            $this->storage->delete($project, $file->name);
        }

        $file->delete();
        $project->touch();

        return response()->noContent();
    }

    private function pathRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if (! is_string($value) || ! PathValidator::isValid($value)) {
                $fail('The '.$attribute.' is not a valid path.');
            }
        };
    }

    private function generateDuplicateName(Project $project, string $name): string
    {
        $directory = pathinfo($name, PATHINFO_DIRNAME);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $prefix = $directory === '.' ? '' : $directory.'/';
        $suffix = $extension ? '.'.$extension : '';

        $candidate = $prefix.$base.' copy'.$suffix;
        $counter = 2;

        while ($project->files()->where('name', $candidate)->exists()) {
            $candidate = $prefix.$base.' copy '.$counter.$suffix;
            $counter++;
        }

        return $candidate;
    }

    private function assertBelongsToProject(Project $project, ProjectFile $file): void
    {
        abort_unless($file->project_id === $project->id, 404);
    }
}
