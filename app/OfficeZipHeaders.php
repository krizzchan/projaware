<?php

namespace App;

use RuntimeException;

class OfficeZipHeaders
{
    /** Normalize Phar's version-zero ZIP entries without loading worksheet contents into memory. */
    public function normalize(string $path): void
    {
        $file = fopen($path, 'r+b');
        if ($file === false) {
            throw new RuntimeException('Unable to finalize workbook archive.');
        }
        try {
            $size = fstat($file)['size'];
            $tailSize = min($size, 65557);
            $tail = $this->readAt($file, $size - $tailSize, $tailSize);
            $end = strrpos($tail, "PK\x05\x06");
            if ($end === false || $end + 22 > strlen($tail)) {
                throw new RuntimeException('Invalid workbook archive.');
            }
            $directory = unpack('ventries/Vsize/Voffset', substr($tail, $end + 10, 10));
            if ($directory['offset'] + $directory['size'] > $size - $tailSize + $end) {
                throw new RuntimeException('Invalid workbook archive directory.');
            }
            $position = $directory['offset'];
            for ($index = 0; $index < $directory['entries']; $index++) {
                $header = $this->readAt($file, $position, 46);
                if (substr($header, 0, 4) !== "PK\x01\x02") {
                    throw new RuntimeException('Invalid workbook archive entry.');
                }
                $lengths = unpack('vname/vextra/vcomment', substr($header, 28, 6));
                $versions = unpack('vmade/vneeded', substr($header, 4, 4));
                $local = unpack('Voffset', substr($header, 42, 4))['offset'];
                $localHeader = $this->readAt($file, $local, 6);
                if (substr($localHeader, 0, 4) !== "PK\x03\x04") {
                    throw new RuntimeException('Invalid workbook local entry.');
                }
                $made = ($versions['made'] & 0xff00) | max($versions['made'] & 0xff, 20);
                $this->writeAt($file, $position + 4, pack('vv', $made, max($versions['needed'], 20)));
                $this->writeAt($file, $local + 4, pack('v', max(unpack('vversion', substr($localHeader, 4, 2))['version'], 20)));
                $position += 46 + array_sum($lengths);
            }
        } finally {
            fclose($file);
        }
    }

    /** @param resource $file */
    private function readAt($file, int $offset, int $length): string
    {
        if ($offset < 0 || fseek($file, $offset) !== 0) {
            throw new RuntimeException('Unable to read workbook archive.');
        }
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($file, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Incomplete workbook archive.');
            }
            $data .= $chunk;
        }

        return $data;
    }

    /** @param resource $file */
    private function writeAt($file, int $offset, string $data): void
    {
        if (fseek($file, $offset) !== 0 || fwrite($file, $data) !== strlen($data)) {
            throw new RuntimeException('Unable to finalize workbook archive headers.');
        }
    }
}
