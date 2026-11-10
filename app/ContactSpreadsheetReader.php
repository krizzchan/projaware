<?php

namespace App;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PharData;
use RecursiveIteratorIterator;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ContactSpreadsheetReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @return resource */
    public function open(UploadedFile $file)
    {
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            return fopen($file->path(), 'r');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'aware_import_');
        if ($temporary === false) {
            throw ValidationException::withMessages(['csv_file' => 'Unable to read workbook.']);
        }
        $path = $temporary.'.zip';
        $stream = fopen('php://temp', 'w+');
        try {
            if (! copy($file->path(), $path)) {
                throw new RuntimeException;
            }
            $archive = new PharData($path);
            $size = 0;
            $count = 0;
            foreach (new RecursiveIteratorIterator($archive) as $entry) {
                $size += $entry->getSize();
                if (++$count > 100 || $size > 12 * 1024 * 1024 || str_ends_with(strtolower($entry->getFilename()), '.bin')) {
                    throw new RuntimeException;
                }
            }
            $workbook = $this->xml($archive, 'xl/workbook.xml');
            $relationshipId = '';
            foreach ($workbook->xpath('/s:workbook/s:sheets/s:sheet') ?: [] as $sheet) {
                if ((string) $sheet['name'] === 'Contacts') {
                    $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                }
            }
            $sheetPath = null;
            foreach ($this->xml($archive, 'xl/_rels/workbook.xml.rels')->xpath('/*/*') ?: [] as $relationship) {
                if ($relationshipId !== '' && (string) $relationship['Id'] === $relationshipId) {
                    $target = (string) $relationship['Target'];
                    if ((string) $relationship['TargetMode'] === 'External' || ! preg_match('~^(?:/xl/)?worksheets/sheet[0-9]+\.xml$~', $target)) {
                        throw new RuntimeException;
                    }
                    $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                }
            }
            if ($sheetPath === null) {
                throw new RuntimeException;
            }
            $shared = [];
            if (isset($archive['xl/sharedStrings.xml'])) {
                foreach ($this->xml($archive, 'xl/sharedStrings.xml')->xpath('/s:sst/s:si') ?: [] as $string) {
                    $shared[] = $this->text($string);
                    if (count($shared) > 10000) {
                        throw new RuntimeException;
                    }
                }
            }
            $previous = 0;
            $contacts = 0;
            foreach ($this->xml($archive, $sheetPath)->xpath('/s:worksheet/s:sheetData/s:row') ?: [] as $row) {
                $number = (int) $row['r'];
                if ($number <= $previous || $number > 5000 || ($previous === 0 && $number !== 1)) {
                    throw new RuntimeException;
                }
                $previous = $number;
                $values = array_fill(0, 8, '');
                $used = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    if (! preg_match('/^([A-Z]+)([1-9][0-9]*)$/', $reference, $match) || (int) $match[2] !== $number || isset($used[$reference]) || isset($cell->f)) {
                        throw new RuntimeException;
                    }
                    $used[$reference] = true;
                    $type = (string) $cell['t'];
                    $value = $type === 'inlineStr' ? $this->text($cell->is) : (string) $cell->v;
                    if ($type === 's') {
                        if (! ctype_digit($value) || ! array_key_exists((int) $value, $shared)) {
                            throw new RuntimeException;
                        }
                        $value = $shared[(int) $value];
                    } elseif (! in_array($type, ['', 'inlineStr', 'str', 'n'], true)) {
                        throw new RuntimeException;
                    }
                    if (strlen($value) > 4000) {
                        throw new RuntimeException;
                    }
                    if (strlen($match[1]) !== 1 || $match[1] > 'H') {
                        if ($value !== '') {
                            throw new RuntimeException;
                        }
                        continue;
                    }
                    $index = ord($match[1]) - 65;
                    if ($index === 5 && preg_match('/^9[0-9]{9}$/', $value)) {
                        $value = '0'.$value;
                    }
                    $values[$index] = $value;
                }
                if ($number !== 1 && count(array_filter($values, fn (string $value) => trim($value) !== '')) === 0) {
                    continue;
                }
                if ($number !== 1 && ++$contacts > 1000) {
                    throw new RuntimeException;
                }
                fputcsv($stream, $values, ',', '"', '', "\r\n");
            }
            rewind($stream);

            return $stream;
        } catch (Throwable) {
            fclose($stream);
            throw ValidationException::withMessages(['csv_file' => 'Invalid Excel workbook. Use the Contacts sheet in the XLSX template, with at most 1,000 contacts and no formulas or macros. No contacts were imported.']);
        } finally {
            unset($archive);
            if (is_file($path)) {
                unlink($path);
            }
            unlink($temporary);
        }
    }

    private function xml(PharData $archive, string $name): SimpleXMLElement
    {
        if (! isset($archive[$name]) || $archive[$name]->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException;
        }
        $handle = $archive[$name]->openFile('r');
        $content = '';
        $limit = 5 * 1024 * 1024;
        while (! $handle->eof() && strlen($content) <= $limit) {
            $chunk = $handle->fread(min(8192, $limit + 1 - strlen($content)));
            if ($chunk === '' && ! $handle->eof()) {
                throw new RuntimeException;
            }
            $content .= $chunk;
        }
        if (strlen($content) > 5 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $content)) {
            throw new RuntimeException;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new RuntimeException;
            }
            $xml->registerXPathNamespace('s', self::NS);

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $element): string
    {
        $element->registerXPathNamespace('s', self::NS);

        return implode('', array_map(fn (SimpleXMLElement $text) => (string) $text, $element->xpath('.//s:t') ?: []));
    }
}
