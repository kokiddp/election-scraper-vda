<?php

namespace ElectionScraperVdA;

use ElectionScraperVdA\Model\ReferendumResult;
use ElectionScraperVdA\Scraper\AbstractHtmlScraper;

class ReferendumScraper extends AbstractHtmlScraper
{
    /** @return ReferendumResult[] */
    public function fetch(string $url): array
    {
        return parent::fetch($url);
    }

    /** @return ReferendumResult[] */
    public function parseHtml(string $html): array
    {
        return parent::parseHtml($html);
    }

    protected function doParse(): array
    {
        $results = [];
        $rows = $this->xpath->query("//div[@id='ctl00_ContentPlaceHolderContenuto_divRisultati']//tr[contains(@class,'tabella-dati-riga-affluenza')]");
        if ($rows === false) {
            return $results;
        }
        for ($i = 0; $i < $rows->length; $i += 2) {
            $rowAbs = $rows->item($i);
            $rowPerc = $rows->item($i + 1);
            if (!$rowAbs || !$rowPerc) {
                continue;
            }
            if (!($rowAbs instanceof \DOMElement) || !($rowPerc instanceof \DOMElement)) {
                continue;
            }
            $cellsAbs = $rowAbs->getElementsByTagName('td');
            $cellsPerc = $rowPerc->getElementsByTagName('td');
            if ($cellsAbs->length < 9 || $cellsPerc->length < 7) {
                continue;
            }
            $values = [];
            for ($c = 0; $c < 9; $c++) {
                $values[] = trim($cellsAbs->item($c)->textContent);
            }
            for ($c = 0; $c < 7; $c++) {
                $values[] = trim($cellsPerc->item($c)->textContent);
            }
            $results[] = ReferendumResult::fromArray($values);
        }
        return $results;
    }
}