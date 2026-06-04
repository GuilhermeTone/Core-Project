<?php

namespace App\Services\Planilhas;

use App\Models\PlanilhaCotacao;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class XlsxCotacaoService
{
    private const XMLNS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const COLUNA_DESCRICAO = 'C';
    private const COLUNA_MARCA_COTADA = 'J';
    private const COLUNA_VALOR_UNITARIO = 'N';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lerItens(string $pathAbsoluto): array
    {
        $zip = $this->abrirZip($pathAbsoluto);
        $sharedStrings = $this->lerSharedStrings($zip);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('A primeira aba da planilha não foi encontrada.');
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml);

        $linhas = [];

        foreach ($dom->getElementsByTagName('row') as $row) {
            /** @var DOMElement $row */
            $numeroLinha = (int) $row->getAttribute('r');

            if ($numeroLinha < 4) {
                continue;
            }

            $valores = [];

            foreach ($row->getElementsByTagName('c') as $cell) {
                /** @var DOMElement $cell */
                [$coluna] = $this->separarReferenciaCelula($cell->getAttribute('r'));
                $valores[$coluna] = $this->valorCelula($cell, $sharedStrings);
            }

            $descricao = trim((string) ($valores[self::COLUNA_DESCRICAO] ?? ''));

            if ($descricao === '') {
                continue;
            }

            $linhas[] = [
                'linha' => $numeroLinha,
                'item' => $valores['A'] ?? null,
                'sequencia' => $valores['B'] ?? null,
                'descricao' => $descricao,
                'unidade' => $valores['D'] ?? null,
                'quantidade' => $this->numeroDecimal($valores['F'] ?? null),
                'marca_cotada' => $valores[self::COLUNA_MARCA_COTADA] ?? null,
                'valor_unitario' => $this->numeroDecimal($valores[self::COLUNA_VALOR_UNITARIO] ?? null),
                'cod_forn' => $valores['L'] ?? null,
                'entrega' => $valores['M'] ?? null,
                'desconto' => $this->numeroDecimal($valores['O'] ?? null),
            ];
        }

        return $linhas;
    }

    public function gerarPlanilhaProcessada(PlanilhaCotacao $planilha): string
    {
        if (! $planilha->relationLoaded('itens')) {
            $planilha->load('itens');
        }

        $origem = Storage::path($planilha->arquivo_original);
        $destinoRelativo = 'planilhas/processadas/planilha-'.$planilha->id.'-processada.xlsx';
        $destino = Storage::path($destinoRelativo);

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        if (! copy($origem, $destino)) {
            throw new RuntimeException('Não foi possível criar a cópia processada da planilha.');
        }

        $zip = $this->abrirZip($destino);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($xml === false) {
            $zip->close();
            throw new RuntimeException('A primeira aba da planilha não foi encontrada.');
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);

        foreach ($planilha->itens as $item) {
            if ($item->marca_cotada !== null && $item->marca_cotada !== '') {
                $this->setCelulaString($dom, self::COLUNA_MARCA_COTADA, $item->linha, (string) $item->marca_cotada);
            }

            if ($item->valor_unitario !== null) {
                $this->setCelulaNumero($dom, self::COLUNA_VALOR_UNITARIO, $item->linha, (float) $item->valor_unitario);
            }
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $dom->saveXML());
        $zip->close();

        return $destinoRelativo;
    }

    /**
     * @return string[]
     */
    private function lerSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $strings = [];

        foreach ($dom->getElementsByTagName('si') as $si) {
            $partes = [];

            foreach ($si->getElementsByTagName('t') as $texto) {
                $partes[] = $texto->textContent;
            }

            $strings[] = implode('', $partes);
        }

        return $strings;
    }

    /**
     * @param  string[]  $sharedStrings
     */
    private function valorCelula(DOMElement $cell, array $sharedStrings): ?string
    {
        $tipo = $cell->getAttribute('t');

        if ($tipo === 'inlineStr') {
            $texto = $cell->getElementsByTagName('t')->item(0);

            return $texto?->textContent;
        }

        $valor = $cell->getElementsByTagName('v')->item(0)?->textContent;

        if ($valor === null) {
            return null;
        }

        if ($tipo === 's') {
            return $sharedStrings[(int) $valor] ?? null;
        }

        return $valor;
    }

    private function setCelulaString(DOMDocument $dom, string $coluna, int $linha, string $valor): void
    {
        $cell = $this->obterOuCriarCelula($dom, $coluna, $linha);
        $this->limparConteudoCelula($cell);
        $cell->setAttribute('t', 'inlineStr');

        $is = $this->criarElementoPlanilha($dom, 'is');
        $t = $this->criarElementoPlanilha($dom, 't');
        $t->appendChild($dom->createTextNode($valor));
        $is->appendChild($t);
        $cell->appendChild($is);
    }

    private function setCelulaNumero(DOMDocument $dom, string $coluna, int $linha, float $valor): void
    {
        $cell = $this->obterOuCriarCelula($dom, $coluna, $linha);
        $this->limparConteudoCelula($cell);
        $cell->removeAttribute('t');
        $cell->appendChild($this->criarElementoPlanilha($dom, 'v', number_format($valor, 2, '.', '')));
    }

    private function obterOuCriarCelula(DOMDocument $dom, string $coluna, int $linha): DOMElement
    {
        $ref = $coluna.$linha;

        foreach ($dom->getElementsByTagName('c') as $cell) {
            /** @var DOMElement $cell */
            if ($cell->getAttribute('r') === $ref) {
                return $cell;
            }
        }

        $sheetData = $dom->getElementsByTagName('sheetData')->item(0);

        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Estrutura sheetData não encontrada.');
        }

        $row = null;

        foreach ($sheetData->getElementsByTagName('row') as $candidate) {
            /** @var DOMElement $candidate */
            if ((int) $candidate->getAttribute('r') === $linha) {
                $row = $candidate;
                break;
            }
        }

        if (! $row instanceof DOMElement) {
            $row = $dom->createElement('row');
            $row->setAttribute('r', (string) $linha);
            $sheetData->appendChild($row);
        }

        $cell = $this->criarElementoPlanilha($dom, 'c');
        $cell->setAttribute('r', $ref);
        $row->appendChild($cell);

        return $cell;
    }

    private function criarElementoPlanilha(DOMDocument $dom, string $nome, ?string $valor = null): DOMElement
    {
        $namespace = $dom->documentElement?->namespaceURI ?: self::XMLNS_MAIN;

        if ($valor === null) {
            return $dom->createElementNS($namespace, $nome);
        }

        return $dom->createElementNS($namespace, $nome, $valor);
    }

    private function limparConteudoCelula(DOMElement $cell): void
    {
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
    }

    /**
     * @return array{string, int}
     */
    private function separarReferenciaCelula(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);

        return [$matches[1] ?? '', (int) ($matches[2] ?? 0)];
    }

    private function numeroDecimal(?string $valor): ?float
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        $valor = trim($valor);

        if (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function abrirZip(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir a planilha XLSX.');
        }

        return $zip;
    }
}
