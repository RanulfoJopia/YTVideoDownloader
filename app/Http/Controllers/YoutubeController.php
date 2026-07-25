<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ExceptionInterface;

class YoutubeController extends Controller
{
    private string $ytDlpPath;
    private string $ffmpegDir;
    private string $tempDir;
    private string $cookiesPath = '/etc/secrets/cookies.txt';

    public function __construct()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Local Windows development
            $this->ytDlpPath = 'C:\\tools\\yt-dlp.exe';
            $this->ffmpegDir = 'C:\\tools';
            $this->tempDir   = 'C:\\tools\\temp';
        } else {
            // Render / Linux production
            $this->ytDlpPath = 'yt-dlp';
            $this->ffmpegDir = '/usr/bin';
            $this->tempDir   = '/tmp/ytdlp';
        }
    }

    private function processEnv(): array
    {
        $env = getenv();
        $env['TEMP'] = $this->tempDir;
        $env['TMP']  = $this->tempDir;
        return $env;
    }

    private function ytDlpAvailable(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return file_exists($this->ytDlpPath);
        }

        $process = new Process(['which', $this->ytDlpPath]);
        $process->run();
        return $process->isSuccessful();
    }

    // Copies the read-only secret cookies file into a writable temp location,
    // since yt-dlp tries to update the cookie jar after each run and will
    // crash if the file is read-only (as Render's Secret Files are).
    private function getWritableCookiesPath(): ?string
    {
        if (!file_exists($this->cookiesPath)) {
            return null;
        }

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        $writablePath = $this->tempDir . DIRECTORY_SEPARATOR . 'cookies.txt';
        copy($this->cookiesPath, $writablePath);

        return $writablePath;
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

        if (!$this->ytDlpAvailable()) {
            return response()->json([
                'error' => 'yt-dlp not found at ' . $this->ytDlpPath,
            ], 500);
        }

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        Log::info('Cookies check', [
            'path'   => $this->cookiesPath,
            'exists' => file_exists($this->cookiesPath),
        ]);

        try {
            $args = [
                $this->ytDlpPath,
                '--dump-json',
                '--no-playlist',
                '--remote-components', 'ejs:github',
            ];

            $writableCookies = $this->getWritableCookiesPath();
            if ($writableCookies) {
                $args[] = '--cookies';
                $args[] = $writableCookies;
            }

            $args[] = $url;

            $process = new Process($args, null, $this->processEnv());
            $process->setTimeout(60);
            $process->run();

            Log::info('yt-dlp SCAN attempt', [
                'command'   => $process->getCommandLine(),
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

        if (!$this->ytDlpAvailable()) {
            return response()->json([
                'error' => 'yt-dlp not found at ' . $this->ytDlpPath,
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
            $args = [
                $this->ytDlpPath,
                '-f', $format,
                '--merge-output-format', 'mp4',
                '--ffmpeg-location', $this->ffmpegDir,
                '--remote-components', 'ejs:github',
            ];

            $writableCookies = $this->getWritableCookiesPath();
            if ($writableCookies) {
                $args[] = '--cookies';
                $args[] = $writableCookies;
            }

            $args[] = '-o';
            $args[] = $outputPath;
            $args[] = $url;

            $process = new Process($args, null, $this->processEnv());
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