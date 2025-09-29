<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\RegionalScraper;

class RegionalScraperTest extends TestCase
{
  public function testParseExample(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/regionali_137_example.html');
    $scraper = new RegionalScraper();
    $payload = $scraper->parseHtml($html);
    $this->assertIsArray($payload);
    $this->assertArrayHasKey('results', $payload);
    $this->assertArrayHasKey('summary', $payload);

    $results = $payload['results'];
    $summary = $payload['summary'];

    $this->assertNotEmpty($results);
    $this->assertCount(10, $results, 'Expected 10 liste');
    $totalVotes = array_sum(array_map(function($r){return $r->voti;}, $results));
    $this->assertGreaterThan(60000, $totalVotes);
    $sorted = $scraper->sortByVotes($results);
    $this->assertGreaterThanOrEqual($sorted[1]->voti ?? 0, $sorted[0]->voti);
    $withSeats = $scraper->getListsWithSeats($results);
    foreach ($withSeats as $r) { $this->assertTrue($r->seggi > 0); }

    $this->assertSame(67159, $summary['schede_scrutinate']);
    $this->assertEqualsWithDelta(100.0, $summary['schede_scrutinate_percent'], 0.01);
    $this->assertSame(812, $summary['schede_bianche']);
    $this->assertEqualsWithDelta(1.21, $summary['schede_bianche_percent'], 0.01);
    $this->assertSame(2629, $summary['schede_nulle']);
    $this->assertEqualsWithDelta(3.91, $summary['schede_nulle_percent'], 0.01);
  }
}
