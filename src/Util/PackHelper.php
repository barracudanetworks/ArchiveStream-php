<?php

declare(strict_types=1);

namespace Genkgo\ArchiveStream\Util;

use GMP;

final class PackHelper
{
    /**
     * Convert a UNIX timestamp to a DOS timestamp.
     *
     * @param \DateTimeImmutable $date
     * @return int DOS timestamp
     */
    public static function dostime(\DateTimeImmutable $date): int
    {
        // get date array for timestamp
        $d = \getdate((int)$date->format('U'));

        // set lower-bound on dates
        if ($d['year'] < 1980) {
            $d = [
                'year' => 1980, 'mon' => 1, 'mday' => 1,
                'hours' => 0, 'minutes' => 0, 'seconds' => 0
            ];
        }

        // remove extra years from 1980
        $d['year'] -= 1980;

        // return date string
        return ($d['year'] << 25) | ($d['mon'] << 21) | ($d['mday'] << 16) |
            ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
    }

    /**
     * Create a format string and argument list for pack(), then call pack() and return the result.
     *
     * @param array<int, mixed> $fields Key is format string and the value is the data to pack.
     * @return string Binary packed data returned from pack().
     */
    public static function packFields(array $fields)
    {
        $fmt = '';
        $args = [];

        // populate format string and argument list
        foreach ($fields as $field) {
            $fmt .= $field[0];
            $args[] = $field[1];
        }

        // prepend format string to argument list
        \array_unshift($args, $fmt);

        // build output string from header and compressed data
        /** @var false|string $packed */
        $packed = \pack(...$args);
        if ($packed === false) {
            throw new \UnexpectedValueException('Cannot pack fields');
        }

        return $packed;
    }

    /**
     * Split a 64-bit integer to two 32-bit integers.
     *
     * @param mixed $value Integer or GMP resource.
     * @return array<int, int|string> Containing high and low 32-bit integers.
     */
    public static function int64Split($value): array
    {
        if ($value instanceof GMP) {
            $hex = \str_pad(\gmp_strval($value, 16), 16, '0', STR_PAD_LEFT);

            $high = self::gmpConvert(\substr($hex, 0, 8), 16, 10);
            $low = self::gmpConvert(\substr($hex, 8, 8), 16, 10);
        } else {
            $left = 0xffffffff00000000;
            $right = 0x00000000ffffffff;

            $high = ($value & $left) >> 32;
            $low = $value & $right;
        }

        return [$low, $high];
    }

    /**
     * Convert a number between bases via GMP.
     *
     * @param string $num Number to convert.
     * @param int $base_a Base to convert from.
     * @param int $base_b Base to convert to.
     * @return string Number in string format.
     */
    private static function gmpConvert(string $num, int $base_a, int $base_b): string
    {
        $gmp_num = \gmp_init($num, $base_a);
        return \gmp_strval($gmp_num, $base_b);
    }
}
