<?php

namespace Tests\Unit\Planilhas;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\Planilhas\XlsxCotacaoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class XlsxCotacaoServiceTest extends TestCase
{
    public function test_le_itens_da_planilha_no_padrao_comlink(): void
    {
        $path = $this->criarXlsxFake('planilhas/originais/teste.xlsx');
        $service = new XlsxCotacaoService;

        $itens = $service->lerItens(Storage::path($path));

        $this->assertCount(2, $itens);
        $this->assertSame(4, $itens[0]['linha']);
        $this->assertSame('CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST', $itens[0]['descricao']);
        $this->assertSame('PC', $itens[0]['unidade']);
        $this->assertSame(3.0, $itens[0]['quantidade']);
    }

    public function test_gera_planilha_processada_preenchendo_marca_e_valor(): void
    {
        $original = $this->criarXlsxFake('planilhas/originais/teste.xlsx');

        $planilha = new PlanilhaCotacao([
            'user_id' => 1,
            'nome_arquivo' => 'teste.xlsx',
            'arquivo_original' => $original,
            'status' => 'processando',
            'total_itens' => 1,
        ]);
        $planilha->id = 123;

        $itemSelecionado = new PlanilhaCotacaoItem([
            'planilha_cotacao_id' => $planilha->id,
            'linha' => 4,
            'descricao' => 'CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST',
            'marca_cotada' => 'Gedore',
            'valor_unitario' => 9.88,
            'status' => 'concluido',
        ]);

        $itemSemSelecao = new PlanilhaCotacaoItem([
            'planilha_cotacao_id' => $planilha->id,
            'linha' => 5,
            'descricao' => 'ALICATE CORTE 6" TRAMONTINA 41006106',
            'marca_cotada' => null,
            'valor_unitario' => null,
            'status' => 'concluido',
        ]);
        $planilha->setRelation('itens', new Collection([$itemSelecionado, $itemSemSelecao]));

        $service = new XlsxCotacaoService;
        $processado = $service->gerarPlanilhaProcessada($planilha);

        $itens = $service->lerItens(Storage::path($processado));

        $this->assertSame('Gedore', $itens[0]['marca_cotada']);
        $this->assertSame(9.88, $itens[0]['valor_unitario']);
        $this->assertSame('Marca Original', $itens[1]['marca_cotada']);
        $this->assertSame(12.34, $itens[1]['valor_unitario']);
    }

    private function criarXlsxFake(string $path): string
    {
        Storage::put($path, '');
        $fullPath = Storage::path($path);

        $zip = new ZipArchive;
        $zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet());
        $zip->close();

        return $path;
    }

    private function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function rels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Cotacao" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function workbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML;
    }

    private function sheet(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="3">
      <c r="A3" t="inlineStr"><is><t>Item</t></is></c>
      <c r="C3" t="inlineStr"><is><t>Descricao</t></is></c>
    </row>
    <row r="4">
      <c r="A4"><v>1</v></c>
      <c r="B4"><v>1</v></c>
      <c r="C4" t="inlineStr"><is><t>CHAVE PHILIPS 1/4" X 4" GEDORE/BELZER/ROBUST</t></is></c>
      <c r="D4" t="inlineStr"><is><t>PC</t></is></c>
      <c r="F4"><v>3</v></c>
    </row>
    <row r="5">
      <c r="A5"><v>2</v></c>
      <c r="B5"><v>1</v></c>
      <c r="C5" t="inlineStr"><is><t>ALICATE CORTE 6" TRAMONTINA 41006106</t></is></c>
      <c r="D5" t="inlineStr"><is><t>PC</t></is></c>
      <c r="F5"><v>3</v></c>
      <c r="J5" t="inlineStr"><is><t>Marca Original</t></is></c>
      <c r="N5"><v>12.34</v></c>
    </row>
  </sheetData>
</worksheet>
XML;
    }
}
