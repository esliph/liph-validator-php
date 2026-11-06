<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Results\ParseResult;
use Override;

/**
 * Aceita só `null`. Um default não nulo substitui o `null` antes da checagem
 * de tipo, então `V::null()->_default(5)` sempre falha.
 */
final class NullSchema extends Schema {

  #[Override]
  protected function acceptsNull(): bool {
    return true;
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($value === null) {
      return ParseResult::ok(null);
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected null, received ' . gettype($value),
        code: 'invalid_type'
      )
    );
  }
}
