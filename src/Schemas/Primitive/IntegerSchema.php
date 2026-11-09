<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class IntegerSchema extends NumberSchema {

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (!is_string($value)) {
      return $value;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
      return null;
    }

    if (preg_match(self::INT_PATTERN, $trimmed) === 1) {
      return self::toExactInt($trimmed) ?? $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return is_finite($value)
        ? ParseResult::fail($this->typeIssue('int', $input, $path))
        : $this->nonFiniteIssue($value, $input, $path);
    }

    if ($this->coerce && is_string($value) && self::isOutOfRangeIntString($value)) {
      return $this->outOfRangeIssue($input, $path);
    }

    return ParseResult::fail($this->typeIssue('int', $input, $path));
  }

  /**
   * Estouro exige pelo menos 19 dígitos, e espaço ou sinal só aumentam a string crua.
   */
  private static function isOutOfRangeIntString(string $value): bool {
    if (strlen($value) < 19) {
      return false;
    }

    $trimmed = trim($value);

    return preg_match(self::INT_PATTERN, $trimmed) === 1 && self::toExactInt($trimmed) === null;
  }

  /**
   * @param string[] $path
   */
  private function outOfRangeIssue(mixed $input, array $path): ParseResult {
    return ParseResult::fail($this->typeIssue('int', $input, $path, 'number outside the int range'));
  }
}
