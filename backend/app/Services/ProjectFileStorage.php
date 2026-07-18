<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProjectFileStorage
{
    public function filesDirectory(Project $project): string
    {
        return "projects/{$project->id}/files";
    }

    public function filePath(Project $project, string $name): string
    {
        return $this->filesDirectory($project).'/'.$name;
    }

    public function read(Project $project, string $name): string
    {
        return Storage::disk('local')->get($this->filePath($project, $name)) ?? '';
    }

    public function write(Project $project, string $name, string $content): int
    {
        Storage::disk('local')->put($this->filePath($project, $name), $content);

        return strlen($content);
    }

    public function writeUploadedFile(Project $project, string $name, UploadedFile $file): int
    {
        Storage::disk('local')->put($this->filePath($project, $name), file_get_contents($file->getRealPath()));

        return $file->getSize();
    }

    public function copy(Project $project, string $fromName, string $toName): void
    {
        Storage::disk('local')->copy($this->filePath($project, $fromName), $this->filePath($project, $toName));
    }

    public function makeDirectory(Project $project, string $name): void
    {
        Storage::disk('local')->makeDirectory($this->filePath($project, $name));
    }

    public function absoluteRawPath(Project $project, string $name): string
    {
        return Storage::disk('local')->path($this->filePath($project, $name));
    }

    public function delete(Project $project, string $name): void
    {
        Storage::disk('local')->delete($this->filePath($project, $name));
    }

    public function deleteDirectory(Project $project, string $name): void
    {
        Storage::disk('local')->deleteDirectory($this->filePath($project, $name));
    }

    public function rename(Project $project, string $oldName, string $newName): void
    {
        Storage::disk('local')->move($this->filePath($project, $oldName), $this->filePath($project, $newName));
    }

    public function deleteProjectDirectory(Project $project): void
    {
        Storage::disk('local')->deleteDirectory("projects/{$project->id}");
    }

    public function ensureFilesDirectory(Project $project): void
    {
        Storage::disk('local')->makeDirectory($this->filesDirectory($project));
    }

    public function absoluteFilesPath(Project $project): string
    {
        return Storage::disk('local')->path($this->filesDirectory($project));
    }

    public function pdfPath(Project $project): string
    {
        // latexmk runs with the files directory mounted as its working directory,
        // so the produced PDF lands next to the source files, not the project root.
        return $this->filesDirectory($project).'/main.pdf';
    }

    public function absolutePdfPath(Project $project): string
    {
        return Storage::disk('local')->path($this->pdfPath($project));
    }

    public function pdfExists(Project $project): bool
    {
        return Storage::disk('local')->exists($this->pdfPath($project));
    }

    public function synctexPath(Project $project): string
    {
        return $this->filesDirectory($project).'/main.synctex.gz';
    }

    public function synctexExists(Project $project): bool
    {
        return Storage::disk('local')->exists($this->synctexPath($project));
    }

    public function logPath(Project $project): string
    {
        return "projects/{$project->id}/compile.log";
    }

    public function writeLog(Project $project, string $log): void
    {
        Storage::disk('local')->put($this->logPath($project), $log);
    }

    public function readLog(Project $project): string
    {
        return Storage::disk('local')->get($this->logPath($project)) ?? '';
    }
}
