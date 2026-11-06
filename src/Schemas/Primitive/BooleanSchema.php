<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\CoercibleSchema;
use Override;

final class BooleanSchema extends CoercibleSchema {

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if (is_bool($value)) {
      return ParseResult::ok($value);
    }

    if (!$this->coerce) {
      return $this->invalidTypeBoolean($value, $path);
    }

    $coerced = $this->coerceToBoolean($value);

    if ($coerced === null) {
      return $this->invalidTypeBoolean($value, $path);
    }

    return ParseResult::ok($coerced);
  }

  private function coerceToBoolean(mixed $value): ?bool {
    if (is_string($value)) {
      $value = mb_strtolower(trim($value));

      return match ($value) {
        'true', '1', 'yes', 'on' => true,
        'false', '0', 'no', 'off', '' => false,
        default => null,
      };
    }

    if (is_int($value)) {
      return match ($value) {
        1 => true,
        0 => false,
        default => null,
      };
    }

    return null;
  }

  /**
   * @param string[] $path
   */
  private function invalidTypeBoolean(mixed $value, array $path): ParseResult {
    $received = self::describeType($value);

    return ParseResult::fail(
      $this->issue('invalid_type', "Expected bool, received $received", $value, $path, [
        'expected' => 'bool',
        'received' => $received,
      ])
    );
  }
}
