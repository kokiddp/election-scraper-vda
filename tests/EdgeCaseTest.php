<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\ComunalScraper;
use ElectionScraperVdA\RegionalScraper;
use ElectionScraperVdA\ReferendumScraper;
use ElectionScraperVdA\BallottaggioScraper;

class EdgeCaseTest extends TestCase
{
  public function testComunalMalformedHtml(): void
  {
    $html = '<html><body><h1 class="testi">Voti - TEST</h1><div class="preferenze-lista"></div>';
    $scraper = new ComunalScraper();
    $result = $scraper->parseHtml($html);
    $this->assertSame('TEST', $result->nomeComune);
    $this->assertEmpty($result->liste);
    $this->assertStringContainsString('Affluenza', $result->getSummary());
  }

  public function testRegionalEmpty(): void
  {
    $scraper = new RegionalScraper();
    $results = $scraper->parseHtml('<html><table><tr class="voti-lista"></tr></table></html>');
    $this->assertSame([], $results);
  }

  public function testReferendumIncompletePairs(): void
  {
    $scraper = new ReferendumScraper();
    $results = $scraper->parseHtml('<div id="ctl00_ContentPlaceHolderContenuto_divRisultati"><tr class="tabella-dati-riga-affluenza"><td>AOSTA</td></tr></div>');
    $this->assertEmpty($results);
  }

  public function testBallottaggioMissingCandidates(): void
  {
    $scraper = new BallottaggioScraper();
    $result = $scraper->parseHtml('<html><title>Voti - ABC</title><div class="riepilogo-elezione-box"></div></html>');
    $this->assertSame('ABC', $result->nomeComune);
    $this->assertEmpty($result->candidati);
  }
}
