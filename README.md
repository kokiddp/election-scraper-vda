# Election Scraper Valle d'Aosta

![Status](https://img.shields.io/badge/status-experimental-blue) ![PHP](https://img.shields.io/badge/PHP-%3E=7.4-777bb4) ![License](https://img.shields.io/badge/license-MIT-brightgreen)

Libreria PHP per scaricare, normalizzare e riutilizzare i risultati elettorali pubblicati sul portale della Regione Autonoma Valle d'Aosta. Tutti gli scraper condividono un'API coerente e modelli di dominio tipizzati, così da integrare facilmente i dati in dashboard, report o applicazioni custom.

## Table of contents

- [Overview](#overview)
- [Quick start](#quick-start)
- [Key features](#key-features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [From source](#from-source)
- [Usage](#usage)
  - [Unified fetch/parse API](#unified-fetchparse-api)
  - [Caching and logging](#caching-and-logging)
  - [Command-line helper](#command-line-helper)
- [Available scrapers](#available-scrapers)
- [Domain models & value objects](#domain-models--value-objects)
- [Architecture](#architecture)
- [Testing](#testing)
- [Extending the library](#extending-the-library)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)
- [Disclaimer](#disclaimer)

## Overview

Election Scraper espone una famiglia di scraper HTML basati su `DOMDocument` e `DOMXPath` per trasformare le tabelle elettorali regionali in oggetti PHP. L'astrazione comune (`AbstractHtmlScraper`) gestisce download, cache opzionale, logging PSR-3, normalizzazione dell'encoding e sanificazione dei dati. Ogni scraper restituisce modelli ricchi (`ComunalResult`, `RegionalResult`, `ReferendumResult`, ecc.) corredati da metodi helper e value object (`VoteCount`, `Percentage`).

## Quick start

```bash
composer install
php scripts/parse_pages.php
```

```php
use ElectionScraperVdA\ReferendumScraper;

$scraper = new ReferendumScraper();
$results = $scraper->fetch('https://example.test/referendum.html');

foreach ($results as $result) {
    echo $result->getResultSummary(), PHP_EOL;
}
```

- Usa `parseHtml()` se hai già il markup localmente.
- Gli esempi HTML sono nella cartella `examples/` per esperimenti rapidi.

## Key features

- ✅ Supporto a cinque tipologie di consultazioni (referendum, regionali, comunali, coalizioni comunali, ballottaggi).
- ✅ API coerente `fetch()` / `parseHtml()` con docblock generics per l'inferenza dei tipi.
- ✅ Modelli che estendono `AbstractElectionResult` con metodi di riepilogo (`getSummary()`).
- ✅ Value object immutabili per voti e percentuali (`VoteCount`, `Percentage`).
- ✅ Caching personalizzabile via callback o PSR-16 plug-and-play.
- ✅ Logging opzionale attraverso qualunque `Psr\Log\LoggerInterface`.
- ✅ Gestione errori dedicata con `ScraperException::network()` e `ScraperException::parsing()`.
- ✅ Suite PHPUnit che copre edge case HTML e aggregazioni numeriche.

## Installation

### Requirements

- PHP >= 7.4
- Estensioni: `ext-dom`, `ext-libxml`
- Composer

### From source

```bash
git clone https://github.com/kokiddp/election-scraper-vda.git
cd election-scraper-vda
composer install
```

> Quando il pacchetto sarà disponibile su Packagist potrai installarlo con `composer require kokiddp/election-scraper-vda` all'interno di un altro progetto.

## Usage

### Unified fetch/parse API

Ogni scraper implementa la stessa interfaccia:

```php
$resultOrCollection = $scraper->fetch(string $url);      // scarica e fa parsing
$resultOrCollection = $scraper->parseHtml(string $html); // usa HTML già disponibile
```

Il metodo protetto `doParse()` racchiude la logica specifica sfruttando `DOMXPath`.

### Caching and logging

```php
use ElectionScraperVdA\ComunalScraper;
use Psr\Log\NullLogger;

$cache = function (string $key, string $url, callable $download) {
    $tmp = sys_get_temp_dir() . '/es_' . $key;
    if (is_file($tmp)) {
        return file_get_contents($tmp);
    }
    $html = $download($url);
    file_put_contents($tmp, $html);
    return $html;
};

$scraper = new ComunalScraper(cacheCallback: $cache, logger: new NullLogger());
$result = $scraper->fetch('https://example.test/comunali.html');
```

È possibile anche fornire una cache PSR-16:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use ElectionScraperVdA\ReferendumScraper;

$pool = new FilesystemAdapter(namespace: 'election', defaultLifetime: 300);
$psr16 = new Psr16Cache($pool);
$scraper = new ReferendumScraper(psr16Cache: $psr16, psr16Ttl: 600);
```

La priorità è: **PSR-16** > **callback custom** > nessuna cache. Puoi inoltre passare un logger PSR-3 (Monolog, `NullLogger`, ecc.) per ricevere messaggi su fetch, fallimenti e parsing.

### Command-line helper

`php scripts/parse_pages.php` processa gli HTML in `examples/` e produce `examples/parsed_output.json`, utile come regression test manuale o per esplorare la struttura dei dati.

## Available scrapers

| Scraper | Output | Descrizione |
| ------- | ------ | ----------- |
| `ReferendumScraper` | `array<ReferendumResult>` | Risultati SÌ/NO per entità (comune, Aosta, regionale) |
| `RegionalScraper` | `array<RegionalResult>` | Liste con voti, percentuali, seggi e voti contestati |
| `ComunalScraper` | `ComunalResult` | Liste e candidati del singolo comune |
| `ComunalCoalitionScraper` | `ComunalCoalitionResult` | Coalizioni sindaco/vice e liste di supporto |
| `BallottaggioScraper` | `BallottaggioResult` | Confronto a due con margine e affluenza |

## Domain models & value objects

- `AbstractElectionResult`: campi condivisi (elettori, votanti, schede bianche/nulle) e helper (`ensureAffluenza()`, `votiValidiCount()`).
- `ComunalResult`, `ComunalCoalitionResult`, `BallottaggioResult`: specializzazioni con liste, coalizioni e candidati.
- `ReferendumResult`, `RegionalResult`: modelli standalone con metodi di parsing (`fromArray()`), formatter e sintesi (`getResultSummary()`).
- `VoteCount`, `Percentage`: value object immutabili con validazioni e formattazione consistente.

Ogni modello espone `getSummary()` per generare descrizioni pronte da loggare o mostrare a video. In fase di parsing vengono sanitizzati numeri, percentuali e stringhe.

## Architecture

```
src/
    Scraper/
        AbstractHtmlScraper     <-- base comune (HTTP, DOM, cache, log, eccezioni)
        ScraperInterface        <-- contratto (fetch, parseHtml)
        ScraperException        <-- errori specializzati (network/parsing)
    Model/
        AbstractElectionResult  <-- campi condivisi e helper
        ...Result               <-- per ciascuna tipologia di consultazione
    Value/
        VoteCount, Percentage   <-- value objects immutabili / formattazione
```

Ogni scraper produce una collezione di modelli oppure un singolo modello tipizzato; le annotazioni generiche aiutano strumenti statici (PHPStan / Psalm) a inferire il tipo restituito. Test e fixture HTML si trovano rispettivamente in `tests/` ed `examples/`.

## Testing

```bash
composer install
./vendor/bin/phpunit
```

La suite (20 test / 115 assertion) copre parsing, edge case HTML, value object e riepiloghi. Gli esempi HTML in `examples/` fungono da fixture di regressione.

## Prefixed vendor build

Se devi distribuire la libreria all'interno di un PHAR, in un plugin legacy o in un ambiente dove potrebbero verificarsi collisioni con altre dipendenze, puoi generare una build con le librerie di terze parti “prefissate” tramite [PHP-Scoper](https://github.com/humbug/php-scoper).

```bash
composer build:prefixed
```

Il comando produce il codice isolato in `build/prefixed/`, mantenendo intatte le classi proprie (`ElectionScraperVdA\`) e le interfacce PSR mentre sposta il resto delle dipendenze sotto il namespace `ElectionScraperVdA\PrefixedVendor`. Copia quella cartella nel tuo artefatto finale e includi l'autoloader generato per evitare conflitti con altre versioni di Guzzle, Symfony o pacchetti PSR presenti nel progetto host.

## Extending the library

1. Crea una classe che estende `AbstractHtmlScraper`.
2. Implementa `protected function doParse()` restituendo il tuo modello (o array di modelli).
3. Aggiungi un modello dedicato (idealmente estendendo `AbstractElectionResult`) e relative value object se necessario.
4. Copia un esempio HTML reale in `examples/` e aggiungi un test in `tests/`.

Mini scheletro:

```php
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;
use ElectionScraperVdA\Model\NewTypeResult;
use DOMXPath;

class NewTypeScraper extends AbstractHtmlScraper
{
    /** @return NewTypeResult[] */
    protected function doParse(DOMXPath $xpath, string $html): array
    {
        // parsing specifico
        return [];
    }
}
```

## Roadmap

- [ ] Pubblicazione su Packagist
- [ ] Aggiunta CI (GitHub Actions) con lint + test matrix PHP 7.4/8.x
- [ ] Integrazione static analysis (PHPStan / Psalm) con livelli elevati
- [x] Aggiunta cache PSR-16 opzionale out-of-the-box
- [x] Miglior gestione encoding (detect & normalize)
- [ ] Export JSON schema standardizzato per risultati
- [x] Documentazione dei modelli principali

## Contributing

Bug report, idee e pull request sono benvenuti. Apri una issue per proporre migliorie o nuovi formati di elezione.

## License

MIT. Consulta il file `LICENSE` (se mancante, crealo prima di distribuire in produzione).

## Disclaimer

Non affiliato ufficialmente con la Regione Valle d'Aosta. Utilizzare responsabilmente rispettando termini d'uso del sito sorgente.
