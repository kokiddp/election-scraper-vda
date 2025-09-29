<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\RegionalCoalitionScraper;
use ElectionScraperVdA\Model\RegionalCoalition;
use ElectionScraperVdA\Model\RegionalResult;

class RegionalCoalitionScraperTest extends TestCase
{
  public function testCoalitionsAreParsed(): void
  {
  $html = file_get_contents(__DIR__ . '/../examples/regionale_coalitions_190_example.html');
    $scraper = new RegionalCoalitionScraper();
    $result = $scraper->parseHtml($html);

  $this->assertSame(103223, $result->elettori);
  $this->assertSame(65011, $result->votanti);
  $this->assertEqualsWithDelta(62.98, $result->affluenzaPercent, 0.01);
  $this->assertSame(9652, $result->schedeScrutinate);
  $this->assertEqualsWithDelta(14.85, $result->schedeScrutinatePercent, 0.01);
  $this->assertSame(231, $result->schedeBianche);
  $this->assertEqualsWithDelta(2.39, $result->schedeBianchePercent, 0.01);
  $this->assertSame(300, $result->schedeNulle);
  $this->assertEqualsWithDelta(3.11, $result->schedeNullePercent, 0.01);

    $this->assertNotEmpty($result->coalizioni);
    $this->assertCount(7, $result->coalizioni);
    $this->assertContainsOnlyInstancesOf(RegionalCoalition::class, $result->coalizioni);

    $this->assertNotEmpty($result->liste);
    $this->assertCount(9, $result->liste);
    $this->assertContainsOnlyInstancesOf(RegionalResult::class, $result->liste);

    $primaCoalizione = $result->coalizioni[0];
    $this->assertSame(5, $primaCoalizione->programmaNumero);
    $this->assertSame(3425, $primaCoalizione->voti);
    $this->assertEqualsWithDelta(37.55, $primaCoalizione->percentualeVoti, 0.01);
    $this->assertSame(1, $primaCoalizione->votiContestati);

    $this->assertNotEmpty($result->getSummary());
  }
}
