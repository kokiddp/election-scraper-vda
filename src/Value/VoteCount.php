<?php
namespace ElectionScraperVdA\Value;

/**
 * Simple immutable wrapper for vote counts ensuring non-negative values.
 */
final class VoteCount
{
  /**
   * Internal vote amount.
   *
   * @var int
   */
  private int $value;

  /**
   * @param int $value Raw vote count.
   */
  private function __construct(int $value)
  {
    $this->value = max(0,$value);
  }

  /**
   * Builds a new instance from an integer value.
   *
   * @param int $value Vote total to encapsulate.
   *
   * @return self
   */
  public static function fromInt(int $value): self
  {
    return new self($value);
  }

  /**
   * Returns the underlying integer value.
   *
   * @return int
   */
  public function toInt(): int
  {
    return $this->value;
  }

  /**
   * Returns a new instance representing the sum with another {@see VoteCount}.
   *
   * @param self $other Another vote count to add.
   *
   * @return self
   */
  public function add(self $other): self
  {
    return new self($this->value + $other->value);
  }

  /**
   * Formats the vote count using Italian thousands separators.
   *
   * @return string
   */
  public function __toString(): string
  {
    return number_format($this->value,0,',','.');
  }
}
