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
}
