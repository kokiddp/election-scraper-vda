<?php
use PHPUnit\Framework\TestCase;
use ElectionScraperVdA\Value\VoteCount;
use ElectionScraperVdA\Value\Percentage;

class ValueObjectsTest extends TestCase
{
  public function testVoteCountBasic(): void
  {
    $v = VoteCount::fromInt(1234);
    $this->assertSame(1234, $v->toInt());
    $this->assertSame('1.234', (string)$v);
    $sum = $v->add(VoteCount::fromInt(6));
    $this->assertSame(1240, $sum->toInt());
  }

  public function testPercentageFormatting(): void
  {
    $p = Percentage::fromFloat(50.4567);
    $this->assertSame(50.4567, $p->toFloat());
    $this->assertSame('50,46%', $p->format(2));
  }
}
