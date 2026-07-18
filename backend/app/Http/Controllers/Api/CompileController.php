<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\CompileService;
use App\Services\ProjectFileStorage;

class CompileController extends Controller
{
    public function __construct(
        private readonly CompileService $compileService,
        private readonly ProjectFileStorage $storage,
    ) {}

    public function compile(Project $project)
    {
        $result = $this->compileService->compile($project);

        return response()->json($result);
    }

    public function pdf(Project $project)
    {
        abort_unless($this->storage->pdfExists($project), 404);

        return response()->file($this->storage->absolutePdfPath($project), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function logs(Project $project)
    {
        return response()->json(['log' => $this->storage->readLog($project)]);
    }

    public function problems(Project $project)
    {
        return response()->json(
            $project->problems()->orderBy('id')->get(['file', 'line', 'column', 'severity', 'message'])
        );
    }
}
