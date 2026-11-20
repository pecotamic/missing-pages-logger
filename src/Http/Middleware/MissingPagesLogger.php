<?php

namespace Pecotamic\MissingPagesLogger\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Statamic\Support\Str;
use Symfony\Component\Yaml\Yaml;

class MissingPagesLogger
{
    private const LOG_DIR = 'pecotamic/missing-pages-logger';
    private const INDEX_FILE = 'missing_pages.yaml';
    private const MISSING_PAGES_DIR = 'missing_pages';
    private const LOCK_FILE = '.lock';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (config('pecotamic.missing-pages-logger.enabled', true) && $response->getStatusCode() === 404) {
            try {
                $this->log($request);
            } catch (\Throwable $exception) {
                // Logging must not change the response to the original request.
                try {
                    report($exception);
                } catch (\Throwable) {
                    // The application's error reporter may use the same broken storage.
                }
            }
        }

        return $response;
    }

    public function log(Request $request): void
    {
        $path = $request->getPathInfo();
        $referer = $request->header('referer');
        $userAgent = $request->header('user-agent');
        $ip = $request->ip();
        $remoteAddr = $ip ?: 'unknown';
        $date = Carbon::now()->toISOString();
        $queryParams = $request->query();

        $lockPath = storage_path(self::LOG_DIR.'/'.self::LOCK_FILE);
        $this->ensureDirectoryExists($lockPath);
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new \RuntimeException(sprintf('Could not open lock file "%s"', $lockPath));
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException(sprintf('Could not lock file "%s"', $lockPath));
            }

            $indexData = $this->loadIndexData();
            $id = $this->findOrCreateIndexEntry($indexData, $path);
            $logData = $this->loadLogData($id);
            $this->addRequestToLog($logData, $date, $remoteAddr, $referer, $userAgent, $queryParams);
            $this->saveLogData($id, $logData);
            $this->saveIndexData($indexData);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function findOrCreateIndexEntry(array &$indexData, string $url): string
    {
        foreach ($indexData['missing_pages'] ?? [] as $entry) {
            if ($entry['request_uri'] === $url) {
                return $entry['id'];
            }
        }
        $id = Str::uuid()->toString();
        $newIndexEntry = [
            'request_uri' => $url,
            'id' => $id,
        ];
        if (!isset($indexData['missing_pages'])) {
            $indexData['missing_pages'] = [];
        }
        $indexData['missing_pages'][] = $newIndexEntry;
        return $id;
    }

    private function addRequestToLog(array &$logData, string $date, string $remoteAddr, ?string $referer, ?string $userAgent, array $queryParams): void
    {
        if (!isset($logData['requests'])) {
            $logData['requests'] = [];
        }
        $request = [
            'date' => $date,
            'remote_addr' => $remoteAddr,
        ];
        if ($referer) {
            $request['referer'] = $referer;
        }
        if ($userAgent) {
            $request['user_agent'] = $userAgent;
        }
        if (!empty($queryParams)) {
            $request['query_params'] = $queryParams;
        }
        $logData['requests'][] = $request;
    }

    private function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
    }

    private function loadIndexData(): array
    {
        $indexPath = storage_path(self::LOG_DIR.'/'.self::INDEX_FILE);
        $this->ensureDirectoryExists($indexPath);
        if (!file_exists($indexPath)) {
            return [
                'title' => 'Fehlende Seiten Index',
                'missing_pages' => [],
            ];
        }
        return $this->readYaml($indexPath);
    }

    private function saveIndexData(array $data): void
    {
        $indexPath = storage_path(self::LOG_DIR.'/'.self::INDEX_FILE);
        $this->writeYaml($indexPath, $data);
    }

    private function loadLogData(string $id): array
    {
        $logPath = storage_path(self::LOG_DIR.'/'.self::MISSING_PAGES_DIR.'/'.$id.'.yaml');
        $this->ensureDirectoryExists($logPath);
        if (!file_exists($logPath)) {
            return [
                'id' => $id,
                'requests' => [],
            ];
        }
        return $this->readYaml($logPath);
    }

    private function saveLogData(string $id, array $data): void
    {
        $logPath = storage_path(self::LOG_DIR.'/'.self::MISSING_PAGES_DIR.'/'.$id.'.yaml');
        $this->writeYaml($logPath, $data);
    }

    private function readYaml(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Could not read "%s"', $path));
        }

        $data = Yaml::parse($content);
        if (!is_array($data)) {
            throw new \RuntimeException(sprintf('Invalid YAML data in "%s"', $path));
        }

        return $data;
    }

    private function writeYaml(string $path, array $data): void
    {
        $this->ensureDirectoryExists($path);
        $temporaryPath = @tempnam(dirname($path), '.missing-pages-');
        if ($temporaryPath === false) {
            throw new \RuntimeException(sprintf('Could not create temporary file for "%s"', $path));
        }

        try {
            $yaml = Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            if (@file_put_contents($temporaryPath, $yaml) !== strlen($yaml)) {
                throw new \RuntimeException(sprintf('Could not write "%s"', $path));
            }
            if (!@rename($temporaryPath, $path)) {
                throw new \RuntimeException(sprintf('Could not replace "%s"', $path));
            }
        } finally {
            if (file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
