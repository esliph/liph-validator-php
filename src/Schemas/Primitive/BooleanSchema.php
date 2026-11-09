<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\CoercibleSchema;
use Override;

final class BooleanSchema extends CoercibleSchema {

  private const array VOCABULARY = [
    'true' => true, '1' => true, 'on' => true, 'yes' => true,
    'false' => false, '0' => false, 'off' => false, 'no' => false,
  ];

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if ($value === 1) {
      return true;
    }

    if ($value === 0) {
      return false;
    }

    if (is_string($value)) {
      return self::VOCABULARY[strtolower(trim($value))] ?? $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_bool($value)) {
      return ParseResult::ok($value);
    }

    return ParseResult::fail($this->typeIssue('bool', $input, $path));
  }
}
