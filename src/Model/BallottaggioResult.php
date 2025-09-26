<?php

namespace ElectionScraperVdA\Model;

/**
 * Class representing the results of a runoff election for municipal elections.
 * The runoff features only two mayoral candidates with their respective lists.
 */
class BallottaggioResult extends AbstractElectionResult
{
  /**
   * Number of ballots counted.
   * 
   * @var int
   */
  public int $schedeScrutinate = 0;

  /**
   * Percentage of ballots counted.
   *
   * @var float
   */
  public float $schedeScrutinatePercent = 0.0;

  /**
   * Array of candidates (max 2 in the runoff).
   *
   * @var BallottaggioCandidate[]
   */
  public array $candidati;

  /**
   * Constructor for BallottaggioResult.
   *
   * @param string $nomeComune Name of the municipality.
   */
  public function __construct(string $nomeComune = '')
  {
    parent::__construct($nomeComune);
    $this->candidati = [];
  }

  /**
   * Adds a candidate to the results.
   *
   * @param BallottaggioCandidate $candidato The candidate to add.
   * @return void
   */
  public function addCandidato(BallottaggioCandidate $candidato): void
  {
    $this->candidati[] = $candidato;
  }

  /**
   * Sorts candidates by number of votes (descending).
   *
   * @return void
   */
  public function sortCandidatiByVoti(): void
  {
    usort($this->candidati, fn($a, $b) => $b->voti <=> $a->voti);
  }

  /**
   * Returns the winning candidate (the one with the most votes).
   *
   * @return BallottaggioCandidate|null
   */
  public function getCandidatoVincitore(): ?BallottaggioCandidate
  {
    if (empty($this->candidati)) {
      return null;
    }

    $this->sortCandidatiByVoti();
    return $this->candidati[0];
  }

  /**
   * Checks if the runoff is complete (has exactly 2 candidates).
   *
   * @return bool
   */
  public function isBallottaggioCompleto(): bool
  {
    return count($this->candidati) === 2;
  }

  /**
   * Calculates the margin of victory in absolute votes.
   *
   * @return int
   */
  public function getMargineVittoria(): int
  {
    if (!$this->isBallottaggioCompleto()) {
      return 0;
    }

    $this->sortCandidatiByVoti();
    return $this->candidati[0]->voti - $this->candidati[1]->voti;
  }

  /**
   * Calculates the margin of victory in percentage.
   *
   * @return float
   */
  public function getMargineVittoriaPercent(): float
  {
    if (!$this->isBallottaggioCompleto()) {
      return 0.0;
    }

    $this->sortCandidatiByVoti();
    return $this->candidati[0]->percentualeVoti - $this->candidati[1]->percentualeVoti;
  }

  /**
   * Returns a summary of the election results.
   *
   * @return string
   */
  public function getSummary(): string
  {
    $this->ensureAffluenza();
    $winner = $this->getCandidatoVincitore();
    if ($winner) {
      return sprintf(
        '%s: Vince %s con %d voti (%.2f%%) – Affluenza %s',
        $this->nomeComune,
        $winner->getNomeCompleto(),
        $winner->voti,
        $winner->percentualeVoti,
        number_format($this->affluenzaPercent,2,',','.') . '%'
      );
    }
    return sprintf('%s: Affluenza %s', $this->nomeComune, number_format($this->affluenzaPercent,2,',','.') . '%');
  }
}

/**
 * Class representing a candidate in the runoff election.
 */
class BallottaggioCandidate
{
  /**
   * Candidate mayor's name
   * 
   * @var string
   */
  public string $sindaco;

  /**
   * Candidate vice mayor's name
   *
   * @var string
   */
  public string $viceSindaco;

  /**
   * Candidate list/coalition name
   *
   * @var string
   */
  public string $nomeLista;

  /**
   * Number of votes received
   *
   * @var int
   */
  public int $voti = 0;

  /**
   * Percentage of votes received
   *
   * @var float
   */
  public float $percentualeVoti = 0.0;

  /**
   * Number of contested votes
   *
   * @var int
   */
  public int $votiContestati = 0;

  /**
   * URL of the list's symbol image
   *
   * @var string
   */
  public string $simboloUrl = '';

  /**
   * @param string $sindaco     Mayor candidate name.
   * @param string $viceSindaco Vice mayor candidate name (if present).
   * @param string $nomeLista   Supporting list or coalition name.
   */
  public function __construct(string $sindaco, string $viceSindaco = '', string $nomeLista = '')
  {
    $this->sindaco = $sindaco;
    $this->viceSindaco = $viceSindaco;
    $this->nomeLista = $nomeLista;
  }

  /**
   * Checks if this candidate is the winner compared to another.
   *
   * @param BallottaggioCandidate $altroCandidate Candidate to compare against.
   * @return bool
   */
  public function isVincitore(BallottaggioCandidate $altroCandidate): bool
  {
    return $this->voti > $altroCandidate->voti;
  }

  /**
   * Returns the full name of the candidate.
   *
   * @return string
   */
  public function getNomeCompleto(): string
  {
    if (!empty($this->viceSindaco)) {
      return $this->sindaco . ' / ' . $this->viceSindaco;
    }
    return $this->sindaco;
  }
}
