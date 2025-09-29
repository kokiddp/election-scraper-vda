<?php

namespace ElectionScraperVdA\Model;

/**
 * Aggregated regional election results grouped by coalition / programme.
 */
class RegionalCoalitionResult extends AbstractElectionResult
{
  /**
   * @var RegionalCoalition[]
   */
  public array $coalizioni = [];

  /**
   * @var RegionalResult[]
   */
  public array $liste = [];

  /**
   * Total number of scrutinised ballots reported by the summary box.
   */
  public int $schedeScrutinate = 0;

  /**
   * Percentage of scrutinised ballots over the electorate.
   */
  public float $schedeScrutinatePercent = 0.0;

  public function __construct(string $nomeElezione)
  {
    parent::__construct($nomeElezione);
  }

  public function addCoalizione(RegionalCoalition $coalizione): void
  {
    $this->coalizioni[] = $coalizione;
  }

  public function addLista(RegionalResult $lista): void
  {
    $this->liste[] = $lista;
  }

  public function getCoalizioneVincitrice(): ?RegionalCoalition
  {
    if (empty($this->coalizioni)) {
      return null;
    }

    $vincitrice = $this->coalizioni[0];
    foreach ($this->coalizioni as $coalizione) {
      if ($coalizione->voti > $vincitrice->voti) {
        $vincitrice = $coalizione;
        continue;
      }
      if ($coalizione->voti === $vincitrice->voti && $coalizione->seggi > $vincitrice->seggi) {
        $vincitrice = $coalizione;
      }
    }

    return $vincitrice;
  }

  public function sortCoalizioniByVoti(): void
  {
    usort($this->coalizioni, function (RegionalCoalition $a, RegionalCoalition $b): int {
      if ($a->voti === $b->voti) {
        return $b->seggi <=> $a->seggi;
      }
      return $b->voti <=> $a->voti;
    });
  }

  public function sortListeByVoti(): void
  {
    usort($this->liste, fn (RegionalResult $a, RegionalResult $b): int => $b->voti <=> $a->voti);
  }

  public function getSummary(): string
  {
    $this->ensureAffluenza();
    $vincitrice = $this->getCoalizioneVincitrice();
    if ($vincitrice) {
      $seggiText = $vincitrice->hasSeggi() ? sprintf(' e %d seggi', $vincitrice->seggi) : '';
      return sprintf(
        '%s: Programma %d in testa con %s voti (%.2f%%)%s - Affluenza %.2f%%',
        $this->nomeComune,
        $vincitrice->programmaNumero,
        number_format($vincitrice->voti, 0, ',', '.'),
        $vincitrice->percentualeVoti,
        $seggiText,
        $this->affluenzaPercent
      );
    }

    return sprintf(
      '%s: Affluenza %.2f%% (%s votanti su %s elettori)',
      $this->nomeComune,
      $this->affluenzaPercent,
      number_format($this->votanti, 0, ',', '.'),
      number_format($this->elettori, 0, ',', '.')
    );
  }
}

/**
 * Model representing a single coalition/programme row within the regional summary.
 */
class RegionalCoalition
{
  public int $programmaNumero;
  public array $simboliUrl = [];
  public int $voti = 0;
  public float $percentualeVoti = 0.0;
  public int $votiContestati = 0;
  public int $seggi = 0;
  public ?float $barraPercentuale = null;

  public function __construct(int $programmaNumero)
  {
    $this->programmaNumero = $programmaNumero;
  }

  public function hasSeggi(): bool
  {
    return $this->seggi > 0;
  }

  public function getSummary(): string
  {
    $seggiText = $this->hasSeggi() ? sprintf(' (%d seggi)', $this->seggi) : '';
    return sprintf(
      'Programma %d: %s voti (%.2f%%)%s',
      $this->programmaNumero,
      number_format($this->voti, 0, ',', '.'),
      $this->percentualeVoti,
      $seggiText
    );
  }
}
