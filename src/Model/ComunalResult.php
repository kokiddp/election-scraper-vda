<?php

namespace ElectionScraperVdA\Model;

/**
 * Represents a candidate belonging to a municipal list.
 */
class ComunalCandidate
{
  /**
   * Candidate full name.
   *
   * @var string
   */
  public string $nome;

  /**
   * Number of preference votes collected by the candidate.
   *
   * @var int
   */
  public int $voti;

  /**
   * Candidate role code (S, V, C...).
   *
   * @var string
   */
  public string $ruolo;

  /**
   * Whether the candidate has been elected.
   *
   * @var bool
   */
  public bool $eletto;

  /**
   * @param string $nome   Candidate full name.
   * @param int    $voti   Number of preference votes collected.
   * @param string $ruolo  Role code provided by the data source.
   * @param bool   $eletto Flag indicating if the candidate has been elected.
   */
  public function __construct(string $nome, int $voti, string $ruolo, bool $eletto = false)
  {
    $this->nome = $nome;
    $this->voti = $voti;
    $this->ruolo = $ruolo;
    $this->eletto = $eletto;
  }

  /**
   * Returns the human-readable role description based on the role code.
   *
   * @return string
   */
  public function getRuoloCompleto(): string
  {
    return match ($this->ruolo) {
      'S' => 'Sindaco',
      'V' => 'Vice Sindaco', 
      'C' => 'Consigliere',
      default => 'Sconosciuto'
    };
  }

  /**
   * Returns a short textual summary of the candidate performance.
   *
   * @return string
   */
  public function getSummary(): string
  {
    $elettoText = $this->eletto ? ' (ELETTO)' : '';
    return sprintf(
      '%s (%s): %d voti%s',
      $this->nome,
      $this->getRuoloCompleto(),
      $this->voti,
      $elettoText
    );
  }
}

/**
 * Stores the outcome of a municipal election, including lists and turnout.
 */
class ComunalResult extends AbstractElectionResult
{
  /**
   * List of electoral lists participating in the election.
   *
   * @var ComunalList[]
   */
  public array $liste;

  /**
   * @param string $nomeComune Municipality name associated with the result.
   */
  public function __construct(string $nomeComune)
  {
    parent::__construct($nomeComune);
    $this->liste = [];
  }

  /**
   * Adds a municipal list to the result model.
   *
   * @param ComunalList $lista List to add to the result.
   */
  public function addLista(ComunalList $lista): void
  {
    $this->liste[] = $lista;
  }

  /**
   * Returns the list declared as winner or the one with most votes.
   *
   * @return ComunalList|null
   */
  public function getListaVincitrice(): ?ComunalList
  {
    if (empty($this->liste)) {
      return null;
    }

    if ($this->isBallottaggio()) {
      return null;
    }

    foreach ($this->liste as $lista) {
      if ($lista->vincitrice) {
        return $lista;
      }
    }

    $vincitrice = $this->liste[0];
    foreach ($this->liste as $lista) {
      if ($lista->votiSindaco > $vincitrice->votiSindaco) {
        $vincitrice = $lista;
      }
    }

    return $vincitrice;
  }

  /**
   * Returns the name of the elected mayor, if any.
   *
   * @return string|null
   */
  public function getSindacoEletto(): ?string
  {
    $vincitrice = $this->getListaVincitrice();
    return $vincitrice?->nomeSindaco;
  }

  /**
   * Returns the list of candidates elected across all lists.
   *
   * @return ComunalCandidate[]
   */
  public function getCandidatiEletti(): array
  {
    $eletti = [];
    foreach ($this->liste as $lista) {
      $eletti = array_merge($eletti, $lista->getCandidatiEletti());
    }
    return $eletti;
  }

  /**
   * Computes the number of valid votes (excluding blank and null ballots).
   *
   * @return int
   */
  public function getVotiValidi(): int
  {
    return $this->votanti - $this->schedeBianche - $this->schedeNulle;
  }

  /**
   * Calculates the turnout percentage based on electors and voters.
   *
   * @return float
   */
  public function calcolaAffluenza(): float
  {
    return $this->elettori > 0 ? ($this->votanti / $this->elettori) * 100 : 0.0;
  }

  /**
   * Generates a human-readable summary of the municipal election outcome.
   *
   * @return string
   */
  public function getSummary(): string
  {
    $this->ensureAffluenza();
    if ($this->isBallottaggio()) {
      return sprintf(
        '%s: Si va al ballottaggio - Affluenza: %.2f%% (%d votanti su %d elettori)',
        $this->nomeComune,
        $this->affluenzaPercent,
        $this->votanti,
        $this->elettori
      );
    }
    
    $vincitrice = $this->getListaVincitrice();
    $sindaco = $this->getSindacoEletto();
    
    if ($vincitrice && $sindaco) {
      return sprintf(
        '%s: Sindaco eletto %s (%s) con %d voti (%.2f%%) - Affluenza: %.2f%%',
        $this->nomeComune,
        $sindaco,
        $vincitrice->nomeLista,
        $vincitrice->votiSindaco,
        $vincitrice->percentualeVoti,
        $this->affluenzaPercent
      );
    }

    return sprintf(
      '%s: Affluenza %.2f%% (%d votanti su %d elettori)',
      $this->nomeComune,
      $this->affluenzaPercent,
      $this->votanti,
      $this->elettori
    );
  }

