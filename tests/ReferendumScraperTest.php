<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\ReferendumScraper;

class ReferendumScraperTest extends TestCase
{
  public function testParseReferendum(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/referendum_187_example.html');
    $scraper = new ReferendumScraper();
    $results = $scraper->parseHtml($html);
    $this->assertNotEmpty($results);
    $this->assertGreaterThan(50, count($results));
    $first = $results[0];
    $this->assertMatchesRegularExpression('/150\s+su\s+150/', $first->entita);
    $this->assertGreaterThanOrEqual(0, $first->votanti);
    $this->assertNotEmpty($first->getResultSummary());
    $completed = array_filter($results, fn($r)=>$r->isCountCompleted);
    $this->assertGreaterThan(0, count($completed));
  }
}
