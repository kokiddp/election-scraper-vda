<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\BallottaggioScraper;

class BallottaggioScraperTest extends TestCase
{
  public function testParseBallottaggio(): void
  {
    $html = file_get_contents(__DIR__ . '/../examples/ballottaggio_154_3_example.html');
    $scraper = new BallottaggioScraper();
    $result = $scraper->parseHtml($html);
    $this->assertNotEmpty($result->candidati);
    $this->assertNotEmpty($result->getSummary());
    $this->assertSame('AOSTA', $result->nomeComune);
    $this->assertSame(28467, $result->elettori);
    $this->assertSame(13075, $result->votanti);
    $this->assertEqualsWithDelta(45.93, $result->affluenzaPercent, 0.01);
    $this->assertSame(81, $result->schedeBianche);
    $this->assertSame(256, $result->schedeNulle);
    $this->assertCount(2, $result->candidati);
    $winner = $result->getCandidatoVincitore();
    $this->assertNotNull($winner);
    $this->assertSame('NUTI Gianni', $winner->sindaco);
    $this->assertSame('BORRE Josette', $winner->viceSindaco);
    $this->assertSame(6794, $winner->voti);
    $this->assertEqualsWithDelta(53.34, $winner->percentualeVoti, 0.01);
    $this->assertStringContainsString('Vince', $result->getSummary());
  }
}
