<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pembuat kode QR (mode byte, koreksi galat level M, versi 1–10) tanpa dependensi tambahan.
 * Cukup untuk tautan verifikasi surat (hingga 213 byte).
 */
final class QrCode
{
    /**
     * Per versi (level M): [EC codeword per blok, jumlah blok grup 1, data per blok grup 1, jumlah blok grup 2, data per blok grup 2].
     *
     * @var array<int, array{int, int, int, int, int}>
     */
    private const BLOCKS = [
        1 => [10, 1, 16, 0, 0], 2 => [16, 1, 28, 0, 0], 3 => [26, 1, 44, 0, 0], 4 => [18, 2, 32, 0, 0], 5 => [24, 2, 43, 0, 0],
        6 => [16, 4, 27, 0, 0], 7 => [18, 4, 31, 0, 0], 8 => [22, 2, 38, 2, 39], 9 => [22, 3, 36, 2, 37], 10 => [26, 4, 43, 1, 44],
    ];

    /**
     * @var array<int, array<int, int>>
     */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /**
     * Matriks modul (true = gelap) untuk teks, tanpa zona hening.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = self::pickVersion(count($bytes));
        $size = 17 + 4 * $version;

        $codewords = self::interleave(self::dataCodewords($bytes, $version), $version);
        $bits = [];

        foreach ($codewords as $codeword) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($codeword >> $i) & 1;
            }
        }

        [$modules, $function] = self::functionPatterns($version, $size);
        self::placeData($modules, $function, $bits, $size);

        $best = null;
        $bestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            self::applyMask($candidate, $function, $mask, $size);
            self::drawFormat($candidate, $mask, $size);
            $penalty = self::penalty($candidate, $size);

            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Gambar PNG (biner) kode QR dengan zona hening.
     */
    public static function png(string $text, int $scale = 6, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $pixels = ($size + 2 * $quiet) * $scale;

        $image = imagecreate($pixels, $pixels);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $pixels, $pixels, $white);

