<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class IntegerSchema extends NumberSchema {

  /** 2^63: o primeiro float acima de PHP_INT_MAX (que não é representável em float). */
  private const float INT_RANGE_END = 9.2233720368547758E18;

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if (is_string($value) && preg_match(self::INT_PATTERN, trim($value)) === 1) {
      return self::toExactInt(trim($value)) ?? $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return $this->parseFloat($value, $input, $path);
    }

    if ($this->coerce && is_string($value) && preg_match(self::INT_PATTERN, trim($value)) === 1 && self::toExactInt(trim($value)) === null) {
      return $this->outOfRangeIssue($input, $path);
    }

    return ParseResult::fail($this->typeIssue('int', $input, $path));
  }

  /**
   * @param string[] $path
   */
  private function parseFloat(float $value, mixed $input, array $path): ParseResult {
    if (!is_finite($value)) {
      return $this->nonFiniteIssue($value, $input, $path);
    }

    if (fmod($value, 1.0) !== 0.0) {
      return ParseResult::fail($this->typeIssue('int', $input, $path, 'float with decimal part'));
    }

    if ($value < (float) PHP_INT_MIN || $value >= self::INT_RANGE_END) {
      return $this->outOfRangeIssue($input, $path);
    }

    return ParseResult::ok((int) $value);
  }

  /**
   * @param string[] $path
   */
  private function outOfRangeIssue(mixed $input, array $path): ParseResult {
    return ParseResult::fail($this->typeIssue('int', $input, $path, 'number outside the int range'));
  }
}
