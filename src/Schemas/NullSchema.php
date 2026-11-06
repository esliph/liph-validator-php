<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Override;

/**
 * Aceita só `null`. Um default não nulo substitui o `null` antes da checagem
 * de tipo, então `V::null()->_default(5)` sempre falha.
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
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($value === null) {
      return ParseResult::ok(null);
    }

    $received = self::describeType($value);

    return ParseResult::fail(
      $this->issue('invalid_type', "Expected null, received $received", $value, $path, [
        'expected' => 'null',
        'received' => $received,
      ])
    );
  }
}
