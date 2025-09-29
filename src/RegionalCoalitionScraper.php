<?php

namespace ElectionScraperVdA;

use DOMElement;
use ElectionScraperVdA\Model\RegionalCoalition;
use ElectionScraperVdA\Model\RegionalCoalitionResult;
use ElectionScraperVdA\Model\RegionalResult;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;

/**
 * Scraper dedicated to the "Riepilogo per coalizione/programma" tables
 * available in the regional election result pages.
 */
class RegionalCoalitionScraper extends AbstractHtmlScraper
{
  /**
   * Cached payload from the standard regional scraper, used to reuse
   * list-level parsing logic and summary statistics.
   *
   * @var array<string, mixed>
   */
  private array $basePayload = [];

  public function fetch(string $url): RegionalCoalitionResult
  {
    return parent::fetch($url);
  }

  public function parseHtml(string $html): RegionalCoalitionResult
  {
    $scraper = new RegionalScraper();
    $this->basePayload = $scraper->parseHtml($html);
    return parent::parseHtml($html);
  }

  protected function doParse(): RegionalCoalitionResult
  {
    $result = new RegionalCoalitionResult($this->extractNomeElezione());
    $this->hydrateSummary($result);
    $this->importListeDettaglio($result);
    $this->extractCoalizioni($result);
    $result->sortCoalizioniByVoti();
    $result->sortListeByVoti();
    return $result;
  }

  private function extractNomeElezione(): string
  {
    $xpath = $this->xpath ?? null;
    if ($xpath) {
      $span = $xpath->query('//span[contains(@class, "titolo-elezione")]')->item(0);
      if ($span instanceof DOMElement) {
        $text = trim($span->textContent);
        if ($text !== '') {
          return $text;
        }
      }
      $header = $xpath->query('//h1[@class="testi"]')->item(0);
      if ($header instanceof DOMElement) {
        $text = trim($header->textContent);
        if ($text !== '') {
          return $text;
        }
      }
      $title = $xpath->query('//title')->item(0);
      if ($title instanceof DOMElement) {
        $text = trim($title->textContent);
        if ($text !== '') {
          return $text;
        }
      }
    }
    return 'Elezioni regionali';
  }

  private function hydrateSummary(RegionalCoalitionResult $result): void
  {
    $summary = $this->basePayload['summary'] ?? [];
    if (!is_array($summary)) {
      $summary = [];
    }

    $result->elettori = (int) ($summary['elettori'] ?? 0);
    $result->votanti = (int) ($summary['votanti'] ?? 0);
    $result->affluenzaPercent = (float) ($summary['affluenza_percent'] ?? 0.0);
    $result->schedeScrutinate = (int) ($summary['schede_scrutinate'] ?? 0);
    $result->schedeScrutinatePercent = (float) ($summary['schede_scrutinate_percent'] ?? 0.0);
    $result->schedeBianche = (int) ($summary['schede_bianche'] ?? 0);
    $result->schedeBianchePercent = (float) ($summary['schede_bianche_percent'] ?? 0.0);
    $result->schedeNulle = (int) ($summary['schede_nulle'] ?? 0);
    $result->schedeNullePercent = (float) ($summary['schede_nulle_percent'] ?? 0.0);
  }

  private function importListeDettaglio(RegionalCoalitionResult $result): void
  {
    $liste = $this->basePayload['results'] ?? [];
    if (!is_array($liste)) {
      return;
    }

    foreach ($liste as $lista) {
      if ($lista instanceof RegionalResult) {
        $result->addLista($lista);
      }
    }
  }

  private function extractCoalizioni(RegionalCoalitionResult $result): void
  {
    $tables = $this->xpath->query('//div[contains(@id, "pnlRiepilogo")]//table');
    if (!$tables) {
      return;
    }

    foreach ($tables as $table) {
      foreach ($this->xpath->query('.//tr', $table) as $row) {
        if (!$row instanceof DOMElement) {
          continue;
        }
        $class = $row->getAttribute('class');
        if ($class !== '' && stripos($class, 'voti-header') !== false) {
          continue;
        }
        $coalizione = $this->parseCoalizioneRow($row);
        if ($coalizione) {
          $result->addCoalizione($coalizione);
        }
      }
      if (!empty($result->coalizioni)) {
        return; // first valid table is the coalition summary
      }
    }
  }

  private function parseCoalizioneRow(DOMElement $row): ?RegionalCoalition
  {
    $cells = $this->xpath->query('.//td', $row);
    if ($cells->length < 4) {
      return null;
    }

    $programmaText = trim($cells->item(0)->textContent ?? '');
    $programma = $this->parseProgramNumber($programmaText);
    if ($programma === null) {
      return null;
    }

    $coalizione = new RegionalCoalition($programma);

    foreach ($this->xpath->query('.//img', $cells->item(1)) as $img) {
      if ($img instanceof DOMElement) {
        $src = trim($img->getAttribute('src'));
        if ($src !== '') {
          $coalizione->simboliUrl[] = $src;
        }
      }
    }

    if ($cells->length > 2) {
      $bar = $this->xpath->query('.//div[contains(@class, "voti-barra")]', $cells->item(2))->item(0);
      if ($bar instanceof DOMElement) {
        if (preg_match('/width\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $bar->getAttribute('style'), $m)) {
          $coalizione->barraPercentuale = (float) $m[1];
        } elseif (preg_match('/width\s*:\s*([0-9]+),(\d+)/', $bar->getAttribute('style'), $m2)) {
          $coalizione->barraPercentuale = (float) ($m2[1] . '.' . $m2[2]);
        }
      }
    }

    if ($cells->length > 3) {
      $coalizione->voti = $this->parseInt($cells->item(3)->textContent ?? '0');
    }
    if ($cells->length > 4) {
      $coalizione->percentualeVoti = $this->parseFloat($cells->item(4)->textContent ?? '0');
    }
    if ($cells->length > 5) {
      $coalizione->votiContestati = $this->parseInt($cells->item(5)->textContent ?? '0');
    }
    if ($cells->length > 6) {
      $coalizione->seggi = $this->parseInt($cells->item(6)->textContent ?? '0');
    }

    return $coalizione;
  }

  private function parseProgramNumber(string $text): ?int
  {
    if ($text === '') {
      return null;
    }
    if (preg_match('/(\d+)/', $text, $matches)) {
      return (int) $matches[1];
    }
    return null;
  }
}
