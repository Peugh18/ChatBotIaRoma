<?php

namespace App\Support;

use App\Models\Product;

/**
 * Resuelve el color de variante desde texto del cliente (evita confundir nombre del producto con color).
 */
class VariantColorResolver
{
  /**
   * @return array<string, string> lowercase => canonical color from DB
   */
  public static function variantColorMap(int $productId): array
  {
    $product = Product::with('variants')->find($productId);
    if (! $product) {
      return [];
    }

    $map = [];
    foreach ($product->variants as $variant) {
      $canonical = trim((string) $variant->color);
      if ($canonical === '') {
        continue;
      }
      $map[mb_strtolower($canonical)] = $canonical;
    }

    return $map;
  }

  public static function resolve(int $productId, string $message): ?string
  {
    $map = self::variantColorMap($productId);
    if ($map === []) {
      return null;
    }

    if (preg_match('/pick_color_' . $productId . '_([^\\s]+)/i', $message, $m)) {
      $requested = mb_strtolower(urldecode($m[1]));

      return $map[$requested] ?? self::matchLoose($requested, $map);
    }

    $messageLower = mb_strtolower(trim($message));
    $product = Product::find($productId);
    $productName = $product ? mb_strtolower(trim($product->name)) : '';

    $matched = self::findColorsInText($messageLower, $map, $productName);
    if ($matched !== []) {
      return $matched[0];
    }

    if (preg_match('/(?:foto|imagen)\s+(?:de|del|en)\s+(.+)/iu', $message, $m)) {
      $tail = mb_strtolower(trim($m[1]));
      $matched = self::findColorsInText($tail, $map, $productName);
      if ($matched !== []) {
        return $matched[0];
      }
    }

    if (preg_match('/\bcolor\s+([a-záéíóúñ]+)/iu', $message, $m)) {
      $requested = mb_strtolower(trim($m[1]));

      return $map[$requested] ?? self::matchLoose($requested, $map);
    }

    return null;
  }

  /**
   * @param  array<string, string>  $map
   * @return array<int, string> canonical colors, longest match first
   */
  private static function findColorsInText(string $text, array $map, string $productName): array
  {
    $keys = array_keys($map);
    usort($keys, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

    $found = [];
    foreach ($keys as $key) {
      if ($key === $productName) {
        continue;
      }
      if (preg_match('/\b' . preg_quote($key, '/') . '\b/iu', $text)) {
        $found[] = $map[$key];
      }
    }

    return array_values(array_unique($found));
  }

  /**
   * @param  array<string, string>  $map
   */
  private static function matchLoose(string $needle, array $map): ?string
  {
    foreach ($map as $lower => $canonical) {
      if ($lower === $needle || str_contains($lower, $needle) || str_contains($needle, $lower)) {
        return $canonical;
      }
    }

    return null;
  }
}
