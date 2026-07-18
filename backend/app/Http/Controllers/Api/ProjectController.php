<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\ProjectFileStorage;
use App\Support\PathValidator;
use App\Support\ProjectFilePresenter;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ZipArchive;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectFileStorage $storage) {}

    public function index()
    {
        $projects = Project::withCount('files')->orderByDesc('updated_at')->get();

        return response()->json($projects->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'createdAt' => Time::toEpochMs($project->created_at),
            'updatedAt' => Time::toEpochMs($project->updated_at),
            'fileCount' => $project->files_count,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mainFile' => ['nullable', 'string', 'max:255'],
            'files' => ['nullable', 'array'],
            'files.*.name' => [
                'required_with:files', 'string', 'max:1000',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! is_string($value) || ! PathValidator::isValid($value)) {
                        $fail('The '.$attribute.' is not a valid path.');
                    }
                },
            ],
            'files.*.content' => ['nullable', 'string'],
        ]);

        $project = Project::create([
            'name' => $data['name'],
            'main_file' => $data['mainFile'] ?? 'main.tex',
        ]);

        $this->storage->ensureFilesDirectory($project);

        $files = $data['files'] ?? [['name' => 'main.tex', 'content' => '']];

        foreach ($files as $file) {
            $content = $file['content'] ?? '';
            $this->storage->write($project, $file['name'], $content);
            ProjectFile::create([
                'project_id' => $project->id,
                'name' => $file['name'],
                'language' => ProjectFile::detectLanguage($file['name']),
                'size' => strlen($content),
                'is_directory' => false,
                'mime_type' => ProjectFile::mimeTypeFor($file['name']),
            ]);
        }

        return response()->json($this->showPayload($project->fresh()), Response::HTTP_CREATED);
    }

    public function show(Project $project)
    {
        return response()->json($this->showPayload($project));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project->update(['name' => $data['name']]);

        return response()->json($this->showPayload($project));
    }

    public function destroy(Project $project)
    {
        $this->storage->deleteProjectDirectory($project);
        $project->delete();

        return response()->noContent();
    }

    public function importZip(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $uploaded = $data['file'];
        $projectName = $data['name'] ?? pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME);

        $project = Project::create([
            'name' => $projectName ?: 'Imported Project',
            'main_file' => 'main.tex',
        ]);

        $this->storage->ensureFilesDirectory($project);

        $zip = new ZipArchive;
        $zip->open($uploaded->getRealPath());

        abort_if($zip->numFiles > 2000, 422, 'Archive contains too many entries.');

        $mainFileCandidate = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $isDirectory = str_ends_with($entryName, '/');
            $relativePath = rtrim($entryName, '/');

            if ($relativePath === '' || ! PathValidator::isValid($relativePath)) {
                continue;
            }

            if ($isDirectory) {
                $this->storage->makeDirectory($project, $relativePath);
                ProjectFile::create([
                    'project_id' => $project->id,
                    'name' => $relativePath,
                    'language' => 'plaintext',
                    'size' => 0,
                    'is_directory' => true,
                    'mime_type' => null,
                ]);

                continue;
            }

            $content = $zip->getFromIndex($i) ?: '';
            $this->storage->write($project, $relativePath, $content);

            ProjectFile::create([
                'project_id' => $project->id,
                'name' => $relativePath,
                'language' => ProjectFile::detectLanguage($relativePath),
                'size' => strlen($content),
                'is_directory' => false,
                'mime_type' => ProjectFile::mimeTypeFor($relativePath),
            ]);

            if (basename($relativePath) === 'main.tex') {
                $mainFileCandidate = $relativePath;
            }
        }

        $zip->close();

        if ($mainFileCandidate) {
            $project->update(['main_file' => $mainFileCandidate]);
        }

        return response()->json($this->showPayload($project->fresh()), Response::HTTP_CREATED);
    }

    public function download(Project $project)
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'project-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($project->files as $file) {
            if ($file->is_directory) {
                $zip->addEmptyDir($file->name);
            } else {
                $zip->addFromString($file->name, $this->storage->read($project, $file->name));
            }
        }

        if ($this->storage->pdfExists($project)) {
            $zip->addFile($this->storage->absolutePdfPath($project), 'main.pdf');
        }

        $zip->close();

        return response()->download($zipPath, $project->name.'.zip')->deleteFileAfterSend();
    }

    private function showPayload(Project $project): array
    {
        $project->loadMissing('files');

        return [
            'id' => $project->id,
            'name' => $project->name,
            'mainFile' => $project->main_file,
            'createdAt' => Time::toEpochMs($project->created_at),
            'updatedAt' => Time::toEpochMs($project->updated_at),
            'compileStatus' => $project->compile_status,
            'compiledAt' => Time::toEpochMs($project->compiled_at),
            'compileDurationMs' => $project->compile_duration_ms,
            'files' => $project->files
                ->map(fn (ProjectFile $file) => ProjectFilePresenter::detail($file, $this->storage, $project))
                ->values(),
        ];
    }
}
