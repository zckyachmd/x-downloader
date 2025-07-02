<?php

namespace App\Traits;

trait EncodesVideoKey
{
    protected static string $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function encodeVideoKey(int $tweetId, int $index): string
    {
        $binary = pack('J', $tweetId) . pack('C', $index);

        return $this->base62Encode($binary);
    }

    public function decodeVideoKey(string $encoded): ?array
    {
        try {
            $binary = $this->base62Decode($encoded);
        } catch (\Throwable) {
            return null;
        }

        if (strlen($binary) !== 9) {
            return null;
        }

        $unpackedId = unpack('Jtweet_id', substr($binary, 0, 8));
        $unpackedIx = unpack('Cindex', substr($binary, 8, 1));

        if (!$unpackedId || !$unpackedIx) {
            return null;
        }

        return [
            'tweet_id' => $unpackedId['tweet_id'],
            'index'    => $unpackedIx['index'],
        ];
    }

    protected function base62Encode(string $input): string
    {
        $num = gmp_import($input);
        $out = '';

        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = [gmp_div_q($num, 62), gmp_intval(gmp_mod($num, 62))];
            $out .= self::$alphabet[$rem];
        }

        return strrev($out);
    }

    protected function base62Decode(string $input): string
    {
        $num = gmp_init(0);

        foreach (str_split($input) as $char) {
            $pos = strpos(self::$alphabet, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException("Invalid Base62 char: $char");
            }
            $num = gmp_add(gmp_mul($num, 62), $pos);
        }

        return gmp_export($num);
    }
}
