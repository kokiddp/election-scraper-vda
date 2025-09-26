<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\RegionalScraper;

class RegionalScraperTest extends TestCase
{
  public function testParseExample(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/regionali_137_example.html');
    $scraper = new RegionalScraper();
    $results = $scraper->parseHtml($html);
    $this->assertNotEmpty($results);
    $this->assertCount(10, $results, 'Expected 10 liste');
    $totalVotes = array_sum(array_map(function($r){return $r->voti;}, $results));
    $this->assertGreaterThan(60000, $totalVotes);
    $sorted = $scraper->sortByVotes($results);
    $this->assertGreaterThanOrEqual($sorted[1]->voti ?? 0, $sorted[0]->voti);
    $withSeats = $scraper->getListsWithSeats($results);
    foreach ($withSeats as $r) { $this->assertTrue($r->seggi > 0); }
  }
}
