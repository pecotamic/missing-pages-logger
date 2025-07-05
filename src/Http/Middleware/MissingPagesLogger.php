<?php

namespace Pecotamic\MissingPagesLogger\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Pecotamic\MissingPagesLogger\Data\Data;
use Statamic\Support\Str;
use Symfony\Component\Yaml\Yaml;

class MissingPagesLogger
{
    private const LOG_DIR = 'pecotamic/missing-pages-logger';
    private const INDEX_FILE = 'missing_pages.yaml';
    private const MISSING_PAGES_DIR = 'missing_pages';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (config('pecotamic.missing-pages-logger.enabled', true) && $response->getStatusCode() === 404) {
            $this->log($request);
        }

        return $response;
    }

    public function log(Request $request): void
    {
        $path = $request->getPathInfo();
        $referer = $request->header('referer');
        $userAgent = $request->header('user-agent');
        $ip = $request->ip();
        $remoteAddr = $ip ? gethostbyaddr($ip) : 'unknown';
        $date = Carbon::now()->toISOString();
        $queryParams = $request->query();

        $indexData = $this->loadIndexData();
        $id = $this->findOrCreateIndexEntry($indexData, $path);
        $this->saveIndexData($indexData);

        $logData = $this->loadLogData($id);
        $this->addRequestToLog($logData, $date, $remoteAddr, $referer, $userAgent, $queryParams);
        $this->saveLogData($id, $logData);
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
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
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
        $content = file_get_contents($indexPath);
        return Yaml::parse($content) ?: [
            'title' => 'Fehlende Seiten Index',
            'missing_pages' => [],
        ];
    }

    private function saveIndexData(array $data): void
    {
        $indexPath = storage_path(self::LOG_DIR.'/'.self::INDEX_FILE);
        $yaml = Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($indexPath, $yaml);
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
        $content = file_get_contents($logPath);
        return Yaml::parse($content) ?: [
            'id' => $id,
            'requests' => [],
        ];
    }

    private function saveLogData(string $id, array $data): void
    {
        $logPath = storage_path(self::LOG_DIR.'/'.self::MISSING_PAGES_DIR.'/'.$id.'.yaml');
        $yaml = Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($logPath, $yaml);
    }
}
