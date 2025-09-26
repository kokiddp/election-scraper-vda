<?php
namespace ElectionScraperVdA\Value;

/**
 * Immutable value object representing a percentage in the 0..100 range.
 */
final class Percentage
{
  /**
   * Internal representation in percentage points (0..100).
   *
   * @var float
   */
  private float $value; // stored 0..100

  /**
   * @param float $value Raw percentage points.
   */
  private function __construct(float $value)
  {
    $this->value = $value < 0 ? 0.0 : $value;
  }

  /**
   * Creates a percentage instance from a float value.
   *
   * @param float $value Percentage points.
   *
   * @return self
   */
  public static function fromFloat(float $value): self
  {
    return new self($value);
  }

  /**
   * Returns the raw float representation of the percentage.
   *
   * @return float
   */
  public function toFloat(): float
  {
    return $this->value;
  }

  /**
   * Formats the percentage according to Italian numeric conventions.
   *
   * @param int $decimals Number of decimal places.
   *
   * @return string
   */
  public function format(int $decimals=2): string
  {
    return number_format($this->value,$decimals,',','.') . '%';
  }
  
  /**
   * Cast the value object to string using {@see self::format()}.
   */
  public function __toString(): string
  {
    return $this->format();
  }
}
