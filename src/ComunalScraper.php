<?php

namespace ElectionScraperVdA;

use ElectionScraperVdA\Model\ComunalResult;
use ElectionScraperVdA\Model\ComunalList;
use ElectionScraperVdA\Model\ComunalCandidate;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;

/**
 * Classe responsabile del download e dell'analisi delle pagine di risultati
 * delle elezioni comunali. Consente di ottenere un oggetto ComunalResult
 * a partire da un URL remoto oppure da una stringa HTML.
 */
class ComunalScraper extends AbstractHtmlScraper
{
  /**
   * Scarica una pagina tramite l'HTTP client interno e restituisce i risultati.
   * 
   * @param string $url URL della pagina da scaricare
   * @return ComunalResult Risultato dell'elezione comunale
   */
  public function fetch(string $url): ComunalResult
  {
    return parent::fetch($url);
  }

  /**
   * Analizza direttamente una stringa HTML e restituisce i risultati.
   * 
   * @param string $html Contenuto HTML da analizzare
   * @return ComunalResult Risultato dell'elezione comunale
   */
  public function parseHtml(string $html): ComunalResult
  {
    return parent::parseHtml($html);
  }

  /**
   * {@inheritdoc}
   */
  protected function doParse(): ComunalResult
  {
    $nomeComune = $this->extractNomeComune();
    $result = new ComunalResult($nomeComune);
    $this->extractDatiGenerali($result);
    $this->extractListe($result);
    $this->identificaVincitrice($result);
    return $result;
  }

  /**
   * Estrae il nome del comune dal titolo della pagina.
   */
  private function extractNomeComune(): string
  {
    // Cerca nel titolo H1
    $h1 = $this->xpath->query('//h1[@class="testi"]')->item(0);
    if ($h1) {
      return preg_replace('/^Voti - /', '', trim($h1->textContent));
    }

    // Fallback: cerca nel riepilogo
    $riepilogo = $this->xpath->query('//div[@class="riepilogo-elezione-box"]//h1')->item(0);
    if ($riepilogo) {
      return preg_replace('/^Comune /', '', trim($riepilogo->textContent));
    }

    return 'Comune sconosciuto';
  }

  /**
   * Estrae i dati generali del comune (elettori, votanti, affluenza, ecc.).
   */
  private function extractDatiGenerali(ComunalResult $result): void
  {
    $table = $this->xpath->query('//table[@class="riepiogo-elezione"]')->item(0);
    if (!$table) {
      return;
    }

    foreach ($this->xpath->query('.//tr', $table) as $row) {
      $cells = $this->xpath->query('.//td', $row);
      if ($cells->length < 2) {
        continue;
      }

      $label = trim($cells->item(0)->textContent);
      $value = trim($cells->item(1)->textContent);
      $percentage = $cells->length > 2 ? trim($cells->item(2)->textContent) : '';

      switch ($label) {
        case 'Elettori':
          $result->elettori = $this->parseInt($value);
          break;
        case 'Votanti':
          $result->votanti = $this->parseInt($value);
          $result->affluenzaPercent = $this->parseFloat($percentage);
          break;
        case 'Schede bianche':
          $result->schedeBianche = $this->parseInt($value);
          $result->schedeBianchePercent = $this->parseFloat($percentage);
          break;
        case 'Schede nulle':
          $result->schedeNulle = $this->parseInt($value);
          $result->schedeNullePercent = $this->parseFloat($percentage);
          break;
      }
    }
  }

  /**
   * Estrae tutte le liste elettorali del comune.
   */
  private function extractListe(ComunalResult $result): void
  {
    // Trova tutte le liste
    foreach ($this->xpath->query('//div[@class="preferenze-lista"]') as $listaElement) {
      if ($lista = $this->parseLista($listaElement)) {
        $result->addLista($lista);
      }
    }
  }

