<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\Model\BallottaggioResult;
use ElectionScraperVdA\Model\BallottaggioCandidate;

class BallottaggioResultModelTest extends TestCase
{
  public function testMargins(): void
  {
    $r = new BallottaggioResult('X');
    $c1 = new BallottaggioCandidate('Alice','Bob','Lista1'); $c1->voti = 1000; $c1->percentualeVoti = 52.5;
    $c2 = new BallottaggioCandidate('Carlo','Dan','Lista2'); $c2->voti = 900; $c2->percentualeVoti = 47.5;
    $r->addCandidato($c1); $r->addCandidato($c2);
    $this->assertEquals(100, $r->getMargineVittoria());
    $this->assertEquals(5.0, $r->getMargineVittoriaPercent());
    $this->assertStringContainsString('Vince', $r->getSummary());
  }
}
