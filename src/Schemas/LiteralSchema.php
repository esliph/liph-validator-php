<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use InvalidArgumentException;
use Override;
use UnitEnum;

/**
 * Accepts only the given value, compared with `===`: `literal(1)` rejects `1.0` and
 * `'1'`, and an enum case only matches itself.
 */
final class LiteralSchema extends Schema {

  /**
   * @throws InvalidArgumentException if the value is `NAN`, which would never match anything
   */
  public function __construct(
    private readonly string|int|float|bool|null|UnitEnum $value
  ) {
    if (is_float($value) && is_nan($value)) {
      throw new InvalidArgumentException('literal value must not be NAN');
    }
  }

  public function getValue(): string|int|float|bool|null|UnitEnum {
    return $this->value;
  }

  #[Override]
  protected static function messageCodes(): array {
    return ['required', 'invalid_literal'];
  }

  #[Override]
  protected function acceptsNull(): bool {
    return $this->value === null;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($value === $this->value) {
      return ParseResult::ok($value);
    }

    return ParseResult::fail(
      $this->issue('invalid_literal', 'Expected literal ' . self::formatValue($this->value), $value, $path, [
        'value' => $this->value,
      ])
    );
  }
}
