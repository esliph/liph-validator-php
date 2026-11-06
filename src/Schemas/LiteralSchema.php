<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Results\ParseResult;
use InvalidArgumentException;
use Override;
use UnitEnum;

/**
 * Aceita só o valor dado, comparado com `===`: `literal(1)` rejeita `1.0` e
 * `'1'`, e um case de enum só casa com ele mesmo.
 */
final class LiteralSchema extends Schema {

  /**
   * @throws InvalidArgumentException se o valor for `NAN`, que nunca casaria com nada
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
  protected function acceptsNull(): bool {
    return $this->value === null;
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($value === $this->value) {
      return ParseResult::ok($value);
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected literal ' . self::formatValue($this->value),
        code: 'invalid_literal'
      )
    );
  }
}
