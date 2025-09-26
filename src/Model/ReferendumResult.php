<?php

namespace ElectionScraperVdA\Model;

/**
 * Represents the referendum statistics collected for a single entity (municipality or summary).
 */
class ReferendumResult
{
  /**
   * Entity label coming from the source (e.g. municipality name with progress info).
   *
   * @var string
   */
  public string $entita;

  /**
   * Number of registered voters.
   *
   * @var int
   */
  public int $elettori;

  /**
   * Number of voters who cast a ballot.
   *
   * @var int
   */
  public int $votanti;

  /**
   * Valid votes in favour of the referendum question.
   *
   * @var int
   */
  public int $siValidi;

  /**
   * Valid votes against the referendum question.
   *
   * @var int
   */
  public int $noValidi;

  /**
   * Unassigned "yes" votes (e.g. waiting for allocation).
   *
   * @var int
   */
  public int $siNonAssegnati;

  /**
   * Unassigned "no" votes.
   *
   * @var int
   */
  public int $noNonAssegnati;

  /**
   * Invalid ballots marked as null.
   *
   * @var int
   */
  public int $nulle;

  /**
   * Invalid ballots left blank.
   *
   * @var int
   */
  public int $bianche;

  /**
   * Turnout percentage reported by the source.
   *
   * @var float
   */
  public float $affluenzaPercent;

  /**
   * Percentage of valid "yes" votes.
   *
   * @var float
   */
  public float $siValidiPercent;

  /**
   * Percentage of valid "no" votes.
   *
   * @var float
   */
  public float $noValidiPercent;

  /**
   * Percentage of unassigned "yes" votes.
   *
   * @var float
   */
  public float $siNonAssPercent;

  /**
   * Percentage of unassigned "no" votes.
   *
   * @var float
   */
  public float $noNonAssPercent;

  /**
   * Percentage of null ballots.
   *
   * @var float
   */
  public float $nullePercent;

  /**
   * Percentage of blank ballots.
   *
   * @var float
   */
  public float $bianchePercent;

  /**
   * Indicates whether counting is completed for the entity.
   *
   * @var bool
   */
  public bool $isCountCompleted;

  /**
   * Builds an instance of {@see ReferendumResult} from the raw table values.
   *
   * @param array<int, string> $values Values extracted from the HTML table.
   *
   * @return self
   */
  public static function fromArray(array $values): self
  {
    $instance = new self();
    
    $instance->entita = $values[0];
    $instance->elettori = self::parseInteger($values[1]);
    $instance->votanti = self::parseInteger($values[2]);
    $instance->siValidi = self::parseInteger($values[3]);
    $instance->noValidi = self::parseInteger($values[4]);
    $instance->siNonAssegnati = self::parseInteger($values[5]);
    $instance->noNonAssegnati = self::parseInteger($values[6]);
    $instance->nulle = self::parseInteger($values[7]);
    $instance->bianche = self::parseInteger($values[8]);
    $instance->affluenzaPercent = self::parseFloat($values[9]);
    $instance->siValidiPercent = self::parseFloat($values[10]);
    $instance->noValidiPercent = self::parseFloat($values[11]);
    $instance->siNonAssPercent = self::parseFloat($values[12]);
    $instance->noNonAssPercent = self::parseFloat($values[13]);
    $instance->nullePercent = self::parseFloat($values[14]);
    $instance->bianchePercent = self::parseFloat($values[15]);

    $instance->isCountCompleted = self::determineCountStatus($instance->entita);
    
    return $instance;
  }

  /**
   * Normalises a numeric string with thousands separators into an integer.
  *
  * @return int
   */
  private static function parseInteger(string $value): int
  {
    $cleaned = str_replace(['.', ' '], '', trim($value));
    return (int) $cleaned;
  }

  /**
   * Converts a percentage formatted with locale-specific separators into a float.
  *
  * @return float
   */
  private static function parseFloat(string $value): float
  {
    $cleaned = str_replace([' ', '%'], '', trim($value));
    $cleaned = str_replace(',', '.', $cleaned);
    return (float) $cleaned;
  }

  /**
   * Determines if the counting is completed based on the entity description.
  *
  * @return bool
   */
  private static function determineCountStatus(string $entita): bool
  {
    if (preg_match('/(\d+)\s+su\s+(\d+)/', $entita, $matches)) {
      $current = (int) $matches[1];
      $total = (int) $matches[2];
      return $current === $total;
    }
    
    return true;
  }

