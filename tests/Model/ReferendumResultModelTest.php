<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\Model\ReferendumResult;

class ReferendumResultModelTest extends TestCase
{
  private function make(array $vals): ReferendumResult { return ReferendumResult::fromArray($vals); }

  public function testPercentHelpers(): void
  {
    $r = $this->make([
      'Comune A', '1000','600','350','250','0','0','10','5',
      '60%','58.33%','41.67%','0%','0%','1.67%','0.83%'
    ]);
    $this->assertTrue($r->isSiWinning());
    $this->assertGreaterThan(50, $r->getSiPercent());
    $this->assertNotEmpty($r->getResultSummary());
  }

  public function testQuorum(): void
  {
    $r = $this->make([
      'Comune B', '1000','400','200','180','0','0','5','5',
      '40%','52%','48%','0%','0%','1.25%','1.25%'
    ]);
    $this->assertFalse($r->hasReachedQuorum());
  }
}
