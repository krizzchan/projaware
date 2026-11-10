<?php

namespace App;

use App\Models\Barangay;
use App\Models\Category;
use Phar;
use PharData;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use XMLWriter;

class ContactExcelTemplate
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function download(array $columns): BinaryFileResponse
    {
        $categories = Category::orderBy('name')->get(['id', 'name'])->map(fn ($item) => $item->id.' - '.$item->name)->all();
        $barangays = ['Not applicable', ...Barangay::orderBy('name')->get(['id', 'name'])->map(fn ($item) => $item->id.' - '.$item->name)->all()];
        $temp = tempnam(sys_get_temp_dir(), 'aware_contacts_');
        if ($temp === false) {
            throw new RuntimeException('Unable to create contact template.');
        }
        $path = $temp.'.zip';
        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $archive->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $archive->addFromString('xl/workbook.xml', $this->workbook(max(1, count($categories)), count($barangays)));
            $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $archive->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="'.self::NS.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $archive->addFromString('xl/worksheets/sheet1.xml', $this->contacts($columns));
            $archive->addFromString('xl/worksheets/sheet2.xml', $this->lists($categories, $barangays));
            unset($archive);
            app(OfficeZipHeaders::class)->normalize($path);

            return response()->download($path, 'Project-AWARE-Contacts-Template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        } finally {
            unlink($temp);
        }
    }

    private function workbook(int $categories, int $barangays): string
    {
        return '<?xml version="1.0"?><workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Contacts" sheetId="1" r:id="rId1"/><sheet name="Lists and instructions" sheetId="2" r:id="rId2"/></sheets><definedNames><definedName name="CategoryOptions">\'Lists and instructions\'!$A$2:$A$'.($categories + 1).'</definedName><definedName name="BarangayOptions">\'Lists and instructions\'!$B$2:$B$'.($barangays + 1).'</definedName></definedNames></workbook>';
    }

    private function xml(): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', self::NS);
        return $xml;
    }

    private function contacts(array $columns): string
    {
        $xml = $this->xml();
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0" showGridLines="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols><col min="1" max="1" width="22" customWidth="1"/><col min="2" max="2" width="16" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/><col min="4" max="5" width="32" customWidth="1"/><col min="6" max="6" width="22" customWidth="1" style="2"/><col min="7" max="8" width="42" customWidth="1"/></cols>');
        $xml->startElement('sheetData');
        $this->row($xml, 1, $columns, 1);
        for ($row = 2; $row <= 1001; $row++) {
            $this->row($xml, $row, array_fill(0, 8, ''), 2);
        }
        $xml->endElement();
        $xml->writeRaw('<autoFilter ref="A1:H1001"/><dataValidations count="2">');
        foreach (['G2:G1001' => 'CategoryOptions', 'H2:H1001' => 'BarangayOptions'] as $range => $name) {
            $xml->startElement('dataValidation');
            foreach (['type' => 'list', 'allowBlank' => '1', 'showDropDown' => '0', 'showInputMessage' => '1', 'showErrorMessage' => '1', 'errorStyle' => 'stop', 'errorTitle' => 'Select a saved value', 'error' => 'Choose an item from the dropdown list.', 'promptTitle' => 'Saved directory records', 'prompt' => 'Select a saved record. Use Not applicable for an external agency barangay.', 'sqref' => $range] as $key => $value) {
                $xml->writeAttribute($key, $value);
            }
            $xml->writeElement('formula1', $name);
            $xml->endElement();
        }
        $xml->writeRaw('</dataValidations>');
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function lists(array $categories, array $barangays): string
    {
        $instructions = [
            'PROJECT AWARE - ILAGAN CDRRMO CONTACT TEMPLATE',
            'Fill the Contacts sheet only. Keep row 1 headers unchanged.',
            'Use rows 2 to 1001; one contact per row.',
            'Required: first_name, last_name, phone_number, category_id.',
            'Choose category_id and barangay_id from the dropdowns.',
            'Use Not applicable or a blank barangay for external agencies.',
            'Phone numbers are formatted as Text to preserve the leading zero.',
            'Enter a verified 09xxxxxxxxx or +639xxxxxxxxx mobile number.',
            'Imported contacts are active; do not upload fictional phone numbers.',
            'Save as XLSX and upload directly using Import CSV / Excel.',
            'Keep this XLSX original to retain the dropdowns.',
            'Download a new template when categories or barangays change.',
            'Only administrators can import contacts. No SMS is sent by importing.',
        ];
        $xml = $this->xml();
        $xml->writeRaw('<sheetFormatPr defaultRowHeight="24"/><cols><col min="1" max="2" width="48" customWidth="1"/><col min="3" max="3" width="90" customWidth="1"/></cols>');
        $xml->startElement('sheetData');
        $this->row($xml, 1, ['Category options', 'Barangay options', 'Instructions'], 1);
        for ($index = 0; $index < max(count($categories), count($barangays), count($instructions)); $index++) {
            $this->row($xml, $index + 2, [$categories[$index] ?? '', $barangays[$index] ?? '', $instructions[$index] ?? '']);
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function row(XMLWriter $xml, int $row, array $values, int $style = 0): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $row);
        if ($style === 1) {
            $xml->writeAttribute('ht', '32');
            $xml->writeAttribute('customHeight', '1');
        }
        foreach ($values as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $index).$row);
            $xml->writeAttribute('s', (string) $style);
            $xml->writeAttribute('t', 'inlineStr');
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value) ?? '');
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }
}
