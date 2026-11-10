<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Phar;
use PharData;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use XMLWriter;

class SmsReportExport
{
    public function download(Builder $query, string $status): BinaryFileResponse
    {
        $sheetPath = tempnam(sys_get_temp_dir(), 'aware_sms_');
        if ($sheetPath === false) {
            throw new RuntimeException('Unable to create report.');
        }
        $archivePath = $sheetPath.'.zip';
        try {
            $xml = new XMLWriter;
            if (! $xml->openUri($sheetPath)) {
                throw new RuntimeException('Unable to write report.');
            }
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetViews');
            $xml->startElement('sheetView');
            $xml->writeAttribute('workbookViewId', '0');
            $xml->startElement('pane');
            foreach (['ySplit' => '1', 'topLeftCell' => 'A2', 'activePane' => 'bottomLeft', 'state' => 'frozen'] as $key => $value) {
                $xml->writeAttribute($key, $value);
            }
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('cols');
            foreach ([26, 18, 16, 28, 65, 28, 14, 55, 24, 24, 28, 28] as $index => $width) {
                $xml->startElement('col');
                $xml->writeAttribute('min', (string) ($index + 1));
                $xml->writeAttribute('max', (string) ($index + 1));
                $xml->writeAttribute('width', (string) $width);
                $xml->writeAttribute('customWidth', '1');
                $xml->endElement();
            }
            $xml->endElement();
            $xml->startElement('sheetData');
            $row = 1;
            $this->writeRow($xml, $row, ['Recipient', 'Phone (masked)', 'Broadcast ID', 'Title', 'Message', 'Result', 'Mode', 'Failure / result details', 'Queued (Asia/Manila)', 'Processed (Asia/Manila)', 'Category', 'Barangay']);
            foreach ($query->lazy(200) as $recipient) {
                if (++$row > 1048576) {
                    throw new RuntimeException('Report exceeds the Excel worksheet row limit.');
                }
                $label = match ($recipient->status) {
                    'accepted' => 'Sent (provider accepted)', 'failed' => 'Failed', 'rejected' => 'Rejected',
                    'simulated' => 'Simulated', 'unknown' => 'Unknown (verify before resend)', 'cancelled' => 'Cancelled before sending', default => ucfirst($recipient->status),
                };
                $this->writeRow($xml, $row, [
                    $recipient->contact ? trim($recipient->contact->first_name.' '.$recipient->contact->last_name) : 'Removed contact',
                    '*******'.substr($recipient->phone_number, -4), (string) $recipient->broadcast_id,
                    $recipient->broadcast->title, $recipient->broadcast->message, $label,
                    $recipient->broadcast->is_test ? 'Test' : 'Live', $recipient->error ?? '',
                    $recipient->created_at->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $recipient->processed_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
                    $recipient->contact?->category?->name ?? 'Not available',
                    $recipient->contact ? ($recipient->contact->barangay?->name ?? 'Not applicable') : 'Not available',
                ]);
                if ($row % 200 === 0) {
                    $xml->flush();
                }
            }
            $xml->endElement();
            $xml->startElement('autoFilter');
            $xml->writeAttribute('ref', 'A1:L'.$row);
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $xml->flush();
            unset($xml);
            $archive = new PharData($archivePath, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="SMS Results" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $archive->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
            unset($archive);
            app(OfficeZipHeaders::class)->normalize($archivePath);
            $response = response()->download($archivePath, 'SMS-report-'.$status.'-'.now('Asia/Manila')->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend();

            return $response;
        } catch (Throwable $exception) {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
            throw $exception;
        } finally {
            if (is_file($sheetPath)) {
                unlink($sheetPath);
            }
        }
    }

    private function writeRow(XMLWriter $xml, int $row, array $values): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $row);
        foreach ($values as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', chr(65 + $index).$row);
            $xml->writeAttribute('t', 'inlineStr');
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            // Write every value as text: names and messages cannot become spreadsheet formulas.
            $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value) ?? '');
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }
}
