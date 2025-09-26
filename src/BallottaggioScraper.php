<?php

namespace ElectionScraperVdA;

use ElectionScraperVdA\Model\BallottaggioResult;
use ElectionScraperVdA\Model\BallottaggioCandidate;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;
use DOMNode;
use DOMElement;

class BallottaggioScraper extends AbstractHtmlScraper
{
    public function fetch(string $url): BallottaggioResult { return parent::fetch($url); }

    public function parseHtml(string $html): BallottaggioResult { return parent::parseHtml($html); }

    protected function doParse(): BallottaggioResult
    {
        $nomeComune = $this->extractNomeComune();
        $result = new BallottaggioResult($nomeComune);
        $this->extractDatiGenerali($result);
        $this->extractCandidati($result);
        $result->sortCandidatiByVoti();
        return $result;
    }

    private function extractNomeComune(): string
    {
        $titleNodes = $this->xpath->query('//title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
            if (preg_match('/Voti\s*-\s*(?:Voti\s*-\s*)?([A-ZÀÁÈÉÌÍÒÓÙÚ\s]+)/', $title, $matches)) {
                return trim($matches[1]);
            }
        }
        $breadcrumbItems = $this->xpath->query('//div[@id="bread"]//li[last()]');
        if ($breadcrumbItems->length > 0) {
            return trim($breadcrumbItems->item(0)->textContent);
        }
        $h1Nodes = $this->xpath->query('//div[@class="riepilogo-elezione-box"]//h1');
        if ($h1Nodes->length > 0) {
            return trim($h1Nodes->item(0)->textContent);
        }
        return 'Comune sconosciuto';
    }

    private function extractDatiGenerali(BallottaggioResult $result): void
    {
        $dataTables = $this->xpath->query('//div[@class="riepilogo-elezione-box"]//table');
        foreach ($dataTables as $table) {
            $rows = $this->xpath->query('.//tr', $table);
            foreach ($rows as $row) {
                $cells = $this->xpath->query('.//td', $row);
                if ($cells->length < 2) continue;
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);
                $third = $cells->length > 2 ? trim($cells->item(2)->textContent) : '';
                switch (true) {
                    case stripos($label, 'elettori') !== false: $result->elettori = $this->parseInt($value); break;
                    case stripos($label, 'votanti') !== false:
                        $result->votanti = $this->parseInt($value);
                        $result->affluenzaPercent = $this->parseFloat($third);
                        break;
                    case stripos($label, 'scrutinate') !== false:
                        $result->schedeScrutinate = $this->parseInt($value);
                        $result->schedeScrutinatePercent = $this->parseFloat($third);
                        break;
                    case stripos($label, 'bianche') !== false:
                        $result->schedeBianche = $this->parseInt($value);
                        $result->schedeBianchePercent = $this->parseFloat($third);
                        break;
                    case stripos($label, 'nulle') !== false:
                        $result->schedeNulle = $this->parseInt($value);
                        $result->schedeNullePercent = $this->parseFloat($third);
                        break;
                }
            }
        }
        if ($result->affluenzaPercent == 0 && $result->elettori > 0) {
            $result->affluenzaPercent = ($result->votanti / $result->elettori) * 100;
        }
    }

    private function extractCandidati(BallottaggioResult $result): void
    {
        $candidateTable = $this->xpath->query('//div[@class="voti-riepilogo"]//table')->item(0)
            ?: $this->xpath->query('//table[contains(@class, "VotiLista")]')->item(0);
        if (!$candidateTable) return;
        $rows = $this->xpath->query('.//tr', $candidateTable);
        foreach ($rows as $row) {
            if ($this->xpath->query('.//td[contains(@class, "voti-header") or contains(., "LISTE") or contains(., "Prog")]', $row)->length > 0) continue;
            $cells = $this->xpath->query('.//td', $row);
            if ($cells->length < 4) continue;
            $candidato = $this->parseCandidatoRow($cells);
            if ($candidato) $result->addCandidato($candidato);
        }
    }

    private function parseCandidatoRow(\DOMNodeList $cells): ?BallottaggioCandidate
    {
        if ($cells->length < 4) return null;
        $candidateCell = $cells->item(1);
        $candidateInfo = $this->extractCandidateInfo($candidateCell);
        if (empty($candidateInfo['sindaco'])) return null;
        $candidato = new BallottaggioCandidate($candidateInfo['sindaco'], $candidateInfo['viceSindaco'], $candidateInfo['nomeLista']);
        $simboloImg = $this->xpath->query('.//img', $candidateCell)->item(0);
        if ($simboloImg instanceof DOMElement) $candidato->simboloUrl = $simboloImg->getAttribute('src');
        if ($cells->length > 3) $candidato->voti = $this->parseInt($cells->item(3)->textContent);
        if ($cells->length > 4) $candidato->percentualeVoti = $this->parseFloat($cells->item(4)->textContent);
        if ($cells->length > 5) $candidato->votiContestati = $this->parseInt($cells->item(5)->textContent);
        return $candidato;
    }

    private function extractCandidateInfo(DOMNode $candidateCell): array
    {
        $res = ['sindaco' => '', 'viceSindaco' => '', 'nomeLista' => ''];
        $sindacoDiv = $this->xpath->query('.//div[contains(@id, "pnlSindaco")]', $candidateCell)->item(0);
        if ($sindacoDiv) {
            $text = strip_tags($this->innerHTML($sindacoDiv), '<br><strong>');
            $text = html_entity_decode($text);
            $lines = preg_split('/(?:<br\s*\/?>|\r?\n)+/i', $text);
            $lines = array_values(array_filter(array_map('trim', $lines)));
            $nomeLista = $sindaco = $vice = '';
            foreach ($lines as $line) {
                $clean = strip_tags($line);
                if (strpos($line, '<strong>') !== false) {
                    $nomeLista = $clean; continue;
                }
                if ($sindaco === '' && $clean !== '') {
                    if ($nomeLista !== '') { $sindaco = $clean; } else { $nomeLista = $clean; }
                    continue;
                }
                if ($vice === '' && $clean !== '' && $sindaco !== '') { $vice = $clean; }
            }
            if ($sindaco !== '' && $nomeLista === '' && count($lines) >= 2) {
                $nomeLista = strip_tags($lines[0]);
                $sindaco = strip_tags($lines[1] ?? '');
                $vice = strip_tags($lines[2] ?? '');
            }
            $res['sindaco'] = $sindaco; $res['viceSindaco'] = $vice; $res['nomeLista'] = $nomeLista;
        }
        return $res;
    }
}
