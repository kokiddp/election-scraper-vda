<?php

namespace ElectionScraperVdA\Model;

/**
 * Class representing the aggregated results by coalition in the municipal elections.
 * This format presents the data grouped by mayoral candidate with the related lists.
 */
class ComunalCoalitionResult extends AbstractElectionResult
{
    /**
     * Array of coalitions.
     * 
     * @var ComunalCoalition[]
     */
    public array $coalizioni;

    /**
     * Array of detailed lists.
     * 
     * @var ComunalListDetail[]
     */
    public array $listeDettaglio;

    /**
     * Constructor for ComunalCoalitionResult.
     *
     * @param string $nomeComune Municipality name associated with the result.
     */
    public function __construct(string $nomeComune)
    {
      parent::__construct($nomeComune);
      $this->coalizioni = [];
      $this->listeDettaglio = [];
    }

    /**
     * Adds a coalition to the results.
     *
     * @param ComunalCoalition $coalizione Coalition to register.
     */
    public function addCoalizione(ComunalCoalition $coalizione): void
    {
      $this->coalizioni[] = $coalizione;
    }

    /**
     * Adds a detailed list to the results.
     *
     * @param ComunalListDetail $lista Detailed list entry describing a single list.
     */
    public function addListaDettaglio(ComunalListDetail $lista): void
    {
      $this->listeDettaglio[] = $lista;
    }

    /**
     * Returns the total number of valid votes (excluding blank and null votes).
     *
     * @return int
     */
    public function getVotiValidi(): int
    {
      return $this->votanti - $this->schedeBianche - $this->schedeNulle;
    }

    /**
     * Returns the winning coalition (the one with the most votes).
     *
     * @return ComunalCoalition|null
     */
    public function getCoalizioneVincitrice(): ?ComunalCoalition
    {
      if (empty($this->coalizioni)) {
        return null;
      }

      $vincitrice = $this->coalizioni[0];
      foreach ($this->coalizioni as $coalizione) {
        if ($coalizione->votiTotali > $vincitrice->votiTotali) {
          $vincitrice = $coalizione;
        }
      }

      return $vincitrice;
    }

  /**
   * Returns a textual representation of the result.
   *
   * @return string
   */
  public function getSummary(): string
  {
    $this->ensureAffluenza();
    $vincitrice = $this->getCoalizioneVincitrice();
    
    if ($vincitrice) {
      return sprintf(
        '%s: Coalizione vincitrice %s (%s) con %d voti (%.2f%%) - Affluenza: %.2f%%',
        $this->nomeComune,
        $vincitrice->sindaco,
        $vincitrice->viceSindaco ? $vincitrice->sindaco . '/' . $vincitrice->viceSindaco : $vincitrice->sindaco,
        $vincitrice->votiTotali,
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
   * Sorts the coalitions by number of votes (descending).
   */
  public function sortCoalizioniByVoti(): void
  {
    usort($this->coalizioni, fn($a, $b) => $b->votiTotali <=> $a->votiTotali);
  }

  /**
   * Sorts the detailed lists by number of votes (descending).
   */
  public function sortListeDettaglioByVoti(): void
  {
    usort($this->listeDettaglio, fn($a, $b) => $b->voti <=> $a->voti);
  }
}

/**
 * Classe che rappresenta una coalizione/programma comune nelle elezioni comunali.
 * Contiene i dati del sindaco, vice sindaco e i voti totali della coalizione.
 */
class ComunalCoalition
{
    /** @var string Nome del candidato sindaco. */
    public string $sindaco;

    /** @var string Nome del candidato vice sindaco. */
    public string $viceSindaco;

    /** @var int Voti esclusivamente al sindaco e vice sindaco. */
    public int $votiEsclusivi;

    /** @var int Voti totali di lista. */
    public int $votiLista;

    /** @var int Voti totali della coalizione. */
    public int $votiTotali;

    /** @var float Percentuale di voti ottenuti. */
    public float $percentualeVoti;

    /** @var int Voti contestati. */
    public int $votiContestati;

    /** @var int Numero di seggi ottenuti. */
    public int $seggi;

    /** @var string[] URL delle immagini dei simboli delle liste. */
    public array $simboliUrl;

  /**
   * @param string $sindaco     Nome del candidato sindaco.
   * @param string $viceSindaco Nome del candidato vice sindaco.
   */
  public function __construct(string $sindaco, string $viceSindaco = '')
  {
    $this->sindaco = $sindaco;
    $this->viceSindaco = $viceSindaco;
    $this->votiEsclusivi = 0;
    $this->votiLista = 0;
    $this->votiTotali = 0;
    $this->percentualeVoti = 0.0;
    $this->votiContestati = 0;
    $this->seggi = 0;
    $this->simboliUrl = [];
  }

  /**
   * Verifica se la coalizione ha ottenuto seggi.
   *
   * @return bool
   */
    public function hasSeggi(): bool
    {
        return $this->seggi > 0;
    }

    /**
   * Restituisce una rappresentazione testuale della coalizione.
   *
   * @return string
     */
    public function getSummary(): string
    {
        $seggiText = $this->seggi > 0 ? " ({$this->seggi} seggi)" : '';
        return sprintf(
            '%s/%s: %s voti (%.2f%%)%s',
            $this->sindaco,
            $this->viceSindaco,
            number_format($this->votiTotali),
            $this->percentualeVoti,
            $seggiText
        );
    }
}

/**
 * Class representing the details of a single municipal electoral list.
 */
class ComunalListDetail
{
  /**
   * The name of the electoral list.
   * 
   * @var string
   */
  public string $nomeLista;

  /**
   * The number of votes obtained by the list.
   *
   * @var int
   */
  public int $voti;

  /**
   * Percentage of votes obtained.
   *
   * @var float
   */
  public float $percentualeVoti;

  /**
   * The number of contested votes.
   *
   * @var int
   */
  public int $votiContestati;

  /**
   * The number of seats obtained.
   *
   * @var int
   */
  public int $seggi;

  /**
   * The URL of the list symbol image.
   *
   * @var string|null
   */
  public ?string $simboloUrl;

  /**
   * The URL to view the preferences of the list.
   *
   * @var string|null
   */
  public ?string $preferencesUrl;

  /**
   * The constructor for the ComunalListDetail class.
   *
   * @param string $nomeLista The name of the electoral list.
   */
  public function __construct(string $nomeLista)
  {
    $this->nomeLista = $nomeLista;
    $this->voti = 0;
    $this->percentualeVoti = 0.0;
    $this->votiContestati = 0;
    $this->seggi = 0;
    $this->simboloUrl = null;
    $this->preferencesUrl = null;
  }

  /**
   * Checks if the list has obtained seats.
   *
   * @return bool
   */
  public function hasSeggi(): bool
  {
    return $this->seggi > 0;
  }

  /**
   * Returns the formatted votes.
   *
   * @return string
   */
  public function getFormattedVotes(): string
  {
    return number_format($this->voti);
  }

  /**
   * Returns the formatted percentage.
   *
   * @return string
   */
  public function getFormattedPercentage(): string
  {
    return number_format($this->percentualeVoti, 2) . '%';
  }

  /**
   * Returns a summary representation of the list.
   *
   * @return string
   */
  public function getSummary(): string
  {
    $seggiText = $this->seggi > 0 ? " ({$this->seggi} seggi)" : '';
    return sprintf(
      '%s: %s voti (%.2f%%)%s',
      $this->nomeLista,
      $this->getFormattedVotes(),
      $this->percentualeVoti,
      $seggiText
    );
  }
}
