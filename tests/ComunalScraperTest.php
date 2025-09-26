<?php

use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\ComunalScraper;

class ComunalScraperTest extends TestCase
{
  public function testParseExample(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/comunali_146_3_example.html');
    $scraper = new ComunalScraper();
    $result = $scraper->parseHtml($html);
    $this->assertSame('AOSTA', $result->nomeComune);
    $this->assertSame(28467, $result->elettori);
    $this->assertSame(18259, $result->votanti);
    $this->assertEqualsWithDelta(64.14, $result->affluenzaPercent, 0.01);
    $this->assertCount(10, $result->liste);
    $this->assertSame($result->affluenzaPercent, $result->affluenzaPercentage()->toFloat());
    $this->assertGreaterThanOrEqual(0, $result->getVotiValidi());
    $listaVincitrice = $result->getListaVincitrice();
    if ($listaVincitrice) {
      $this->assertTrue($listaVincitrice->percentualeVoti > 50.0);
    }
    $this->assertNotEmpty($result->getSummary());
  }
}
