<?php

namespace App\Services;

use App\Models\Project;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CompileService
{
    private const TIMEOUT_SECONDS = 45;

    public function __construct(
        private readonly ProjectFileStorage $storage,
        private readonly CompileLogParser $logParser,
    ) {}

    /**
     * @return array{status: string, log: string, durationMs: int, problems: array}
     */
    public function compile(Project $project): array
    {
        $project->update(['compile_status' => 'compiling']);

        $filesDir = $this->storage->absoluteFilesPath($project);

        $process = new Process([
            // -gg: force a fresh rebuild every run. Without it, latexmk's .fdb_latexmk
            // cache can report "Nothing to do" on the run after a halted/failed compile,
            // masking the fact that no PDF was produced.
            'latexmk', '-pdf', '-gg', '-synctex=1', '-interaction=nonstopmode', '-halt-on-error',
            '-no-shell-escape', '-jobname=main', $project->main_file,
        ], $filesDir);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        $startedAt = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
            $process->stop();
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
        $log = $process->getOutput().$process->getErrorOutput();

        if ($timedOut) {
            $log .= "\n\n[compile timed out after ".self::TIMEOUT_SECONDS.'s]';
        }

        $this->storage->writeLog($project, $log);

        $succeeded = ! $timedOut && $process->isSuccessful() && $this->storage->pdfExists($project);
        $status = $succeeded ? 'success' : 'failed';

        $problems = $this->logParser->parse($log, $project->main_file);

        $project->problems()->delete();
        foreach ($problems as $problem) {
            $project->problems()->create($problem);
        }

        $project->update([
            'compile_status' => $status,
            'compile_duration_ms' => $durationMs,
            'compiled_at' => now(),
        ]);

        return [
            'status' => $status,
            'log' => $log,
            'durationMs' => $durationMs,
            'problems' => $problems,
        ];
    }
}
