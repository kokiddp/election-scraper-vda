<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\RegionalCoalitionScraper;
use ElectionScraperVdA\Model\RegionalCoalition;
use ElectionScraperVdA\Model\RegionalResult;

class RegionalCoalitionScraperTest extends TestCase
{
  public function testCoalitionsAreParsed(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/regionali_142_example.html');
    $scraper = new RegionalCoalitionScraper();
    $result = $scraper->parseHtml($html);

    $this->assertSame(103127, $result->elettori);
    $this->assertSame(72701, $result->votanti);
    $this->assertEqualsWithDelta(70.50, $result->affluenzaPercent, 0.01);
    $this->assertSame(72701, $result->schedeScrutinate);
    $this->assertEqualsWithDelta(100.0, $result->schedeScrutinatePercent, 0.01);
    $this->assertSame(2642, $result->schedeBianche);
    $this->assertEqualsWithDelta(3.63, $result->schedeBianchePercent, 0.01);
    $this->assertSame(3793, $result->schedeNulle);
    $this->assertEqualsWithDelta(5.22, $result->schedeNullePercent, 0.01);

    $this->assertNotEmpty($result->coalizioni);
    $this->assertCount(7, $result->coalizioni);
    $this->assertContainsOnlyInstancesOf(RegionalCoalition::class, $result->coalizioni);

    $this->assertNotEmpty($result->liste);
    $this->assertContainsOnlyInstancesOf(RegionalResult::class, $result->liste);

    $primaCoalizione = $result->coalizioni[0];
    $this->assertSame(5, $primaCoalizione->programmaNumero);
    $this->assertSame(3233, $primaCoalizione->voti);
    $this->assertEqualsWithDelta(38.49, $primaCoalizione->percentualeVoti, 0.01);

    $this->assertNotEmpty($result->getSummary());
  }
}
