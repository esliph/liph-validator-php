<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class IntegerSchema extends NumberSchema {

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
      return is_finite($value)
        ? ParseResult::fail($this->typeIssue('int', $input, $path))
        : $this->nonFiniteIssue($value, $input, $path);
    }

    if ($this->coerce && is_string($value) && preg_match(self::INT_PATTERN, trim($value)) === 1 && self::toExactInt(trim($value)) === null) {
      return $this->outOfRangeIssue($input, $path);
    }

    return ParseResult::fail($this->typeIssue('int', $input, $path));
  }

  /**
   * @param string[] $path
   */
  private function outOfRangeIssue(mixed $input, array $path): ParseResult {
    return ParseResult::fail($this->typeIssue('int', $input, $path, 'number outside the int range'));
  }
}
