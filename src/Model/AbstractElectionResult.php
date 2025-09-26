<?php

namespace ElectionScraperVdA\Model;

use ElectionScraperVdA\Value\VoteCount;
use ElectionScraperVdA\Value\Percentage;

/**
 * Base class for election results (municipal, coalitions, runoff)
 * that share the same turnout and ballot statistics.
 */
abstract class AbstractElectionResult implements Summarizable
{
  /**
   * Name of the municipality the result refers to.
   *
   * @var string
   */
  public string $nomeComune;

  /**
   * Number of registered voters.
   *
   * @var int
   */
  public int $elettori = 0;

  /**
   * Number of people who cast a ballot.
   *
   * @var int
   */
  public int $votanti = 0;

  /**
   * Turnout percentage (0..100), when available.
   *
   * @var float
   */
  public float $affluenzaPercent = 0.0;

  /**
   * Count of blank ballots.
   *
   * @var int
   */
  public int $schedeBianche = 0;

  /**
   * Percentage of blank ballots.
   *
   * @var float
   */
  public float $schedeBianchePercent = 0.0;

  /**
   * Count of null ballots.
   *
   * @var int
   */
  public int $schedeNulle = 0;

  /**
   * Percentage of null ballots.
   *
   * @var float
   */
  public float $schedeNullePercent = 0.0;

  /**
   * @param string $nomeComune Name of the municipality.
   */
  public function __construct(string $nomeComune)
  {
    $this->nomeComune = $nomeComune;
  }

  /**
   * Lazily computes {@see $affluenzaPercent} if it is still zero.
   */
  public function ensureAffluenza(): void
  {
    if ($this->affluenzaPercent == 0.0 && $this->elettori > 0) {
      $this->affluenzaPercent = ($this->votanti / $this->elettori) * 100;
    }
  }

  /**
   * Returns the number of valid votes (total voters minus blank and null ballots).
   *
   * @return int
   */
  public function getVotiValidi(): int
  {
    return max(0, $this->votanti - $this->schedeBianche - $this->schedeNulle);
  }

  /**
   * Returns the {@see VoteCount} wrapper for registered voters.
   *
   * @return VoteCount
   */
  public function elettoriCount(): VoteCount
  {
    return VoteCount::fromInt($this->elettori);
  }

  /**
   * Returns the {@see VoteCount} wrapper for turnout voters.
   *
   * @return VoteCount
   */
  public function votantiCount(): VoteCount
  {
    return VoteCount::fromInt($this->votanti);
  }

  /**
   * Returns the {@see VoteCount} wrapper for valid ballots.
   *
   * @return VoteCount
   */
  public function votiValidiCount(): VoteCount
  {
    return VoteCount::fromInt($this->getVotiValidi());
  }

  /**
   * Returns the turnout value as {@see Percentage}, computing it when missing.
   *
   * @return Percentage
   */
  public function affluenzaPercentage(): Percentage
  {
    return Percentage::fromFloat(
      $this->affluenzaPercent ?: ($this->elettori > 0 ? ($this->votanti / $this->elettori) * 100 : 0)
    );
  }

  /**
   * Returns a human-readable summary of the election results.
   *
   * @return string
   */
  abstract public function getSummary(): string;
}
