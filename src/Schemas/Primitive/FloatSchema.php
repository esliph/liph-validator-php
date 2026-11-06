<?php

namespace Esliph\Validator\Schemas\Primitive;

use Override;

use Esliph\Validator\Results\ParseResult;

class FloatSchema extends NumberSchema {

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    $input = $value;

    if ($this->coerce && is_string($value) && is_numeric($value)) {
      $value = (float) $value;
    }

    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $input, $path);
    }

    if (is_int($value)) {
      return ParseResult::ok((float) $value);
    }

    $received = self::describeType($input);

    return ParseResult::fail(
      $this->issue('invalid_type', "Expected float, received $received", $input, $path, [
        'expected' => 'float',
        'received' => $received,
      ])
    );
  }
}
