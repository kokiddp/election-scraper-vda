<?php
declare(strict_types=1);

namespace ElectionScraperVdA\Scraper;

use ElectionScraperVdA\Scraper\Http\CurlHttpClient;
use ElectionScraperVdA\Scraper\Http\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * @template TResult
 * Base astratta per tutti gli scraper HTML.
 * Fornisce:
 *  - HTTP client
 *  - Caching opzionale via callback ($cacheCallback)
 *  - Logging opzionale (PSR-3)
 *  - Parsing DOM + helpers numerici
 *  - Eccezioni specializzate (ScraperException)
 */
abstract class AbstractHtmlScraper implements ScraperInterface
{
  protected HttpClientInterface $httpClient;
  protected \DOMDocument $dom;
  protected \DOMXPath $xpath;
  protected ?LoggerInterface $logger;
  /** @var null|callable(string,string,callable):string */
  protected $cacheCallback;
  protected ?CacheInterface $psr16Cache = null;
  protected ?int $psr16Ttl = null;

  public function __construct(
    ?HttpClientInterface $httpClient = null,
    ?LoggerInterface $logger = null,
    ?callable $cacheCallback = null,
    ?CacheInterface $psr16Cache = null,
    ?int $psr16Ttl = 300
  )
  {
    $this->httpClient = $httpClient ?? new CurlHttpClient();
    $this->dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $this->logger = $logger;
    $this->cacheCallback = $cacheCallback;
    $this->psr16Cache = $psr16Cache;
    $this->psr16Ttl = $psr16Ttl;
  }

  /**
   * @return TResult
   * @throws ScraperException
   */
  public function fetch(string $url)
  {
    try {
      $download = function(string $effectiveUrl): string {
        return $this->httpClient->get($effectiveUrl);
      };
      if ($this->psr16Cache) {
        $cacheKey = 'es_' . md5($url);
        $html = $this->psr16Cache->get($cacheKey);
        if ($html === null) {
          $html = $download($url);
          try { $this->psr16Cache->set($cacheKey, $html, $this->psr16Ttl); } catch (\Throwable $ce) {
            $this->logger?->warning('Cache store failed', ['key'=>$cacheKey,'error'=>$ce->getMessage()]);
          }
        } else {
          $this->logger?->info('Cache hit', ['url'=>$url,'key'=>$cacheKey]);
        }
      } elseif ($this->cacheCallback) {
        $html = ($this->cacheCallback)(md5($url), $url, $download);
      } else {
        $html = $download($url);
      }
      $this->logger?->info('Fetched election page', ['url' => $url, 'bytes' => strlen($html)]);
    } catch (\Throwable $e) {
      $this->logger?->error('Fetch failure', ['url' => $url, 'error' => $e->getMessage()]);
      throw ScraperException::network($e->getMessage(), $e);
    }
    return $this->parseHtml($html);
  }

  /**
   * @return TResult
   * @throws ScraperException
   */
  public function parseHtml(string $html)
  {
    // Heuristic: completely tag-less or empty content should be considered invalid for our scraping purposes
    if (trim($html) === '' || !preg_match('/</', $html)) {
      $this->logger?->error('Invalid HTML provided (no markup detected)');
      throw ScraperException::parsing('HTML non valido fornito (nessun markup)');
    }
    $html = $this->normalizeEncoding($html);
    if (!$this->dom->loadHTML($html)) {
      $this->logger?->error('Invalid HTML provided');
      throw ScraperException::parsing('HTML non valido fornito');
    }
    $this->xpath = new \DOMXPath($this->dom);
    return $this->doParse();
  }

  /** @return TResult */
  abstract protected function doParse();

  /* ===== Helpers comuni ===== */
  protected function parseInt(string $text): int
  {
    $clean = preg_replace('/[^0-9]/', '', $text) ?? '';
    return $clean === '' ? 0 : (int)$clean;
  }

  protected function parseFloat(string $text): float
  {
    $clean = str_replace(['%',' '], '', trim($text));
    $clean = str_replace(',', '.', $clean);
    if ($clean === '') return 0.0;
    return (float)preg_replace('/[^0-9.\-]/', '', $clean);
  }

  protected function innerHTML(\DOMNode $node): string
  {
    $html = '';
    foreach ($node->childNodes as $child) {
      $html .= $this->dom->saveHTML($child);
    }
    return $html;
  }

  /**
   * Rileva l'encoding (grezzo) e normalizza a UTF-8 se necessario.
   * Approccio conservativo per evitare dipendenze esterne.
   */
  protected function normalizeEncoding(string $html): string
  {
    // Se contiene dichiarazione meta charset, lascia stare
    if (stripos($html, 'charset=') !== false || str_contains($html, '<meta charset')) {
      return $this->toUtf8($html);
    }
    // Heuristic: se non è UTF-8 valido, prova ISO-8859-1
    if (!mb_check_encoding($html, 'UTF-8')) {
      $converted = @mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
      if ($converted !== false) {
        $html = $converted;
      }
    }
    return $this->toUtf8($html);
  }

  private function toUtf8(string $html): string
  {
    // Assicura dichiarazione meta utf-8 per DOMDocument se mancante
    if (stripos($html, '<meta charset') === false) {
      // Inserisci subito dopo <head> se possibile
      $html = preg_replace('/<head(.*?)>/i', '<head$1><meta charset="UTF-8">', $html, 1) ?? $html;
    }
    return $html;
  }
}
