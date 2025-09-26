<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ElectionScraperVdA\ComunalScraper;
use ElectionScraperVdA\ComunalCoalitionScraper;
use ElectionScraperVdA\BallottaggioScraper;
use ElectionScraperVdA\RegionalScraper;
use ElectionScraperVdA\Model\ComunalResult;
use ElectionScraperVdA\Model\ComunalCoalitionResult;
use ElectionScraperVdA\Model\BallottaggioResult;
use ElectionScraperVdA\Model\RegionalResult;
use GuzzleHttp\Client;

// Mapping statico URL -> tipo scraper richiesto (COMUNALI, BALLOTTAGGIO, REGIONALI)
$urlMap = [
  'https://www.regione.vda.it/amministrazione/Elezioni/Dati_e_risultati/elezioni/VotiComunaliAosta_i.aspx?idele=146&ord=1&setcar=n&idcom=3' => 'COMUNALI_COALIZIONE',
  'https://www.regione.vda.it/amministrazione/Elezioni/Dati_e_risultati/elezioni/VotiLista_i.aspx?idele=154&ord=1&setcar=n&idcom=3' => 'BALLOTTAGGIO',
  'https://www.regione.vda.it/amministrazione/Elezioni/Dati_e_risultati/elezioni/VotiLista_i.aspx?idele=142&ord=1&setcar=n' => 'REGIONALI',
  'https://www.regione.vda.it/amministrazione/Elezioni/Dati_e_risultati/elezioni/VotiComunali_i.aspx?idele=147&ord=1&setcar=n&idcom=22' => 'COMUNALI',
];

$client = new Client(['verify' => false, 'headers' => ['User-Agent' => 'ElectionScraperVdA/Script']]);

$out = [];
// Helper sanitize defined early for single JSON names
$sanitize = function(string $s) {
  return preg_replace('/[^a-z0-9_\-\.]/i', '_', strtolower($s));
};
// Directories
$examplesDir = __DIR__ . '/../examples';
$reportsDir = $examplesDir . '/reports';
if (!is_dir($reportsDir)) { mkdir($reportsDir, 0755, true); }
// Base URL per completare i path dei simboli
$symbolBase = 'https://www.regione.vda.it/amministrazione/Elezioni/Dati_e_risultati/elezioni/';

