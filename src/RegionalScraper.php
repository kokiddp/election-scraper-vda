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
    $results = [];
    foreach ($this->xpath->query('//tr[@class="voti-lista"]') as $row) {
      if ($r = $this->parseRow($row)) {
        $results[] = $r;
      }
    }
    return $results;
  }

  private function parseRow(\DOMElement $row): ?RegionalResult
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

  private function extractNomeLista(\DOMElement $cell): string
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
