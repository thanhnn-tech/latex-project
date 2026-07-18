<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\SyncTexService;
use Illuminate\Http\Request;

class SyncTexController extends Controller
{
    public function __construct(
        private readonly SyncTexService $syncTex,
    ) {}

    public function forward(Request $request, Project $project)
    {
        $data = $request->validate([
            'file' => ['required', 'string'],
            'line' => ['required', 'integer', 'min:1'],
            'column' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->syncTex->forward($project, $data['file'], $data['line'], $data['column'] ?? 1);

        return $result === null
            ? response()->json(['message' => 'No SyncTeX data available.'], 404)
            : response()->json($result);
    }

    public function reverse(Request $request, Project $project)
    {
        $data = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
        ]);

        $result = $this->syncTex->reverse($project, $data['page'], $data['x'], $data['y']);

        return $result === null
            ? response()->json(['message' => 'No SyncTeX data available.'], 404)
            : response()->json($result);
    }
}
