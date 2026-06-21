<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Funcoes de sanitizacao de entrada.
 */
final class Security
{
    /** Remove bytes nulos, caracteres de controle e espacos das pontas. */
    public static function cleanString(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        return trim($value);
    }

    /** Sanitiza recursivamente um array de entrada (apenas strings). */
    public static function cleanArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $out[$key] = self::cleanArray($value);
            } elseif (is_string($value)) {
                $out[$key] = self::cleanString($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /** Remove qualquer tag HTML (para campos que nunca devem conter markup). */
    public static function stripTags(string $value): string
    {
        return self::cleanString(strip_tags($value));
    }

    /** Normaliza para comparacao sem acentos e em maiusculas. */
    public static function normalize(string $value): string
    {
        $value = self::cleanString($value);
        $map = [
            'á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï',
            'ó','ò','ô','õ','ö','ú','ù','û','ü','ç',
            'Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï',
            'Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç',
        ];
        $rep = [
            'a','a','a','a','a','e','e','e','e','i','i','i','i',
            'o','o','o','o','o','u','u','u','u','c',
            'a','a','a','a','a','e','e','e','e','i','i','i','i',
            'o','o','o','o','o','u','u','u','u','c',
        ];
        return mb_strtoupper(trim(str_replace($map, $rep, $value)));
    }
}