        foreach ($matrix as $row => $line) {
            foreach ($line as $col => $dark) {
                if ($dark) {
                    $x = ($col + $quiet) * $scale;
                    $y = ($row + $quiet) * $scale;
                    imagefilledrectangle($image, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    public static function dataUri(string $text, int $scale = 6): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($text, $scale));
    }

    private static function pickVersion(int $length): int
    {
        foreach (self::BLOCKS as $version => [, $g1, $d1, $g2, $d2]) {
            $headerBits = 4 + ($version < 10 ? 8 : 16);

            if ((int) ceil(($headerBits + 8 * $length) / 8) <= $g1 * $d1 + $g2 * $d2) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Teks terlalu panjang untuk kode QR.');
    }

    /**
     * @param  array<int, int>  $bytes
     * @return array<int, int>
     */
    private static function dataCodewords(array $bytes, int $version): array
    {
        [, $g1, $d1, $g2, $d2] = self::BLOCKS[$version];
        $capacity = $g1 * $d1 + $g2 * $d2;

        $bits = [0, 1, 0, 0];
        $countBits = $version < 10 ? 8 : 16;

        for ($i = $countBits - 1; $i >= 0; $i--) {
            $bits[] = (count($bytes) >> $i) & 1;
        }

        foreach ($bytes as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        for ($i = 0; $i < 4 && count($bits) < $capacity * 8; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];

        foreach (array_chunk($bits, 8) as $chunk) {
            $codewords[] = bindec(implode('', $chunk));
        }

        for ($pad = 0; count($codewords) < $capacity; $pad++) {
            $codewords[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        return $codewords;
    }

    /**
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private static function interleave(array $data, int $version): array
    {
        [$ecLength, $g1, $d1, $g2, $d2] = self::BLOCKS[$version];

        $sizes = array_merge(array_fill(0, $g1, $d1), array_fill(0, $g2, $d2));
        $divisor = self::reedSolomonDivisor($ecLength);
        $blocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($sizes as $length) {
            $block = array_slice($data, $offset, $length);
            $offset += $length;
            $blocks[] = $block;
            $ecBlocks[] = self::reedSolomonRemainder($block, $divisor);
        }

        $result = [];

        for ($i = 0; $i < max($d1, $d2); $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private static function reedSolomonDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::multiply($result[$j], $root);

                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }

            $root = self::multiply($root, 0x02);
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $data
     * @param  array<int, int>  $divisor
     * @return array<int, int>
     */
    private static function reedSolomonRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);

        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;

            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::multiply($coefficient, $factor);
            }
        }

        return $result;
    }

    private static function multiply(int $x, int $y): int
    {
        $z = 0;

        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    /**
     * @return array{array<int, array<int, bool>>, array<int, array<int, bool>>}
     */
    private static function functionPatterns(int $version, int $size): array
    {
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));

        $set = function (int $col, int $row, bool $dark) use (&$modules, &$function, $size): void {
            if ($col >= 0 && $col < $size && $row >= 0 && $row < $size) {
                $modules[$row][$col] = $dark;
                $function[$row][$col] = true;
            }
        };

        for ($i = 0; $i < $size; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }

        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$left, $top]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                    $dark = $inside && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                    $set($left + $dx, $top + $dy, $dark);
                }
            }
        }

        $positions = self::ALIGNMENT[$version];
        $last = count($positions) - 1;

        foreach ($positions as $i => $cy) {
            foreach ($positions as $j => $cx) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }

                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Area format (diisi ulang per topeng) dan modul gelap tetap.
        $reserve = function (int $col, int $row) use (&$function, $set): void {
            if (! $function[$row][$col]) {
                $set($col, $row, false);
            }
        };

        for ($i = 0; $i < 9; $i++) {
            $reserve(8, $i);
            $reserve($i, 8);
        }

        for ($i = 0; $i < 8; $i++) {
            $reserve($size - 1 - $i, 8);
            $reserve(8, $size - 1 - $i);
        }

        $set(8, $size - 8, true);

        if ($version >= 7) {
            $rem = $version;

            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }

            $bits = ($version << 12) | $rem;

            for ($i = 0; $i < 18; $i++) {
                $dark = (($bits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $dark);
                $set($b, $a, $dark);
            }
        }

        return [$modules, $function];
    }

    /**
     * @param  array<int, array<int, bool>>  $modules
     * @param  array<int, array<int, bool>>  $function
     * @param  array<int, int>  $bits
     */
    private static function placeData(array &$modules, array $function, array $bits, int $size): void
    {
        $index = 0;
        $total = count($bits);
        $upward = true;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }

            for ($i = 0; $i < $size; $i++) {
                $row = $upward ? $size - 1 - $i : $i;

                for ($j = 0; $j < 2; $j++) {
                    $c = $col - $j;

                    if (! $function[$row][$c]) {
                        $modules[$row][$c] = $index < $total && $bits[$index] === 1;
                        $index++;
                    }
                }
            }

            $upward = ! $upward;
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $modules
     * @param  array<int, array<int, bool>>  $function
     */
    private static function applyMask(array &$modules, array $function, int $mask, int $size): void
    {
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($function[$r][$c]) {
                    continue;
                }

                $invert = match ($mask) {
                    0 => ($r + $c) % 2 === 0,
                    1 => $r % 2 === 0,
                    2 => $c % 3 === 0,
                    3 => ($r + $c) % 3 === 0,
                    4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                    5 => ($r * $c) % 2 + ($r * $c) % 3 === 0,
                    6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
                    default => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
                };

                if ($invert) {
                    $modules[$r][$c] = ! $modules[$r][$c];
                }
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $modules
     */
    private static function drawFormat(array &$modules, int $mask, int $size): void
    {
        $data = $mask; // level M = 00
        $rem = $data;

        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }

        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $modules[$i][8] = $bit($i);
        }

        $modules[7][8] = $bit(6);
        $modules[8][8] = $bit(7);
        $modules[8][7] = $bit(8);

        for ($i = 9; $i < 15; $i++) {
            $modules[8][14 - $i] = $bit($i);
        }

        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = $bit($i);
        }

        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = $bit($i);
        }

        $modules[$size - 8][8] = true;
    }

    /**
     * @param  array<int, array<int, bool>>  $modules
     */
    private static function penalty(array $modules, int $size): int
    {
        $score = 0;
        $dark = 0;

        $lines = [];

        for ($i = 0; $i < $size; $i++) {
            $lines[] = $modules[$i];
            $lines[] = array_column($modules, $i);
        }

        foreach ($lines as $line) {
            $run = 1;

            for ($i = 1; $i <= $size; $i++) {
                if ($i < $size && $line[$i] === $line[$i - 1]) {
                    $run++;

                    continue;
                }

                if ($run >= 5) {
                    $score += 3 + $run - 5;
                }

                $run = 1;
            }

            $text = implode('', array_map(fn (bool $v): string => $v ? '1' : '0', $line));
            $score += 40 * (substr_count($text, '10111010000') + substr_count($text, '00001011101'));
        }

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $dark += $modules[$r][$c] ? 1 : 0;

                if ($r + 1 < $size && $c + 1 < $size && $modules[$r][$c] === $modules[$r][$c + 1] && $modules[$r][$c] === $modules[$r + 1][$c] && $modules[$r][$c] === $modules[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        return $score + 10 * (int) floor(abs($dark * 100 / ($size * $size) - 50) / 5);
    }
}
