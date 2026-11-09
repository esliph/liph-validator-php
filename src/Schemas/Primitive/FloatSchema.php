<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class FloatSchema extends NumberSchema {

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if (is_string($value) && preg_match(self::FLOAT_PATTERN, trim($value)) === 1) {
      return (float) trim($value);
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $input, $path);
    }

    if (is_int($value)) {
      return ParseResult::ok((float) $value);
    }

    return ParseResult::fail($this->typeIssue('float', $input, $path));
  }
}