foreach ($urlMap as $url => $tipo) {
  try {
    $resp = $client->get($url);
    $html = (string)$resp->getBody();
    // Save to examples for inspection
    $fname = __DIR__ . '/../examples/' . preg_replace('/[^a-z0-9]+/i', '_', parse_url($url, PHP_URL_PATH) . '_' . parse_url($url, PHP_URL_QUERY)) . '.html';
    file_put_contents($fname, $html);

    // Choose scraper by explicit mapping
    $scraper = match ($tipo) {
      'COMUNALI' => new ComunalScraper(),
      'COMUNALI_COALIZIONE' => new ComunalCoalitionScraper(),
      'BALLOTTAGGIO' => new BallottaggioScraper(),
      'REGIONALI' => new RegionalScraper(),
      default => new ComunalScraper(),
    };

    $result = $scraper->parseHtml($html);

    // Convert to array by serializing public properties recursively
    $serialized = json_decode(json_encode($result), true);

    $metaRegional = null;
    if ($tipo === 'REGIONALI') {
      // parse minimal stats from HTML (elettori, votanti, affluenza, bianche, nulle)
      $metaRegional = [];
      if (preg_match('/Elettori\s*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $m)) { $metaRegional['elettori'] = (int)preg_replace('/[^0-9]/','',$m[1]); }
      if (preg_match('/Votanti\s*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $m)) { $metaRegional['votanti'] = (int)preg_replace('/[^0-9]/','',$m[1]); }
      if (preg_match('/Votanti[\s\S]*?<td class="dato">\s*([0-9,.]+)%/i', $html, $m)) { $metaRegional['affluenzaPercent'] = (float)str_replace(',','.', $m[1]); }
      if (preg_match('/Schede bianche\s*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $m)) { $metaRegional['schedeBianche'] = (int)preg_replace('/[^0-9]/','',$m[1]); }
      if (preg_match('/Schede bianche[\s\S]*?<td class="dato">\s*([0-9,.]+)%/i', $html, $m)) { $metaRegional['schedeBianchePercent'] = (float)str_replace(',','.', $m[1]); }
      if (preg_match('/Schede nulle\s*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $m)) { $metaRegional['schedeNulle'] = (int)preg_replace('/[^0-9]/','',$m[1]); }
      if (preg_match('/Schede nulle[\s\S]*?<td class="dato">\s*([0-9,.]+)%/i', $html, $m)) { $metaRegional['schedeNullePercent'] = (float)str_replace(',','.', $m[1]); }
    }

    $out[] = [
      'url' => $url,
      'tipo' => $tipo,
      'parsed' => $serialized,
      'meta' => $metaRegional,
    ];

    // Save per-URL JSON
    $singleJsonName = $sanitize(parse_url($url, PHP_URL_PATH) . '_' . parse_url($url, PHP_URL_QUERY)) . '.json';
    $singleJsonPath = __DIR__ . '/../examples/' . $singleJsonName;
    file_put_contents($singleJsonPath, json_encode($serialized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  } catch (Throwable $e) {
    $out[] = ['url' => $url, 'tipo' => $tipo, 'error' => $e->getMessage()];
  }
}

// Save aggregated JSON results
$jsonOutPath = $examplesDir . '/parsed_output.json';
file_put_contents($jsonOutPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Report generator helpers
function jsArray(array $arr): string { return json_encode($arr, JSON_UNESCAPED_UNICODE); }

// Build a small HTML legend for charts showing logo + name when available
function buildChartLegend(array $items, string $labelKey, string $symbolKey = 'simboloUrl', string $symbolBase = ''): string {
  $html = '<div class="chart-legend">';
  foreach ($items as $it) {
    $label = '';
    if (is_array($it)) {
      $label = $it[$labelKey] ?? '';
    } else {
      $label = (string)$it;
    }
    $imgHtml = '';
    if (is_array($it) && isset($it[$symbolKey]) && !empty($it[$symbolKey])) {
      $sym = $it[$symbolKey];
      if (is_array($sym)) {
        $sym = $sym[0] ?? '';
      }
      if ($sym) {
        $urlFull = str_starts_with($sym, 'http') ? $sym : ($symbolBase . $sym);
        $imgHtml = '<img src="'.htmlspecialchars($urlFull).'" alt=""/>';
      }
    }
    $html .= '<div>' . $imgHtml . '<span>' . htmlspecialchars((string)$label) . '</span></div>';
  }
  $html .= '</div>';
  return $html;
}

foreach ($out as $entry) {
  $url = $entry['url'];
  $tipo = $entry['tipo'];
  $parsed = $entry['parsed'];
  $name = $sanitize(parse_url($url, PHP_URL_PATH) . '_' . parse_url($url, PHP_URL_QUERY));
  $fname = $reportsDir . '/' . $name . '.html';
  $dataJson = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

  $sections = '';
  $chartsJs = '';
  $symbolLegends = '';
  $chartLegend = '';

  // Common stats extraction when object-like
  $aff = '';
  $kpi = '';
  $meta = $entry['meta'] ?? null;
  if (is_array($parsed) && isset($parsed['elettori'])) {
    $aff = number_format($parsed['affluenzaPercent'] ?? 0, 2, ',', '.');
    $kpi = '<div class="kpi">'
      . '<div><strong>Elettori:</strong> '.($parsed['elettori']??0).'</div>'
      . '<div><strong>Votanti:</strong> '.($parsed['votanti']??0).'</div>'
      . '<div><strong>Affluenza:</strong> '.$aff.'%</div>'
      . '<div><strong>Bianche:</strong> '.($parsed['schedeBianche']??0).' ('.number_format($parsed['schedeBianchePercent']??0,2,',','.').'%)</div>'
      . '<div><strong>Nulle:</strong> '.($parsed['schedeNulle']??0).' ('.number_format($parsed['schedeNullePercent']??0,2,',','.').'%)</div>'
      . '</div>';
  } elseif ($tipo === 'REGIONALI' && is_array($meta) && isset($meta['elettori'])) {
    $aff = number_format($meta['affluenzaPercent'] ?? 0, 2, ',', '.');
    $kpi = '<div class="kpi">'
      . '<div><strong>Elettori:</strong> '.($meta['elettori']??0).'</div>'
      . '<div><strong>Votanti:</strong> '.($meta['votanti']??0).'</div>'
      . '<div><strong>Affluenza:</strong> '.$aff.'%</div>'
      . '<div><strong>Bianche:</strong> '.($meta['schedeBianche']??0).' ('.number_format($meta['schedeBianchePercent']??0,2,',','.').'%)</div>'
      . '<div><strong>Nulle:</strong> '.($meta['schedeNulle']??0).' ('.number_format($meta['schedeNullePercent']??0,2,',','.').'%)</div>'
      . '</div>';
  }

  if ($tipo === 'COMUNALI' && is_array($parsed)) {
    // Sindaco eletto / ballottaggio
    $sindacoInfo = 'Ballottaggio o non determinato';
    if (!empty($parsed['liste'])) {
      foreach ($parsed['liste'] as $l) {
        if (!empty($l['vincitrice']) && !empty($l['nomeSindaco'])) {
          $sindacoInfo = 'Sindaco eletto: ' . htmlspecialchars($l['nomeSindaco']);
          if (!empty($l['nomeViceSindaco'])) { $sindacoInfo .= ' — Vice: ' . htmlspecialchars($l['nomeViceSindaco']); }
          break;
        }
      }
    }
    $sections .= '<h2>Esito Sindaco</h2><p>'.$sindacoInfo.'</p>';
    // Consiglieri eletti
    $consiglieri = [];
    if (!empty($parsed['liste'])) {
      foreach ($parsed['liste'] as $l) {
        if (!empty($l['candidati'])) {
          foreach ($l['candidati'] as $c) {
            if (!empty($c['eletto'])) { $consiglieri[] = $c['nome']; }
          }
        }
      }
    }
    $sections .= '<h2>Consiglieri eletti</h2><ul>' . implode('', array_map(fn($n)=>'<li>'.htmlspecialchars($n).'</li>', $consiglieri)) . '</ul>';
    // Grafici liste (voti e percentuali)
    $labels = $voti = $perc = [];
    if (!empty($parsed['liste'])) {
      foreach ($parsed['liste'] as $l) {
        $labels[] = $l['nomeLista'] ?? 'Lista';
        $voti[] = (int)($l['votiSindaco'] ?? 0);
        $perc[] = (float)($l['percentualeVoti'] ?? 0);
      }
    }
    $chartsJs .= "(function(){var ctx1=document.getElementById('chart_voti_$name').getContext('2d');new Chart(ctx1,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'Voti Sindaco',data:".jsArray($voti).",backgroundColor:'rgba(54,162,235,0.6)'}]},options:{responsive:true}});var ctx2=document.getElementById('chart_perc_$name').getContext('2d');new Chart(ctx2,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'% Voti Sindaco',data:".jsArray($perc).",backgroundColor:'rgba(75,192,192,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,suggestedMax:100}}}});})();";
    $sections .= '<h2>Distribuzione voti per lista</h2><canvas id="chart_voti_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_perc_'.$name.'"></canvas>';
    // Simboli liste
    if (!empty($parsed['liste'])) {
      $symbolLegends .= '<h2>Simboli Liste</h2><div class="symbols">';
      foreach ($parsed['liste'] as $l) {
        if (!empty($l['simboloUrl'])) {
          $urlFull = (str_starts_with($l['simboloUrl'],'http'))? $l['simboloUrl'] : $symbolBase.$l['simboloUrl'];
          $symbolLegends .= '<div><img src="'.htmlspecialchars($urlFull).'" alt=""/><span>'.htmlspecialchars($l['nomeLista'] ?? 'Lista').'</span></div>';
        }
      }
      $symbolLegends .= '</div>';
  // Chart legend logos+name for lists
  $chartLegend .= buildChartLegend($parsed['liste'], 'nomeLista', 'simboloUrl', $symbolBase);
    }
  } elseif ($tipo === 'REGIONALI' && is_array($parsed)) {
    // parsed è una lista di liste regionali
    $labels = $voti = $perc = $seggi = [];
    $labelsSeats = $seggiSeats = [];
    foreach ($parsed as $r) {
      if (!is_array($r) || !isset($r['nomeLista'])) continue;
      $labels[] = $r['nomeLista'];
      $voti[] = (int)$r['voti'];
      $perc[] = (float)$r['percentuale'];
      $seggi[] = (int)$r['seggi'];
      if (($r['seggi'] ?? 0) > 0) {
        $labelsSeats[] = $r['nomeLista'];
        $seggiSeats[] = (int)$r['seggi'];
      }
    }
    $sections .= '<h2>Liste Regionali</h2>';
    $chartsJs .= "(function(){var c1=document.getElementById('chart_voti_$name').getContext('2d');new Chart(c1,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'Voti',data:".jsArray($voti).",backgroundColor:'rgba(255,159,64,0.6)'}]},options:{responsive:true}});var c2=document.getElementById('chart_perc_$name').getContext('2d');new Chart(c2,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'% Voti',data:".jsArray($perc).",backgroundColor:'rgba(153,102,255,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,suggestedMax:100}}}});var c3=document.getElementById('chart_seggi_$name').getContext('2d');new Chart(c3,{type:'pie',data:{labels:".jsArray($labels).",datasets:[{label:'Seggi',data:".jsArray($seggi).",backgroundColor:".jsArray(array_map(fn($i)=>'hsl('.($i*37%360).',65%,60%)', array_keys($labels)))."}]},options:{responsive:true}});var c4=document.getElementById('chart_seggi_bar_$name').getContext('2d');new Chart(c4,{type:'bar',data:{labels:".jsArray($labelsSeats).",datasets:[{label:'Seggi',data:".jsArray($seggiSeats).",backgroundColor:'rgba(40,167,69,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,precision:0}}}});})();";
    $sections .= '<h2>Grafici</h2><canvas id="chart_voti_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_perc_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_seggi_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_seggi_bar_'.$name.'"></canvas>';
    // Simboli liste regionali
    $symbolLegends .= '<h2>Simboli Liste</h2><div class="symbols">';
    foreach ($parsed as $r) {
      if (!empty($r['simboloUrl'])) {
        $urlFull = (str_starts_with($r['simboloUrl'],'http'))? $r['simboloUrl'] : $symbolBase.$r['simboloUrl'];
        $symbolLegends .= '<div><img src="'.htmlspecialchars($urlFull).'" alt=""/><span>'.htmlspecialchars($r['nomeLista']).'</span></div>';
      }
    }
    $symbolLegends .= '</div>';
  // Chart legend for regional lists
  $chartLegend .= buildChartLegend($parsed, 'nomeLista', 'simboloUrl', $symbolBase);
  } elseif ($tipo === 'COMUNALI_COALIZIONE' && is_array($parsed)) {
    // Winner coalition info
    $winnerCoal = null;
    if (!empty($parsed['coalizioni'])) {
      $winnerCoal = $parsed['coalizioni'][0]; // already sorted in scraper
    }
    if ($winnerCoal) {
      $sections .= '<h2>Coalizione vincitrice</h2><p>'
        . htmlspecialchars(($winnerCoal['sindaco'] ?? '') . ' / ' . ($winnerCoal['viceSindaco'] ?? ''))
        . ' — Voti totali: ' . number_format($winnerCoal['votiTotali'] ?? 0)
        . ' (' . number_format($winnerCoal['percentualeVoti'] ?? 0, 2, ',', '.') . '%)'
        . ' — Seggi: ' . ($winnerCoal['seggi'] ?? 0)
        . '</p>';
    }
    // Coalizioni table style summary list
    $sections .= '<h2>Coalizioni</h2>';
    $sections .= '<ul>' . implode('', array_map(function($c){
        return '<li>' . htmlspecialchars(($c['sindaco'] ?? '') . ' / ' . ($c['viceSindaco'] ?? ''))
          . ' — ' . number_format($c['votiTotali'] ?? 0) . ' voti (' . number_format($c['percentualeVoti'] ?? 0, 2, ',', '.') . '%)'
          . ' — Seggi: ' . ($c['seggi'] ?? 0)
          . '</li>';
      }, $parsed['coalizioni'] ?? [])) . '</ul>';

    // Simboli coalizioni
    if (!empty($parsed['coalizioni'])) {
      $symbolLegends .= '<h2>Simboli Coalizioni</h2><div class="symbols">';
      foreach ($parsed['coalizioni'] as $c) {
        if (!empty($c['simboliUrl'])) {
          $symbolLegends .= '<div>';
          foreach ($c['simboliUrl'] as $su) {
            $urlFull = (str_starts_with($su,'http'))? $su : $symbolBase.$su;
            $symbolLegends .= '<img src="'.htmlspecialchars($urlFull).'" alt=""/>';
          }
          $symbolLegends .= '<span>'.htmlspecialchars(($c['sindaco'] ?? '')).'</span></div>';
        }
      }
      $symbolLegends .= '</div>';
  // Chart legend for coalitions (use first simbolo per coalizione)
  $coalItems = array_map(fn($c)=>['nome' => (($c['sindaco'] ?? '') . ' / ' . ($c['viceSindaco'] ?? '')), 'simboloUrl' => is_array($c['simboliUrl'] ?? null) ? ($c['simboliUrl'][0] ?? '') : ($c['simboliUrl'] ?? '')], $parsed['coalizioni']);
  $chartLegend .= buildChartLegend($coalItems, 'nome', 'simboloUrl', $symbolBase);
    }
    // Grafici coalizioni (voti, % e seggi pie)
    $coalLabels = $coalVoti = $coalPerc = $coalSeggi = [];
    foreach ($parsed['coalizioni'] ?? [] as $c) {
      $coalLabels[] = ($c['sindaco'] ?? '');
      $coalVoti[] = (int)($c['votiTotali'] ?? 0);
      $coalPerc[] = (float)($c['percentualeVoti'] ?? 0);
      $coalSeggi[] = (int)($c['seggi'] ?? 0);
    }
    $sections .= '<h2>Grafici Coalizioni</h2><canvas id="chart_coal_voti_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_coal_perc_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_coal_seggi_'.$name.'"></canvas>';
    $chartsJs .= "(function(){var cv1=document.getElementById('chart_coal_voti_$name').getContext('2d');new Chart(cv1,{type:'bar',data:{labels:".jsArray($coalLabels).",datasets:[{label:'Voti Coalizioni',data:".jsArray($coalVoti).",backgroundColor:'rgba(54,162,235,0.6)'}]},options:{responsive:true}});var cv2=document.getElementById('chart_coal_perc_$name').getContext('2d');new Chart(cv2,{type:'bar',data:{labels:".jsArray($coalLabels).",datasets:[{label:'% Voti Coalizioni',data:".jsArray($coalPerc).",backgroundColor:'rgba(255,99,132,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,suggestedMax:100}}}});var cv3=document.getElementById('chart_coal_seggi_$name').getContext('2d');new Chart(cv3,{type:'pie',data:{labels:".jsArray($coalLabels).",datasets:[{label:'Seggi',data:".jsArray($coalSeggi).",backgroundColor:".jsArray(array_map(fn($i)=>'hsl('.($i*53%360).',65%,60%)', array_keys($coalLabels)))."}]},options:{responsive:true}});})();";
    // Liste dettaglio grafici (voti e % e seggi bar)
    $labels = $voti = $perc = $seggi = [];
    foreach ($parsed['listeDettaglio'] ?? [] as $l) {
      $labels[] = $l['nomeLista'] ?? 'Lista';
      $voti[] = (int)($l['voti'] ?? 0);
      $perc[] = (float)($l['percentualeVoti'] ?? 0);
      $seggi[] = (int)($l['seggi'] ?? 0);
    }
    $chartsJs .= "(function(){var l1=document.getElementById('chart_liste_voti_$name').getContext('2d');new Chart(l1,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'Voti Liste',data:".jsArray($voti).",backgroundColor:'rgba(0,123,255,0.6)'}]},options:{responsive:true}});var l2=document.getElementById('chart_liste_perc_$name').getContext('2d');new Chart(l2,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'% Voti Liste',data:".jsArray($perc).",backgroundColor:'rgba(255,193,7,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,suggestedMax:100}}}});var l3=document.getElementById('chart_liste_seggi_$name').getContext('2d');new Chart(l3,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'Seggi Liste',data:".jsArray($seggi).",backgroundColor:'rgba(40,167,69,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,precision:0}}}});})();";
    // Simboli liste dettaglio
    if (!empty($parsed['listeDettaglio'])) {
      $symbolLegends .= '<h2>Simboli Liste</h2><div class="symbols">';
      foreach ($parsed['listeDettaglio'] as $l) {
        if (!empty($l['simboloUrl'])) {
          $urlFull = (str_starts_with($l['simboloUrl'],'http'))? $l['simboloUrl'] : $symbolBase.$l['simboloUrl'];
          $symbolLegends .= '<div><img src="'.htmlspecialchars($urlFull).'" alt=""/><span>'.htmlspecialchars($l['nomeLista']).'</span></div>';
        }
      }
      $symbolLegends .= '</div>';
  // Chart legend for detailed lists
  $chartLegend .= buildChartLegend($parsed['listeDettaglio'], 'nomeLista', 'simboloUrl', $symbolBase);
    }
    $sections .= '<h2>Liste</h2><canvas id="chart_liste_voti_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_liste_perc_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_liste_seggi_'.$name.'"></canvas>';
  } elseif ($tipo === 'BALLOTTAGGIO' && is_array($parsed)) {
    $winner = '';
    if (!empty($parsed['candidati'])) {
      usort($parsed['candidati'], fn($a,$b)=>($b['voti']??0)<=>($a['voti']??0));
      $w = $parsed['candidati'][0];
      $winner = 'Sindaco eletto: '.htmlspecialchars(($w['sindaco']??'').' / '.($w['viceSindaco']??''));
    }
    $sections .= '<h2>Esito Ballottaggio</h2><p>'.$winner.'</p>';
    $labels = $voti = $perc = [];
    foreach ($parsed['candidati'] ?? [] as $c) {
      $labels[] = $c['nomeLista'] ?? ($c['sindaco'] ?? 'Candidato');
      $voti[] = (int)($c['voti'] ?? 0);
      $perc[] = (float)($c['percentualeVoti'] ?? 0);
    }
    $chartsJs .= "(function(){var c1=document.getElementById('chart_voti_$name').getContext('2d');new Chart(c1,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'Voti',data:".jsArray($voti).",backgroundColor:'rgba(255,99,132,0.6)'}]},options:{responsive:true}});var c2=document.getElementById('chart_perc_$name').getContext('2d');new Chart(c2,{type:'bar',data:{labels:".jsArray($labels).",datasets:[{label:'% Voti',data:".jsArray($perc).",backgroundColor:'rgba(54,162,235,0.6)'}]},options:{responsive:true,scales:{y:{beginAtZero:true,suggestedMax:100}}}});})();";
    $sections .= '<h2>Grafici</h2><canvas id="chart_voti_'.$name.'"></canvas><canvas style="margin-top:40px" id="chart_perc_'.$name.'"></canvas>';
    // Simboli candidati
    if (!empty($parsed['candidati'])) {
      $symbolLegends .= '<h2>Simboli Candidati</h2><div class="symbols">';
      foreach ($parsed['candidati'] as $c) {
        if (!empty($c['simboloUrl'])) {
          $urlFull = (str_starts_with($c['simboloUrl'],'http'))? $c['simboloUrl'] : $symbolBase.$c['simboloUrl'];
          $symbolLegends .= '<div><img src="'.htmlspecialchars($urlFull).'" alt=""/><span>'.htmlspecialchars(($c['sindaco'] ?? '')).'</span></div>';
        }
      }
      $symbolLegends .= '</div>';
  // Chart legend for candidati
  $chartLegend .= buildChartLegend($parsed['candidati'], 'sindaco', 'simboloUrl', $symbolBase);
    }
  }

  $html = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Report {$tipo} - {$name}</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;max-width:1350px;margin:auto;}h1{margin-top:0}.kpi{display:flex;gap:.75rem;flex-wrap:wrap;margin:1rem 0}.kpi div{background:#f5f5f5;padding:.5rem .75rem;border-radius:4px;font-size:.9rem} pre{background:#111;color:#eee;padding:10px;overflow:auto;border-radius:4px;} canvas{max-width:100%;} ul{columns:3;-webkit-columns:3;-moz-columns:3;} .symbols{display:flex;flex-wrap:wrap;gap:.6rem;margin:1.2rem 0}.symbols div{display:flex;align-items:center;gap:.35rem;background:#fafafa;border:1px solid #e5e5e5;padding:.35rem .55rem;border-radius:4px;font-size:.7rem;line-height:1.1}.symbols img{height:34px;width:auto;display:block}.chart-legend{display:flex;flex-wrap:wrap;gap:.6rem;margin:0.9rem 0}.chart-legend div{display:flex;align-items:center;gap:.4rem;background:#fff;border:1px solid #eee;padding:.25rem .5rem;border-radius:4px;font-size:.85rem}.chart-legend img{height:26px;width:auto;display:block}</style>
</head>
<body>
  <h1>Report {$tipo}</h1>
  <p><strong>URL:</strong> <a href="{$url}" target="_blank">{$url}</a></p>
  {$kpi}
  {$sections}
  {$chartLegend}
  {$symbolLegends}
  <h2>Dati grezzi</h2>
  <pre>{$dataJson}</pre>
  <script>{$chartsJs}</script>
</body>
</html>
HTML;
  file_put_contents($fname, $html);
}
// Print summary location
echo "Saved aggregated JSON to: $jsonOutPath\n";
echo "Reports saved to: $reportsDir\n";

