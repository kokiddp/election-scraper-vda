<?php
declare(strict_types=1);

namespace ElectionScraperVdA\Scraper;

use Throwable;

class ScraperException extends \RuntimeException
{
  public static function network(string $message, ?Throwable $previous = null): self
  {
    return new self('Network error: ' . $message, 0, $previous);
  }
  
  public static function parsing(string $message, ?Throwable $previous = null): self
  {
    return new self('Parsing error: ' . $message, 0, $previous);
  }
}
