<?php

namespace App\Support;

/**
 * Normaliza tallas en stock (mayúsculas consistentes) para comparación dinámica.
 */
class NormalizadorStockTallas
{
  /**
   * @param  array<string|int, int|string>  $stockBySize
   * @return array<string, int>
   */
  public static function normalize(array $stockBySize): array
  {
    $out = [];
    foreach ($stockBySize as $size => $qty) {
      $key = strtoupper(trim((string) $size));
      if ($key === '') {
        continue;
      }
      $out[$key] = (int) $qty;
    }

    return $out;
  }

  /**
   * @param  array<string, int>  $normalizedStock
   * @param  array<int, string>  $availableSizes
   */
  public static function resolveFromMessage(string $message, array $normalizedStock, array $availableSizes = []): ?string
  {
    $trimmed = trim($message);
    $candidate = null;

    if (preg_match('/^size_([a-z0-9]+)$/i', $trimmed, $m)) {
      $candidate = strtoupper($m[1]);
    } elseif (preg_match('/\btalla\s*([a-z0-9]+)\b/ui', $trimmed, $m)) {
      $candidate = strtoupper($m[1]);
    } elseif (preg_match('/^\s*([a-z0-9]{1,4})\s*$/ui', $trimmed, $m)) {
      $candidate = strtoupper($m[1]);
    }

    if ($candidate === null || $candidate === '') {
      return null;
    }

    if (isset($normalizedStock[$candidate])) {
      return $candidate;
    }

    $availableUpper = array_map(fn ($s) => strtoupper(trim((string) $s)), $availableSizes);
    if (in_array($candidate, $availableUpper, true)) {
      return $candidate;
    }

    foreach (array_keys($normalizedStock) as $key) {
      if (strtoupper((string) $key) === $candidate) {
        return $key;
      }
    }

    return $candidate;
  }
}
