<?php

namespace App\Support;

use App\Models\DigitalProfile;
use App\Models\QrCode;

/**
 * Short public codes for profiles and QR codes. They use an unambiguous
 * alphabet (no 0/O, 1/I/L) so they can be typed from a printed card.
 */
final class ProfileCodes
{
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /** Profile card identifier such as "P-7K2M9QXA". */
    public static function profileIdentifier(): string
    {
        do {
            $code = 'P-'.self::random(8);
        } while (DigitalProfile::withTrashed()->where('card_identifier', $code)->exists());

        return $code;
    }

    /** Short token for /q/{code} links. */
    public static function qrCode(): string
    {
        do {
            $code = strtolower(self::random(10));
        } while (QrCode::where('code', $code)->exists());

        return $code;
    }

    public static function random(int $length): string
    {
        $out = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
