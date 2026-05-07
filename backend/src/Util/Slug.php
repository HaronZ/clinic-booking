<?php
declare(strict_types=1);

namespace Clinic\Util;

/**
 * URL slug generator.
 *
 * "Dr. Ana Reyes"          → "dr-ana-reyes"
 * "Dr. Ramón Núñez"        → "dr-ramon-nunez"
 * "Annual Physical (60m)"  → "annual-physical-60m"
 * "  multiple   spaces  "  → "multiple-spaces"
 *
 * Pure function — no DB. Collision suffixing is handled at the service layer
 * because it requires querying the database.
 */
final class Slug
{
    public static function fromName(string $name): string
    {
        // 1. Lowercase
        $s = mb_strtolower(trim($name), 'UTF-8');

        // 2. Fold common Latin accents to ASCII (covers Spanish, French, etc.)
        $replacements = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $s = strtr($s, $replacements);

        // 3. Replace any non-alphanumeric run with a single hyphen
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);

        // 4. Trim leading/trailing hyphens
        $s = trim($s, '-');

        // 5. Fallback so we never return an empty string
        return $s === '' ? 'item' : $s;
    }
}
