<?php

namespace Model;

class DownloadToken
{
    private const DELIMITER = '|';

    public static function encode(string $relativePath, string $secret): string
    {
        $relativePath = ltrim($relativePath, '/');
        $hmac = hash_hmac('sha256', $relativePath, $secret);
        $payload = $relativePath . self::DELIMITER . $hmac;
        $token = base64_encode($payload);
        return rtrim(strtr($token, '+/', '-_'), '=');
    }

    public static function decode(string $token, string $secret): ?string
    {
        $token = strtr($token, '-_', '+/');
        $decoded = base64_decode($token, true);
        if ($decoded === false || !str_contains($decoded, self::DELIMITER)) {
            return null;
        }

        [$relativePath, $hash] = explode(self::DELIMITER, $decoded, 2);
        $expected = hash_hmac('sha256', $relativePath, $secret);

        if (!hash_equals($expected, $hash)) {
            return null;
        }

        return ltrim($relativePath, '/');
    }
}