  /**
   * Sorts lists in descending order by the votes of their mayoral candidate.
   */
  public function sortListeByVoti(): void
  {
    usort($this->liste, fn($a, $b) => $b->votiSindaco <=> $a->votiSindaco);
  }

  /**
   * Checks whether a mayor has already been elected.
  *
  * @return bool
   */
  public function hasSindacoEletto(): bool
  {
    foreach ($this->liste as $lista) {
      if ($lista->vincitrice && $lista->votiSindaco > 0) {
        return true;
      }
    }
    return false;
  }

  /**
   * Indicates whether a runoff is required (no votes yet but multiple lists).
  *
  * @return bool
   */
  public function isBallottaggio(): bool
  {
    $hasVotes = false;
    foreach ($this->liste as $lista) {
      if ($lista->votiSindaco > 0) {
        $hasVotes = true;
        break;
      }
    }
    
    return !$hasVotes && count($this->liste) > 1;
  }
}

/**
 * Describes a municipal electoral list with related candidates.
 */
class ComunalList
{
  /**
   * Official list name.
   *
   * @var string
   */
  public string $nomeLista;

  /**
   * Name of the mayor candidate supported by the list.
   *
   * @var string
   */
  public string $nomeSindaco;

  /**
   * Name of the vice mayor candidate, when available.
   *
   * @var string
   */
  public string $nomeViceSindaco;

  /**
   * Number of votes collected by the mayoral candidate.
   *
   * @var int
   */
  public int $votiSindaco;

  /**
   * Percentage of votes attributed to the mayoral candidate.
   *
   * @var float
   */
  public float $percentualeVoti;

  /**
   * URL pointing to the list symbol image.
   *
   * @var string|null
   */
  public ?string $simboloUrl;

  /**
   * Candidates belonging to the list.
   *
   * @var ComunalCandidate[]
   */
  public array $candidati;

  /**
   * Whether the list has been marked as winner.
   *
   * @var bool
   */
  public bool $vincitrice;

  /**
   * @param string $nomeLista        List name.
   * @param string $nomeSindaco      Mayor candidate name.
   * @param string $nomeViceSindaco  Vice mayor candidate name.
   */
  public function __construct(string $nomeLista, string $nomeSindaco = '', string $nomeViceSindaco = '')
  {
    $this->nomeLista = $nomeLista;
    $this->nomeSindaco = $nomeSindaco;
    $this->nomeViceSindaco = $nomeViceSindaco;
    $this->candidati = [];
    $this->votiSindaco = 0;
    $this->percentualeVoti = 0.0;
    $this->vincitrice = false;
    $this->simboloUrl = null;
  }

  /**
   * Adds a candidate to the list roster.
   *
   * @param ComunalCandidate $candidato Candidate to attach to the list.
   */
  public function addCandidato(ComunalCandidate $candidato): void
  {
    $this->candidati[] = $candidato;
  }

  /**
   * Returns only the candidates marked as elected.
   *
   * @return ComunalCandidate[]
   */
  public function getCandidatiEletti(): array
  {
    return array_filter($this->candidati, fn($candidato) => $candidato->eletto);
  }

  /**
   * Counts how many candidates in the list have been elected.
   *
   * @return int
   */
  public function getNumeroEletti(): int
  {
    return count($this->getCandidatiEletti());
  }

  /**
   * Returns the sum of preference votes for all list candidates.
   *
   * @return int
   */
  public function getTotalVotiPreferenze(): int
  {
    return array_sum(array_map(fn($candidato) => $candidato->voti, $this->candidati));
  }

  /**
   * Sorts candidates in descending order based on preference votes.
   */
  public function sortCandidatiByVoti(): void
  {
    usort($this->candidati, fn($a, $b) => $b->voti <=> $a->voti);
  }

  /**
   * Returns a concise description of the list outcome.
  *
  * @return string
   */
  public function getSummary(): string
  {
    $status = $this->vincitrice ? ' (VINCITRICE)' : '';
    return sprintf(
      '%s: Sindaco %s - %d voti (%.2f%%)%s',
      $this->nomeLista,
      $this->nomeSindaco,
      $this->votiSindaco,
      $this->percentualeVoti,
      $status
    );
  }
}
