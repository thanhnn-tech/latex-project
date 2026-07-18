<?php

namespace App\Services;

use App\Models\Project;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class SyncTexService
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly ProjectFileStorage $storage,
    ) {}

    /**
     * Source location -> PDF coordinates, for jumping from the editor to the preview.
     *
     * @return array{page: int, x: float, y: float, width: float, height: float}|null
     */
    public function forward(Project $project, string $file, int $line, int $column = 1): ?array
    {
        if (! $this->storage->synctexExists($project)) {
            return null;
        }

        $output = $this->run($project, ['synctex', 'view', '-i', "{$line}:{$column}:{$file}", '-o', 'main.pdf']);
        $fields = $this->parseResult($output);

        if (! isset($fields['Page'], $fields['x'], $fields['y'])) {
            return null;
        }

        return [
            'page' => (int) $fields['Page'],
            'x' => (float) $fields['x'],
            'y' => (float) $fields['y'],
            'width' => (float) ($fields['W'] ?? 0),
            'height' => (float) ($fields['H'] ?? 0),
        ];
    }

    /**
     * PDF coordinates -> source location, for jumping from a PDF click to the editor.
     *
     * @return array{file: string, line: int, column: int}|null
     */
    public function reverse(Project $project, int $page, float $x, float $y): ?array
    {
        if (! $this->storage->synctexExists($project)) {
            return null;
        }

        $output = $this->run($project, ['synctex', 'edit', '-o', "{$page}:{$x}:{$y}:main.pdf"]);
        $fields = $this->parseResult($output);

        if (! isset($fields['Input'], $fields['Line'])) {
            return null;
        }

        $filesDir = $this->storage->absoluteFilesPath($project);
        $file = ltrim(str_replace($filesDir.'/', '', $fields['Input']), './');

        return [
            'file' => $file,
            'line' => (int) $fields['Line'],
            'column' => max(1, (int) ($fields['Column'] ?? 1)),
        ];
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(Project $project, array $command): string
    {
        $filesDir = $this->storage->absoluteFilesPath($project);

        $process = new Process($command, $filesDir);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $process->stop();
        }

        return $process->getOutput();
    }

    /**
     * Parses the "Key:value" lines synctex prints between its result markers.
     * Keys are case-sensitive on purpose: "h"/"v" (baseline offset) and "W"/"H"
     * (box width/height) are distinct fields that only differ by case.
     *
     * @return array<string, string>
     */
    private function parseResult(string $output): array
    {
        $fields = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^([A-Za-z]+):(.*)$/', trim($line), $matches) === 1) {
                $fields[$matches[1]] = trim($matches[2]);
            }
        }

        return $fields;
    }
}
