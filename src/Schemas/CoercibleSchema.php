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
   * Devolve o JSON decodificado (objetos como stdClass), ou a string original
   * se ela aparada não começar por `$opening` ou o JSON for inválido.
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
