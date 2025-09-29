<?php

namespace ElectionScraperVdA;

use ElectionScraperVdA\Model\RegionalResult;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;

class RegionalScraper extends AbstractHtmlScraper
{
  public function fetch(string $url): array
  {
    return parent::fetch($url);
  }

  public function parseHtml(string $html): array
  {
    return parent::parseHtml($html);
  }

  protected function doParse(): array
  {
    $summary = $this->extractSummaryStats();
    $results = [];
    foreach ($this->xpath->query('//tr[@class="voti-lista"]') as $row) {
      if ($r = $this->parseRow($row)) {
        $results[] = $r;
      }
    }

    return [
      'results' => $results,
      'summary' => $summary,
    ];
  }

  protected function extractSummaryStats(): array
  {
    $summary = [
      'elettori' => 0,
      'votanti' => 0,
      'affluenza_percent' => 0.0,
      'schede_scrutinate' => 0,
      'schede_scrutinate_percent' => 0.0,
      'schede_bianche' => 0,
      'schede_bianche_percent' => 0.0,
      'schede_nulle' => 0,
      'schede_nulle_percent' => 0.0,
    ];

    foreach ($this->xpath->query('//table') as $table) {
      foreach ($this->xpath->query('.//tr', $table) as $row) {
        $cells = $this->xpath->query('.//td', $row);
        if ($cells->length < 2) {
          continue;
        }

        $label = trim($cells->item(0)->textContent);
        $value = trim($cells->item(1)->textContent);
        $percentage = $cells->length > 2 ? trim($cells->item(2)->textContent) : '';
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);

        if ($normalized === '') {
          continue;
        }

        if (str_contains($normalized, 'elettor') && $summary['elettori'] === 0) {
          $summary['elettori'] = $this->parseInt($value);
          continue;
        }

        if (str_contains($normalized, 'votanti') && $summary['votanti'] === 0) {
          $summary['votanti'] = $this->parseInt($value);
          $summary['affluenza_percent'] = $this->parseFloat($percentage);
          continue;
        }

        if (str_contains($normalized, 'scrutinate') && $summary['schede_scrutinate'] === 0) {
          $summary['schede_scrutinate'] = $this->parseInt($value);
          $summary['schede_scrutinate_percent'] = $this->parseFloat($percentage);
          continue;
        }

        if (str_contains($normalized, 'bianch') && $summary['schede_bianche'] === 0) {
          $summary['schede_bianche'] = $this->parseInt($value);
          $summary['schede_bianche_percent'] = $this->parseFloat($percentage);
          continue;
        }

        if (str_contains($normalized, 'null') && $summary['schede_nulle'] === 0) {
          $summary['schede_nulle'] = $this->parseInt($value);
          $summary['schede_nulle_percent'] = $this->parseFloat($percentage);
          continue;
        }
      }
    }

    if ($summary['schede_scrutinate'] === 0) {
      $summary['schede_scrutinate'] = $summary['votanti'];
    }

    if ($summary['schede_scrutinate_percent'] === 0.0 && $summary['elettori'] > 0) {
      $summary['schede_scrutinate_percent'] = ($summary['schede_scrutinate'] / $summary['elettori']) * 100;
    }

    if ($summary['schede_bianche_percent'] === 0.0 && $summary['schede_scrutinate'] > 0 && $summary['schede_bianche'] > 0) {
      $summary['schede_bianche_percent'] = ($summary['schede_bianche'] / $summary['schede_scrutinate']) * 100;
    }

    if ($summary['schede_nulle_percent'] === 0.0 && $summary['schede_scrutinate'] > 0 && $summary['schede_nulle'] > 0) {
      $summary['schede_nulle_percent'] = ($summary['schede_nulle'] / $summary['schede_scrutinate']) * 100;
    }

    if ($summary['affluenza_percent'] === 0.0 && $summary['elettori'] > 0 && $summary['votanti'] > 0) {
      $summary['affluenza_percent'] = ($summary['votanti'] / $summary['elettori']) * 100;
    }

    return $summary;
  }

  protected function parseRow(\DOMElement $row): ?RegionalResult
  {
    $nodeList = $this->xpath->query('.//td', $row);
    if (!$nodeList) {
      return null;
    }

    $cells = [];
    foreach ($nodeList as $cell) {
      if ($cell instanceof \DOMElement) {
        $cells[] = $cell;
      }
    }

    while (!empty($cells)) {
      $last = $cells[count($cells) - 1];
      $text = trim($last->textContent);
      $hasImage = $this->xpath->query('.//img', $last)->length > 0;
      if ($text === '' && !$hasImage) {
        array_pop($cells);
        continue;
      }
      break;
    }

    if (count($cells) < 4) {
      return null;
    }

    $imageCell = $cells[0];
    $nameCell = $cells[1];

    $dataCells = [];
    foreach ($cells as $cell) {
      $class = $cell->getAttribute('class');
      if ($class !== '' && strpos($class, 'voti-dato') !== false) {
        $dataCells[] = $cell;
      }
    }

    if (count($dataCells) < 4) {
      return null;
    }

    $votesCell = $dataCells[0];
    $percentCell = $dataCells[1];
    $contestatiCell = $dataCells[2];
    $seatsCell = $dataCells[3];

    $simboloUrl = null;
    $img = $this->xpath->query('.//img', $imageCell)->item(0);
    if ($img instanceof \DOMElement) {
      $simboloUrl = $img->getAttribute('src');
    }
    $nomeLista = $this->extractNomeLista($nameCell);
    if (trim($nomeLista)==='') {
      return null;
    }
    $voti = $this->parseInt($votesCell->textContent);
    $percent = $this->parseFloat($percentCell->textContent);
    $contestati = $this->parseInt($contestatiCell->textContent);
    $seggiTxt = trim($seatsCell->textContent);
    $seggi = $seggiTxt==='-'?0:$this->parseInt($seggiTxt);
    return RegionalResult::create($nomeLista, $voti, $percent, $contestati, $seggi, $simboloUrl);
  }

  protected function extractNomeLista(\DOMElement $cell): string
  {
    $div = $this->xpath->query('.//div[contains(@style, "font-size")]', $cell)->item(0);
    if ($div) {
      $n = trim($div->textContent);
      if ($n!=='') {
        return $n;
      }
    }
    return trim($cell->textContent);
  }

  public function getTotalVotes(array $results): int
  {
    return array_sum(array_map(fn($r)=>$r->voti, $results));
  }

  public function getTotalSeats(array $results): int
  {
    return array_sum(array_map(fn($r)=>$r->seggi, $results));
  }

  public function getListsWithSeats(array $results): array
  {
    return array_filter($results, fn($r)=>$r->hasSeats());
  }

  public function sortByVotes(array $results): array
  {
    usort($results, fn($a,$b)=>$a->compareByVotes($b));
    return $results;
  }

  public function sortBySeats(array $results): array
  {
    usort($results, fn($a,$b)=>$a->compareBySeats($b));
    return $results;
  }
}