  /**
   * Percentage of yes votes over the valid votes.
  *
  * @return float
   */
  public function getSiPercent(): float
  {
    $totalValid = $this->siValidi + $this->noValidi;
    return $totalValid > 0 ? ($this->siValidi / $totalValid) * 100 : 0.0;
  }

  /**
   * Percentage of no votes over the valid votes.
  *
  * @return float
   */
  public function getNoPercent(): float
  {
    $totalValid = $this->siValidi + $this->noValidi;
    return $totalValid > 0 ? ($this->noValidi / $totalValid) * 100 : 0.0;
  }

  /**
   * Turnout percentage computed on the fly from voters and electors.
  *
  * @return float
   */
  public function getTurnoutPercent(): float
  {
    return $this->elettori > 0 ? ($this->votanti / $this->elettori) * 100 : 0.0;
  }

  /**
   * Total number of valid ballots (yes + no).
  *
  * @return int
   */
  public function getTotalValidVotes(): int
  {
    return $this->siValidi + $this->noValidi;
  }

  /**
   * Total number of unassigned votes.
  *
  * @return int
   */
  public function getTotalUnassignedVotes(): int
  {
    return $this->siNonAssegnati + $this->noNonAssegnati;
  }

  /**
   * Total number of invalid ballots (null + blank).
  *
  * @return int
   */
  public function getTotalInvalidBallots(): int
  {
    return $this->nulle + $this->bianche;
  }

  /**
   * Percentage of invalid ballots over the cast ballots.
  *
  * @return float
   */
  public function getInvalidBallotsPercent(): float
  {
    return $this->votanti > 0 ? ($this->getTotalInvalidBallots() / $this->votanti) * 100 : 0.0;
  }

  /**
   * Checks whether the referendum has reached the quorum threshold (50%).
  *
  * @return bool
   */
  public function hasReachedQuorum(): bool
  {
    return $this->getTurnoutPercent() > 50.0;
  }

  /**
   * Returns true if the yes votes are leading.
  *
  * @return bool
   */
  public function isSiWinning(): bool
  {
    return $this->siValidi > $this->noValidi;
  }

  /**
   * Generates a compact textual summary for reporting purposes.
  *
  * @return string
   */
  public function getResultSummary(): string
  {
    $isSiWinning = $this->isSiWinning();
    $siPercent = number_format($this->getSiPercent(), 2);
    $noPercent = number_format($this->getNoPercent(), 2);
    $turnout = number_format($this->getTurnoutPercent(), 2);
    $isAostaCity = $this->isAostaCity();
    $isRegionalSummary = $this->isRegionalSummary();
    $entita = $isAostaCity ? 'AOSTA (' . $this->entita . ')' : $this->entita;
    $entita = $isRegionalSummary ? 'REGIONE (' . $this->entita . ')' : $entita;
    $status = $this->isCountCompleted ? '' : ' [PARZIALE]';
    
    if ($isSiWinning) {
      $result = sprintf(
        '%s: SÌ vince con %s%% (NO: %s%%) - Affluenza: %s%%%s',
        $entita,
        $siPercent,
        $noPercent,
        $turnout,
        $status
      );
    } else {
      $result = sprintf(
        '%s: NO vince con %s%% (SÌ: %s%%) - Affluenza: %s%%%s',
        $entita,
        $noPercent,
        $siPercent,
        $turnout,
        $status
      );
    }
    
    return $result;
  }

  /**
   * Detects if the entity represents the regional aggregate summary.
  *
  * @return bool
   */
  public function isRegionalSummary(): bool
  {
    return preg_match('/\d+\s+su\s+150/', $this->entita) === 1;
  }

  /**
   * Detects if the entity refers specifically to the city of Aosta.
  *
  * @return bool
   */
  public function isAostaCity(): bool
  {
    return preg_match('/\d+\s+su\s+38/', $this->entita) === 1;
  }

  /**
   * True for single municipalities (neither region nor city summaries).
  *
  * @return bool
   */
  public function isSingleMunicipality(): bool
  {
    return !$this->isRegionalSummary() && !$this->isAostaCity();
  }

  /**
   * Returns the number of sections already scrutinised or null if not available.
  *
  * @return int|null
   */
  public function getScrutinizedCount(): ?int
  {
    if (preg_match('/(\d+)\s+su\s+\d+/', $this->entita, $matches)) {
      return (int) $matches[1];
    }
    return null;
  }

  /**
   * Returns the total number of sections or null if not mentioned.
  *
  * @return int|null
   */
  public function getTotalCount(): ?int
  {
    if (preg_match('/\d+\s+su\s+(\d+)/', $this->entita, $matches)) {
      return (int) $matches[1];
    }
    return null;
  }
}