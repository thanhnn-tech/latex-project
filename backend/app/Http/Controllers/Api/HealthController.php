<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'latex' => $this->checkLatex(),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'error',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            $disk = Storage::disk('local');
            $path = '.health-check';
            $disk->put($path, 'ok');
            $ok = $disk->get($path) === 'ok';
            $disk->delete($path);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkLatex(): bool
    {
        $process = new Process(['latexmk', '-version']);
        $process->run();

        return $process->isSuccessful();
    }
}
