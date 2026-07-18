<?php

namespace App\Services;

class CompileLogParser
{
    /**
     * @return array<int, array{file: string, line: int|null, column: int|null, severity: string, message: string}>
     */
    public function parse(string $log, string $mainFile): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $log) ?: [];
        $problems = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^! (.+)$/', $line, $matches) === 1) {
                $problems[] = [
                    'file' => $mainFile,
                    'line' => $this->findFollowingLineNumber($lines, $index),
                    'column' => null,
                    'severity' => 'error',
                    'message' => trim($matches[1]),
                ];

                continue;
            }

            if (preg_match('/LaTeX Warning: (.+)$/', $line, $matches) === 1) {
                $message = rtrim(trim($matches[1]), '.');
                $lineNumber = null;

                if (preg_match('/on input line (\d+)/', $message, $lineMatches) === 1) {
                    $lineNumber = (int) $lineMatches[1];
                }

                $problems[] = [
                    'file' => $mainFile,
                    'line' => $lineNumber,
                    'column' => null,
                    'severity' => 'warning',
                    'message' => $message,
                ];
            }
        }

        return $problems;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findFollowingLineNumber(array $lines, int $fromIndex): ?int
    {
        $window = array_slice($lines, $fromIndex, 6);

        foreach ($window as $line) {
            if (preg_match('/^l\.(\d+)/', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
