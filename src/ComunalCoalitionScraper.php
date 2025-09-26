<?php

namespace ElectionScraperVdA;

use ElectionScraperVdA\Model\ComunalCoalitionResult;
use ElectionScraperVdA\Model\ComunalCoalition;
use ElectionScraperVdA\Model\ComunalListDetail;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;
use DOMElement; use DOMNode;

class ComunalCoalitionScraper extends AbstractHtmlScraper
{
  public function fetch(string $url): ComunalCoalitionResult
  {
    return parent::fetch($url);
  }

  public function parseHtml(string $html): ComunalCoalitionResult
  {
    return parent::parseHtml($html);
  }

  protected function doParse(): ComunalCoalitionResult
  {
    $nomeComune = $this->extractNomeComune();
    $result = new ComunalCoalitionResult($nomeComune);
    $this->extractDatiGenerali($result);
    $this->extractCoalizioni($result);
    $this->extractListeDettaglio($result);
    $result->sortCoalizioniByVoti();
    $result->sortListeDettaglioByVoti();
    return $result;
  }

  private function extractNomeComune(): string
  {
    $title = $this->xpath->query('//title')->item(0);
    if ($title) {
      $t = trim($title->textContent);
      if (preg_match('/Elezioni\s+([A-ZÀ-Ú][^0-9]+)\s+\d{4}/u', $t, $m)) return trim($m[1]);
    }
    return 'Comune sconosciuto';
  }

  private function extractDatiGenerali(ComunalCoalitionResult $result): void
  {
    foreach ($this->xpath->query('//table') as $table) {
      foreach ($this->xpath->query('.//tr', $table) as $row) {
        $cells = $this->xpath->query('.//td', $row); if ($cells->length <2) continue;
        $label = trim($cells->item(0)->textContent); $value = trim($cells->item(1)->textContent);
        $third = $cells->length>2 ? trim($cells->item(2)->textContent) : '';
        switch(true){
          case stripos($label,'elettori')!==false: $result->elettori = $this->parseInt($value); break;
          case stripos($label,'votanti')!==false: $result->votanti = $this->parseInt($value); $result->affluenzaPercent = $this->parseFloat($third); break;
          case stripos($label,'bianche')!==false: $result->schedeBianche = $this->parseInt($value); $result->schedeBianchePercent = $this->parseFloat($third); break;
          case stripos($label,'nulle')!==false: $result->schedeNulle = $this->parseInt($value); $result->schedeNullePercent = $this->parseFloat($third); break;
        }
      }
    }
    if ($result->affluenzaPercent==0 && $result->elettori>0) $result->affluenzaPercent = ($result->votanti/$result->elettori)*100;
  }

  private function extractCoalizioni(ComunalCoalitionResult $result): void
  {
    foreach ($this->xpath->query('//table') as $table) {
      foreach ($this->xpath->query('.//tr', $table) as $row) {
        $cells = $this->xpath->query('.//td', $row); if ($cells->length<6) continue;
        $secondHTML = $this->innerHTML($cells->item(1));
        if (strpos($secondHTML,'<br')===false && strpos($secondHTML,"\n")===false) continue;
        if ($coal = $this->parseCoalizioneRow($cells)) $result->addCoalizione($coal);
      }
      if (!empty($result->coalizioni)) return; // stop after first valid table
    }
  }

  private function parseCoalizioneRow(\DOMNodeList $cells): ?ComunalCoalition
  {
    $names = $this->parseCandidateNames($cells->item(1)->textContent);
    if (empty($names['sindaco'])) return null;
    $coal = new ComunalCoalition($names['sindaco'], $names['vice_sindaco']);
    foreach ($this->xpath->query('.//img', $cells->item(0)) as $img) {
      if ($img instanceof DOMElement) {
        $coal->simboliUrl[] = $img->getAttribute('src');
      }
    }
    if ($cells->length>2) {
      $coal->votiEsclusivi = $this->parseInt($cells->item(2)->textContent);
    }
    if ($cells->length>3) {
      $coal->votiLista = $this->parseInt($cells->item(3)->textContent);
    }
    if ($cells->length>4) {
      $coal->votiTotali = $this->parseInt($cells->item(4)->textContent);
    }
    if ($cells->length>5) {
      $coal->percentualeVoti = $this->parseFloat($cells->item(5)->textContent);
    }
    if ($cells->length>6) {
      $coal->votiContestati = $this->parseInt($cells->item(6)->textContent);
    }
    if ($cells->length>7) {
      $coal->seggi = $this->parseInt($cells->item(7)->textContent);
    }
    return $coal;
  }

  private function parseCandidateNames(string $text): array
  {
    $lines = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+|<br\s*\/?>/i', trim($text)))));
    $res=['sindaco'=>'','vice_sindaco'=>''];
    if(count($lines)>=2){
      $res['sindaco']=$lines[0];
      $res['vice_sindaco']=$lines[1];
    } elseif(count($lines)==1){
      if(preg_match('/^([^\/\-]+)[\s]*[\/\-][\s]*(.+)$/',$lines[0],$m)){
        $res['sindaco']=trim($m[1]);
        $res['vice_sindaco']=trim($m[2]);
      } else {
        $res['sindaco']=$lines[0];
      }
    }
    return $res;
  }

  private function extractListeDettaglio(ComunalCoalitionResult $result): void
  {
    foreach ($this->xpath->query("//tr[contains(@class,'voti-lista')]") as $row) {
      $cells = $this->xpath->query('.//td', $row);
      if ($cells->length<4) {
        continue;
      }
      if ($lista = $this->parseListaDettaglioRow($cells)){
        $result->addListaDettaglio($lista);
      }
    }
  }

  private function parseListaDettaglioRow(\DOMNodeList $cells): ?ComunalListDetail
  {
    $nome = trim(strip_tags($cells->item(1)->textContent));
    if ($nome==='') {
      return null;
    }
    $lista = new ComunalListDetail($nome);
    $simbolo = $this->xpath->query('.//img', $cells->item(0))->item(0);
    if ($simbolo instanceof DOMElement) {
      $lista->simboloUrl = $simbolo->getAttribute('src');
    }
    $prefLink = $this->xpath->query('.//a', $cells->item($cells->length-1))->item(0);
    if ($prefLink instanceof DOMElement) {
      $lista->preferencesUrl = $prefLink->getAttribute('href');
    }
    if ($cells->length>2) {
      $lista->voti = $this->parseInt($cells->item(2)->textContent);
    }
    if ($cells->length>3) {
      $lista->percentualeVoti = $this->parseFloat($cells->item(3)->textContent);
    }
    if ($cells->length>4) {
      $lista->votiContestati = $this->parseInt($cells->item(4)->textContent);
    }
    if ($cells->length>5) {
      $seggiTxt = trim($cells->item(5)->textContent);
      $lista->seggi = ($seggiTxt==='-')?0:$this->parseInt($seggiTxt);
    }
    return $lista;
  }
}
