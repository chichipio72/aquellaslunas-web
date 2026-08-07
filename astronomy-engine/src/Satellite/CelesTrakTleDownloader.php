<?php

declare(strict_types=1);

namespace AstronomyEngine\Satellite;

use RuntimeException;

final readonly class CelesTrakTleDownloader implements TleDownloader
{
    private const ENDPOINT = 'https://celestrak.org/NORAD/elements/gp.php';

    public function __construct(private int $timeoutSeconds = 8)
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new RuntimeException('CelesTrak timeout must be between 1 and 30 seconds.');
        }
    }

    public function download(int $catalogNumber): string
    {
        if ($catalogNumber < 1 || $catalogNumber > 99999) {
            throw new RuntimeException('Invalid NORAD catalog number for TLE download.');
        }
        $url = self::ENDPOINT . '?' . http_build_query(['CATNR' => $catalogNumber, 'FORMAT' => 'TLE']);
        if (function_exists('curl_init')) {
            return $this->downloadWithCurl($url);
        }
        return $this->downloadWithStreams($url);
    }

    private function downloadWithCurl(string $url): string
    {
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Could not initialize the CelesTrak request.');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(4, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'AquellasLunas/1.0 satellite-tle-provider',
            CURLOPT_HTTPHEADER => ['Accept: text/plain'],
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException('CelesTrak download failed (HTTP ' . $status . ($error !== '' ? ', transport error' : '') . ').');
        }
        return $body;
    }

    private function downloadWithStreams(string $url): string
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => $this->timeoutSeconds, 'ignore_errors' => true,
            'header' => "Accept: text/plain\r\nUser-Agent: AquellasLunas/1.0 satellite-tle-provider\r\n",
        ]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = 0;
        if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $match) === 1) $status = (int) $match[1];
        if (!is_string($body) || $status !== 200) throw new RuntimeException('CelesTrak download failed (HTTP ' . $status . ').');
        return $body;
    }
}
