<?php

namespace Esliph\Validator\Schemas;

abstract class CoercibleSchema extends Schema {

  public function coerce(): static {
    $clone = clone $this;
    $clone->coerce = true;

    return $clone;
  }

  protected static function isBlank(mixed $value): bool {
    return is_string($value) && trim($value) === '';
  }

  /**
   * Returns the decoded JSON (objects as stdClass), or the original string
   * if its trimmed form does not start with `$opening` or the JSON is invalid.
   */
  protected static function decodeJson(string $value, string $opening): mixed {
    $trimmed = trim($value);

    if (!str_starts_with($trimmed, $opening)) {
      return $value;
    }

    $decoded = json_decode($trimmed, false);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return $value;
    }

    return $decoded;
  }
}
