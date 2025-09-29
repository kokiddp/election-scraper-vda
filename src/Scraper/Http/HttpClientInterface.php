<?php
declare(strict_types=1);

namespace ElectionScraperVdA\Scraper\Http;

/**
 * Minimal HTTP client abstraction used by the scrapers.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @throws \RuntimeException When the request cannot be completed successfully.
     */
    public function get(string $url, array $headers = []): string;
}