  /**
   * Analizza una singola lista elettorale.
   */
  private function parseLista(\DOMElement $listaElement): ?ComunalList
  {
    // Nome della lista
    $nomeListaEl = $this->xpath->query('.//span[@class="nome-lista"]', $listaElement)->item(0);
    if (!$nomeListaEl) {
      return null;
    }
    $nomeLista = trim($nomeListaEl->textContent);
    
    $lista = new ComunalList($nomeLista);

    // Simbolo della lista
    $simbolo = $this->xpath->query('.//div[@class="logo-lista-comunali"]//img', $listaElement)->item(0);
    if ($simbolo instanceof \DOMElement) {
      $lista->simboloUrl = $simbolo->getAttribute('src');
    }

    // Candidato sindaco
    $sindacoEl = $this->xpath->query('.//div[@class="lista-comunali-sindaco"]', $listaElement)->item(0);
    if ($sindacoEl) {
      $lista->nomeSindaco = preg_replace('/^[SVC]\s*/', '', trim($sindacoEl->textContent));
    }

    // Candidato vice sindaco
    $viceEl = $this->xpath->query('.//div[@class="lista-comunali-vicesindaco"]', $listaElement)->item(0);
    if ($viceEl) {
      $lista->nomeViceSindaco = preg_replace('/^[SVC]\s*/', '', trim($viceEl->textContent));
    }

    // Voti del sindaco
    $votiSindaco = $this->xpath->query('.//span[@class="voto-sindaco"]', $listaElement)->item(0);
    if ($votiSindaco) {
      $lista->votiSindaco = $this->parseInt($votiSindaco->textContent);
    }

    // Percentuale voti
    $perc = $this->xpath->query('.//span[@class="perc-voto-sindaco"]', $listaElement)->item(0);
    if ($perc) {
      $lista->percentualeVoti = $this->parseFloat($perc->textContent);
    }

    // Candidati/consiglieri
    $this->extractCandidati($lista, $listaElement);
    
    // Una lista è vincitrice se ha voti positivi e almeno il 50% dei voti
    $lista->vincitrice = $lista->votiSindaco > 0 && $lista->percentualeVoti > 50.0;

    return $lista;
  }

  /**
   * Estrae i candidati di una lista.
   */
  private function extractCandidati(ComunalList $lista, \DOMElement $listaElement): void
  {
    foreach ($this->xpath->query('.//table[@class="lista-comunali-candidati"]//tr[@class="tabella-dati-riga-affluenza"]', $listaElement) as $row) {
      $cells = $this->xpath->query('.//td', $row);
      if ($cells->length < 2) {
        continue;
      }

      // Ruolo/elezione
      $ruoloEl = $this->xpath->query('.//span', $cells->item(0));
      $ruolo = $ruoloEl->length > 0 ? trim($ruoloEl->item(0)->textContent) : '';
      $eletto = $ruolo !== '';

      // Nome candidato
      $nomeEl = $this->xpath->query('.//span', $cells->item(1));
      if ($nomeEl->length === 0) {
        continue;
      }
      $nome = trim($nomeEl->item(0)->textContent);
      if ($nome === '') {
        continue;
      }

      // Voti di preferenza
      $voti = 0;
      if ($cells->length > 2) {
        $vEl = $this->xpath->query('.//span', $cells->item(2));
        if ($vEl->length > 0) {
          $voti = $this->parseInt($vEl->item(0)->textContent);
        }
      }

      $lista->addCandidato(new ComunalCandidate($nome, $voti, $ruolo, $eletto));
    }
  }

  /**
   * Identifica quale lista ha vinto le elezioni.
   * Se i voti del sindaco sono tutti 0, probabilmente si va al ballottaggio.
   */
  private function identificaVincitrice(ComunalResult $result): void
  {
    if (empty($result->liste)) {
      return;
    }

    // Prima prova con i voti del sindaco
    $max = max(array_map(fn($l) => $l->votiSindaco, $result->liste));
    
    if ($max > 0) {
      // Trova la lista con più voti del sindaco
      foreach ($result->liste as $lista) {
        if ($lista->votiSindaco === $max && $lista->percentualeVoti > 50.0) {
          $lista->vincitrice = true;
          break;
        }
      }
    }
  }

  /**
   * Analizza più comuni da un array di HTML e restituisce un array di risultati.
   * 
   * @param array<string> $htmlArray Array di contenuti HTML
   * @return ComunalResult[] Array di risultati comunali
   */
  public function parseMultipleHtml(array $htmlArray): array
  {
    return array_map(fn($h) => $this->parseHtml($h), $htmlArray);
  }

  /**
   * Restituisce statistiche aggregate di più comuni.
   * 
   * @param ComunalResult[] $results Array di risultati comunali
   * @return array<string, mixed> Statistiche aggregate
   */
  public function getStatisticsAggregate(array $results): array
  {
    $totalElettori = array_sum(array_map(fn($r) => $r->elettori, $results));
    $totalVotanti = array_sum(array_map(fn($r) => $r->votanti, $results));
    $totalListe = array_sum(array_map(fn($r) => count($r->liste), $results));
    $affluenzaMedia = $totalElettori > 0 ? ($totalVotanti / $totalElettori) * 100 : 0;
    return [
      'num_comuni' => count($results),
      'total_elettori' => $totalElettori,
      'total_votanti' => $totalVotanti,
      'affluenza_media' => $affluenzaMedia,
      'total_liste' => $totalListe,
      'media_liste_per_comune' => count($results) ? $totalListe / count($results) : 0
    ];
  }
}
