<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class FloatSchema extends NumberSchema {

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($this->coerce && is_string($value) && is_numeric($value)) {
      $value = (float) $value;
    }

    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $input, $path);
    }

    if (is_int($value)) {
      return ParseResult::ok((float) $value);
    }

    return ParseResult::fail($this->typeIssue('float', $input, $path));
  }
}
