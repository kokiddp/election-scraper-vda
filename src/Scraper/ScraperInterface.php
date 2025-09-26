<?php
declare(strict_types=1);

namespace ElectionScraperVdA\Scraper;

interface ScraperInterface
{
  public function fetch(string $url);
  
  public function parseHtml(string $html);
}
