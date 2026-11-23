<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Override;

/**
 * Accepts only `null`. A non-null default replaces the `null` before the type
 * check, so `V::null()->_default(5)` always fails.
 */
final class NullSchema extends Schema {

  #[Override]
  protected static function messageCodes(): array {
    return ['invalid_type'];
  }

  #[Override]
  protected function acceptsNull(): bool {
    return true;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($value === null) {
      return ParseResult::ok(null);
    }

    return ParseResult::fail($this->typeIssue('null', $value, $path));
  }
}
