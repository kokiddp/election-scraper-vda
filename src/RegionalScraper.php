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
    $cells = $this->xpath->query('.//td', $row);
    if ($cells->length < 5) {
      return null;
    }
    $simboloUrl = null;
    $img = $this->xpath->query('.//img', $cells->item(0))->item(0);
    if ($img instanceof \DOMElement) {
      $simboloUrl = $img->getAttribute('src');
    }
    $nomeLista = $this->extractNomeLista($cells->item(1));
    if (trim($nomeLista)==='') {
      return null;
    }
    $voti = $this->parseInt($cells->item(2)->textContent);
    $percent = $this->parseFloat($cells->item(3)->textContent);
    $contestati = $this->parseInt($cells->item(4)->textContent);
    $seggi = 0;
    if ($cells->length>5) {
      $seggiTxt = trim($cells->item(5)->textContent);
      $seggi = $seggiTxt==='-'?0:$this->parseInt($seggiTxt);
    }
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
