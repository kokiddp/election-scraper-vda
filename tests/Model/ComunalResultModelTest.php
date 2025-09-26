<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\Model\ComunalResult;
use ElectionScraperVdA\Model\ComunalList;
use ElectionScraperVdA\Model\ComunalCandidate;

class ComunalResultModelTest extends TestCase
{
  public function testWinnerLogic(): void
  {
    $r = new ComunalResult('TEST');
    $listA = new ComunalList('Lista A');
    $listA->votiSindaco = 600; $listA->percentualeVoti = 55.0; $listA->vincitrice = true;
    $listB = new ComunalList('Lista B');
    $listB->votiSindaco = 500; $listB->percentualeVoti = 45.0;
    $r->addLista($listA); $r->addLista($listB);
    $r->elettori = 2000; $r->votanti = 1100; $r->affluenzaPercent = 55.0;
    $winner = $r->getListaVincitrice();
    $this->assertSame('Lista A', $winner->nomeLista);
    $this->assertTrue($r->hasSindacoEletto());
    $this->assertFalse($r->isBallottaggio());
    $this->assertStringContainsString('Affluenza', $r->getSummary());
  }

  public function testBallottaggioScenario(): void
  {
    $r = new ComunalResult('TEST2');
    $a = new ComunalList('A'); $a->votiSindaco = 0; $a->percentualeVoti = 0;
    $b = new ComunalList('B'); $b->votiSindaco = 0; $b->percentualeVoti = 0;
    $r->addLista($a); $r->addLista($b);
    $this->assertTrue($r->isBallottaggio());
    $this->assertNull($r->getListaVincitrice());
  }

  public function testCandidateHelpers(): void
  {
    $r = new ComunalResult('HELP');
    $l = new ComunalList('L');
    $l->addCandidato(new ComunalCandidate('Mario', 120, 'C', true));
    $l->addCandidato(new ComunalCandidate('Luigi', 80, 'C'));
    $r->addLista($l);
    $eletti = $r->getCandidatiEletti();
    $this->assertCount(1, $eletti);
    $this->assertEquals(200, $l->getTotalVotiPreferenze());
  }
}
