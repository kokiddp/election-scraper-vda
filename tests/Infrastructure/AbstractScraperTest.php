<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;
use ElectionScraperVdA\Scraper\ScraperException;
use Psr\Log\NullLogger;

/** @covers AbstractHtmlScraper */
class AbstractScraperTest extends TestCase
{
  public function testCacheCallbackUsed(): void
  {
    $called = 0; $htmlProvided = '<html><body>OK</body></html>';
    $cache = function(string $key,string $url, callable $downloader) use (&$called,$htmlProvided){ $called++; return $htmlProvided; };
    $scraper = new class(null, new NullLogger(), $cache) extends AbstractHtmlScraper {
      protected function doParse() { return 'PARSED'; }
    };
    $result = $scraper->fetch('http://example.test');
    $this->assertSame('PARSED', $result);
    $this->assertEquals(1, $called);
  }

  public function testInvalidHtmlThrows(): void
  {
    $scraper = new class extends AbstractHtmlScraper { protected function doParse() { return 'X'; } };
    $this->expectException(ScraperException::class);
    // Passing a binary-like invalid string to provoke failure
    $scraper->parseHtml("\x00\x00\x00");
  }
}
