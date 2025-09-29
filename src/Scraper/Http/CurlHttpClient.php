<?php
declare(strict_types=1);

namespace ElectionScraperVdA\Scraper\Http;

final class CurlHttpClient implements HttpClientInterface
{
    /** @var array<string, string> */
    private array $defaultHeaders;
    private float $timeout;
    private bool $verifyPeer;

    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(array $defaultHeaders = [], float $timeout = 10.0, bool $verifyPeer = false)
    {
        $this->defaultHeaders = $defaultHeaders + [
            'User-Agent' => 'ElectionScraperVdA/1.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ];
        $this->timeout = $timeout;
        $this->verifyPeer = $verifyPeer;
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): string
    {
        $mergedHeaders = $this->mergeHeaders($headers);
        $headerLines = $this->formatHeaders($mergedHeaders);

        if (\function_exists('curl_init')) {
            return $this->requestWithCurl($url, $mergedHeaders, $headerLines);
        }

        return $this->requestWithStream($url, $headerLines);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function mergeHeaders(array $headers): array
    {
        // Later keys in $headers should override defaults
        $normalized = $this->defaultHeaders;
        foreach ($headers as $name => $value) {
            $normalized[$name] = $value;
        }

        if (!isset($normalized['User-Agent']) || $normalized['User-Agent'] === '') {
            $normalized['User-Agent'] = 'ElectionScraperVdA/1.0';
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $headers
     * @return string[]
     */
    private function formatHeaders(array $headers): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return $lines;
    }

    /**
     * @param array<string, string> $headers
     * @param string[] $headerLines
     */
    private function requestWithCurl(string $url, array $headers, array $headerLines): string
    {
        $handle = \curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('Impossibile inizializzare cURL.');
        }

        \curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $this->verifyPeer ? 2 : 0,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => $headers['User-Agent'] ?? 'ElectionScraperVdA/1.0',
        ]);

        $response = \curl_exec($handle);
        if ($response === false) {
            $error = \curl_error($handle) ?: 'Errore sconosciuto';
            \curl_close($handle);
            throw new \RuntimeException(sprintf('Richiesta HTTP fallita: %s', $error));
        }

        $status = (int) \curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        \curl_close($handle);

        if ($status >= 400) {
            throw new \RuntimeException(sprintf('Richiesta HTTP fallita con status %d per %s', $status, $url));
        }

        return (string) $response;
    }

    /**
     * @param string[] $headerLines
     */
    private function requestWithStream(string $url, array $headerLines): string
    {
        $context = \stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->verifyPeer,
                'verify_peer_name' => $this->verifyPeer,
            ],
        ]);

        $response = @\file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last()['message'] ?? 'Errore sconosciuto';
            throw new \RuntimeException(sprintf('Richiesta HTTP fallita: %s', $error));
        }

        $status = $this->extractStatusCode($http_response_header ?? []); // @phpstan-ignore-line
        if ($status !== null && $status >= 400) {
            throw new \RuntimeException(sprintf('Richiesta HTTP fallita con status %d per %s', $status, $url));
        }

        return (string) $response;
    }

    /**
     * @param string[] $responseHeaders
     */
    private function extractStatusCode(array $responseHeaders): ?int
    {
        if (empty($responseHeaders)) {
            return null;
        }

        $statusLine = $responseHeaders[0] ?? '';
        if ($statusLine === '') {
            return null;
        }

        if (preg_match('#HTTP/\S+\s+(\d{3})#', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
