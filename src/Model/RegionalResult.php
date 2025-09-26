<?php

namespace ElectionScraperVdA\Model;

/**
 * Represents aggregated results for a single list in the regional elections.
 */
class RegionalResult
{
  /**
   * Political list name.
   *
   * @var string
   */
  public string $nomeLista;

  /**
   * Total votes obtained by the list.
   *
   * @var int
   */
  public int $voti;

  /**
   * Vote percentage reported for the list.
   *
   * @var float
   */
  public float $percentuale;

  /**
   * Number of contested votes.
   *
   * @var int
   */
  public int $votiContestati;

  /**
   * Seats assigned to the list.
   *
   * @var int
   */
  public int $seggi;

  /**
   * Optional URL to the list's symbol image.
   *
   * @var string|null
   */
  public ?string $simboloUrl;


  /**
   * Factory helper to create a {@see RegionalResult} instance from raw values.
   *
   * @param string      $nomeLista      List name.
   * @param int         $voti           Total votes.
   * @param float       $percentuale    Vote percentage.
   * @param int         $votiContestati Contested votes count.
   * @param int         $seggi          Seats obtained.
   * @param string|null $simboloUrl     Optional symbol URL.
  *
  * @return self
   */
  public static function create(
    string $nomeLista,
    int $voti,
    float $percentuale,
    int $votiContestati,
    int $seggi,
    ?string $simboloUrl = null
  ): self {
    $instance = new self();
    $instance->nomeLista = $nomeLista;
    $instance->voti = $voti;
    $instance->percentuale = $percentuale;
    $instance->votiContestati = $votiContestati;
    $instance->seggi = $seggi;
    $instance->simboloUrl = $simboloUrl;
    
    return $instance;
  }

  /**
   * Parses a vote count string and returns the numeric value.
  *
  * @return int
   */
  private static function parseVoti(string $voti): int
  {
    $cleaned = str_replace(['.', ' ', ','], '', trim($voti));
    return (int) $cleaned;
  }

  /**
   * Parses a percentage string (with locale separators) into a float.
  *
  * @return float
   */
  private static function parsePercentuale(string $percentuale): float
  {
    $cleaned = str_replace(['%', ' '], '', trim($percentuale));
    $cleaned = str_replace(',', '.', $cleaned);
    return (float) $cleaned;
  }

  /**
   * Parses the seats column which may contain placeholders.
  *
  * @return int
   */
  private static function parseSeggi(string $seggi): int
  {
    $cleaned = trim($seggi);
    if ($cleaned === '-' || $cleaned === '' || $cleaned === '0') {
      return 0;
    }
    return (int) $cleaned;
  }

  /**
   * Creates a result from an HTML row data array.
   *
   * @param array<int, string> $data
  *
  * @return self
   */
  public static function fromArray(array $data): self
  {
    return self::create(
      $data[0] ?? '',
      self::parseVoti($data[1] ?? '0'),
      self::parsePercentuale($data[2] ?? '0%'),
      self::parseVoti($data[3] ?? '0'),
      self::parseSeggi($data[4] ?? '0'),
      $data[5] ?? null
    );
  }

  /**
   * Indicates whether the list has obtained at least one seat.
  *
  * @return bool
   */
  public function hasSeats(): bool
  {
    return $this->seggi > 0;
  }

  /**
   * Returns a concise textual representation of the list performance.
  *
  * @return string
   */
  public function getSummary(): string
  {
    $seggiText = $this->seggi > 0 ? " ({$this->seggi} seggi)" : " (nessun seggio)";
    return sprintf(
      '%s: %s voti (%.2f%%)%s',
      $this->nomeLista,
      number_format($this->voti, 0, ',', '.'),
      $this->percentuale,
      $seggiText
    );
  }

  /**
   * Returns the vote count formatted with thousands separators.
  *
  * @return string
   */
  public function getFormattedVotes(): string
  {
    return number_format($this->voti, 0, ',', '.');
  }

  /**
   * Returns the percentage formatted using Italian locale conventions.
  *
  * @return string
   */
  public function getFormattedPercentage(): string
  {
    return number_format($this->percentuale, 2, ',', '.') . '%';
  }

  /**
   * Comparator helper to sort results in descending order by votes.
  *
  * @return int Returns -1, 0 or 1 for sorting purposes.
   */
  public function compareByVotes(RegionalResult $other): int
  {
    return $other->voti <=> $this->voti;
  }

  /**
   * Comparator helper to sort results by seats, then by votes.
  *
  * @return int Returns -1, 0 or 1 for sorting purposes.
   */
  public function compareBySeats(RegionalResult $other): int
  {
    if ($other->seggi === $this->seggi) {
      return $other->voti <=> $this->voti; 
    }
    return $other->seggi <=> $this->seggi;
  }
}
