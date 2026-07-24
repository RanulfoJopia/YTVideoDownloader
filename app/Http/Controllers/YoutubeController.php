<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ExceptionInterface;

class YoutubeController extends Controller
{
    private string $ytDlpPath = 'C:\\tools\\yt-dlp.exe';
    private string $ffmpegDir = 'C:\\tools';
    private string $tempDir  = 'C:\\tools\\temp';

    private function processEnv(): array
{
    $env = getenv(); // grab the FULL current environment (SystemRoot, windir, PATH, etc.)
    $env['TEMP'] = $this->tempDir;
    $env['TMP']  = $this->tempDir;
    return $env;
}

    public function index()
    {
        return view('downloader');
    }

    public function scan(Request $request)
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $request->input('url');

        if (!file_exists($this->ytDlpPath)) {
            return response()->json([
                'error' => 'yt-dlp.exe not found at ' . $this->ytDlpPath,
            ], 500);
        }

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        try {
            $process = new Process(
                [
                    $this->ytDlpPath,
                    '--dump-json',
                    '--no-playlist',
                    $url,
                ],
                null,
                $this->processEnv()
            );
            $process->setTimeout(60);
            $process->run();

            Log::info('yt-dlp SCAN attempt', [
                'exit_code' => $process->getExitCode(),
                'error'     => $process->getErrorOutput(),
            ]);

            if (!$process->isSuccessful()) {
                return response()->json([
                    'error' => 'Scan failed: ' . $process->getErrorOutput(),
                ], 422);
            }

            $data = json_decode($process->getOutput(), true);

            $resolutions = collect($data['formats'] ?? [])
                ->filter(fn ($f) => !empty($f['height']) && ($f['vcodec'] ?? 'none') !== 'none')
                ->groupBy('height')
                ->keys()
                ->sortDesc()
                ->values();

            return response()->json([
                'title'       => $data['title'] ?? '',
                'description' => $data['description'] ?? '',
                'thumbnail'   => $data['thumbnail'] ?? '',
                'duration'    => $data['duration_string'] ?? '',
                'resolutions' => $resolutions,
                'url'         => $url,
            ]);

        } catch (ExceptionInterface $e) {
            Log::error('yt-dlp SCAN exception', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Exception during scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $request->validate([
            'url'        => ['required', 'url'],
            'resolution' => ['required', 'integer'],
        ]);

        $url = $request->input('url');
        $height = $request->input('resolution');

        if (!file_exists($this->ytDlpPath)) {
            return response()->json([
                'error' => 'yt-dlp.exe not found at ' . $this->ytDlpPath,
            ], 500);
        }

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        $downloadsDir = storage_path('app/downloads');
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }

        $filename = 'yt_' . uniqid() . '.mp4';
        $outputPath = $downloadsDir . DIRECTORY_SEPARATOR . $filename;

        $format = "bestvideo[height<={$height}]+bestaudio/best[height<={$height}]";

        try {
            $process = new Process(
                [
                    $this->ytDlpPath,
                    '-f', $format,
                    '--merge-output-format', 'mp4',
                    '--ffmpeg-location', $this->ffmpegDir,
                    '-o', $outputPath,
                    $url,
                ],
                null,
                $this->processEnv()
            );
            $process->setTimeout(600);
            $process->run();

            Log::info('yt-dlp DOWNLOAD attempt', [
                'command'     => $process->getCommandLine(),
                'exit_code'   => $process->getExitCode(),
                'output'      => $process->getOutput(),
                'error'       => $process->getErrorOutput(),
                'file_exists' => file_exists($outputPath),
            ]);

            if (!$process->isSuccessful() || !file_exists($outputPath)) {
                return response()->json([
                    'error' => 'Download failed: ' . $process->getErrorOutput(),
                ], 500);
            }

            return response()->download($outputPath)->deleteFileAfterSend(true);

        } catch (ExceptionInterface $e) {
            Log::error('yt-dlp DOWNLOAD exception', ['message' => $e->getMessage()]);
            return response()->json([
                'error' => 'Exception during download: ' . $e->getMessage(),
            ], 500);
        }
    }
}