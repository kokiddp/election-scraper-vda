<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\ComunalCoalitionScraper;

class ComunalCoalitionScraperTest extends TestCase
{
  public function testParseCoalition(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/comunaliaosta_120_example.html');
    $scraper = new ComunalCoalitionScraper();
    $result = $scraper->parseHtml($html);
    $this->assertNotEmpty($result->coalizioni);
    $this->assertSame(28651, $result->elettori);
    $this->assertSame(17553, $result->votanti);
    $this->assertEqualsWithDelta(61.26, $result->affluenzaPercent, 0.02);
    $this->assertCount(7, $result->coalizioni);
    $this->assertCount(11, $result->listeDettaglio);
    $coalizione = $result->getCoalizioneVincitrice();
    if ($coalizione) {
      $this->assertTrue($coalizione->votiTotali >= 0);
    }
    $this->assertNotEmpty($result->getSummary());
  }
}
