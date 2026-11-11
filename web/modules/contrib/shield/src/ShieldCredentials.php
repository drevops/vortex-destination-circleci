<?php

namespace Drupal\shield;

/**
 * Encodes and decodes the credential lists of the Shield credential provider.
 *
 * The provider stores its usernames and passwords in two string settings so
 * that they can be overridden from settings.php and exported as plain config.
 * Several pairs are stored as a JSON list in each setting, one entry per pair
 * in the same order, which lets a username or password contain any character.
 *
 * A value that is not such a list is a single plain value. That keeps a site
 * working before its update hooks have run and keeps settings.php overrides
 * that assign a plain string valid.
 */
final class ShieldCredentials {

  /**
   * Encodes a list of usernames or passwords for storage.
   *
   * @param string[] $values
   *   The values, in pair order.
   *
   * @return string
   *   The encoded list.
   */
  public static function encode(array $values): string {
    return json_encode(array_map('strval', array_values($values)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Decodes a stored list of usernames or passwords.
   *
   * @param mixed $value
   *   The stored value.
   *
   * @return string[]
   *   The values, in pair order. A plain value yields a single entry; NULL
   *   yields no entries.
   */
  public static function decode($value): array {
    if ($value === NULL) {
      return [];
    }
    $value = (string) $value;
    $decoded = self::decodeList($value);
    return $decoded ?? [$value];
  }

  /**
   * Checks whether a stored value is an encoded list.
   *
   * @param mixed $value
   *   The stored value.
   *
   * @return bool
   *   TRUE if the value is an encoded list, FALSE if it is a plain value.
   */
  public static function isEncoded($value): bool {
    return is_string($value) && self::decodeList($value) !== NULL;
  }

  /**
   * Splits the lines of a form field into a list of values.
   *
   * @param string $text
   *   The submitted text, one value per line.
   * @param bool $trim
   *   Whether to trim each line. Usernames are trimmed, passwords are not.
   *
   * @return string[]
   *   The values. Trailing empty lines, as left by a textarea, are dropped.
   */
  public static function splitLines(string $text, bool $trim): array {
    $lines = preg_split('/\R/u', $text) ?: [];
    if ($trim) {
      $lines = array_map('trim', $lines);
    }
    while ($lines && end($lines) === '') {
      array_pop($lines);
    }
    return array_values($lines);
  }

  /**
   * Decodes a value if it is a JSON list of strings.
   *
   * @param string $value
   *   The stored value.
   *
   * @return string[]|null
   *   The list, or NULL if the value is not one.
   */
  private static function decodeList(string $value): ?array {
    if (!str_starts_with($value, '[')) {
      return NULL;
    }
    $decoded = json_decode($value, TRUE);
    if (!is_array($decoded) || array_values($decoded) !== $decoded) {
      return NULL;
    }
    foreach ($decoded as $entry) {
      if (!is_string($entry)) {
        return NULL;
      }
    }
    return $decoded;
  }

}
