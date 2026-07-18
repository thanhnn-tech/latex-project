<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CompileService
{
    private const IMAGE = 'texlive/texlive:latest';

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

        $containerName = 'compile-'.Str::uuid();
        $filesDir = $this->storage->absoluteFilesPath($project);

        $process = new Process([
            'docker', 'run', '--rm',
            '--name', $containerName,
            '--network', 'none',
            '--cpus', '1',
            '--memory', '512m',
            '--memory-swap', '512m',
            '--pids-limit', '128',
            '--read-only',
            '--tmpfs', '/tmp',
            '-v', "{$filesDir}:/data",
            '-w', '/data',
            env('LATEX_COMPILER_IMAGE', 'texlive/texlive:latest'),
            // -gg: force a fresh rebuild every run. Without it, latexmk's .fdb_latexmk
            // cache can report "Nothing to do" on the run after a halted/failed compile,
            // masking the fact that no PDF was produced.
            'latexmk', '-pdf', '-gg', '-synctex=1', '-interaction=nonstopmode', '-halt-on-error',
            '-no-shell-escape', '-jobname=main', $project->main_file,
        ]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        $startedAt = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } finally {
            (new Process(['docker', 'rm', '-f', $containerName]))->run();
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
