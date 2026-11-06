<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use BackedEnum;
use InvalidArgumentException;
use Override;

/**
 * Aceita um valor de uma lista de escalares, comparado com `in_array` estrito,
 * ou um valor de backing de um `BackedEnum`, devolvendo o case.
 */
final class EnumSchema extends Schema {

  /** @var list<string|int|float|bool> */
  private readonly array $values;
  /** @var class-string<BackedEnum>|null */
  private readonly ?string $enumClass;
  private readonly bool $intBacked;
  /**
   * Montada uma vez: num union, a falha de uma opção-enum é comum e a
   * mensagem é descartada, então não pode custar O(n) a cada parse.
   */
  private readonly string $failureMessage;

  /**
   * @param list<string|int|float|bool>|array<string|int|float|bool>|class-string<BackedEnum> $values
   * @throws InvalidArgumentException se a lista for vazia ou tiver um valor que não seja escalar finito, ou se a classe não for um enum com backing e cases
   */
  public function __construct(array|string $values) {
    if (is_string($values)) {
      $this->enumClass = $values;
      [$this->values, $this->intBacked] = self::readBackedEnum($values);
    } else {
      self::assertValidList($values);

      $this->enumClass = null;
      $this->intBacked = false;
      $this->values = array_values($values);
    }

    $this->failureMessage = 'Expected one of ' . implode(', ', array_map(self::formatValue(...), $this->values));
  }

  /**
   * @return list<string|int|float|bool>
   */
  public function getValues(): array {
    return $this->values;
  }

  #[Override]
  protected static function messageCodes(): array {
    return ['required', 'invalid_enum_value'];
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->enumClass === null) {
      if (in_array($value, $this->values, true)) {
        return ParseResult::ok($value);
      }

      return $this->failure($value, $path);
    }

    if ($value instanceof $this->enumClass) {
      return ParseResult::ok($value);
    }

    // O `tryFrom` converte o tipo (`IntBacked::tryFrom('1')` acha o case) e
    // lança `TypeError` para arrays e objetos, então o tipo é checado antes.
    if ($this->intBacked ? is_int($value) : is_string($value)) {
      $case = $this->enumClass::tryFrom($value);

      if ($case !== null) {
        return ParseResult::ok($case);
      }
    }

    return $this->failure($value, $path);
  }

  /**
   * @param string[] $path
   */
  private function failure(mixed $value, array $path): ParseResult {
    return ParseResult::fail(
      $this->issue('invalid_enum_value', $this->failureMessage, $value, $path, ['values' => $this->values])
    );
  }

  /**
   * @param array<mixed> $values
   */
  private static function assertValidList(array $values): void {
    if ($values === []) {
      throw new InvalidArgumentException('enum values must not be empty');
    }

    foreach ($values as $value) {
      if (!self::isAcceptedScalar($value)) {
        throw new InvalidArgumentException('enum values must be strings, ints, bools or finite floats');
      }
    }
  }

  private static function isAcceptedScalar(mixed $value): bool {
    return is_string($value)
      || is_int($value)
      || is_bool($value)
      || (is_float($value) && is_finite($value));
  }

  /**
   * @return array{list<string|int>, bool}
   */
  private static function readBackedEnum(string $class): array {
    if (!enum_exists($class)) {
      throw new InvalidArgumentException("enum class $class does not exist");
    }

    if (!is_subclass_of($class, BackedEnum::class)) {
      throw new InvalidArgumentException("enum $class must be a backed enum");
    }

    $values = array_map(
      static fn(BackedEnum $case): string|int => $case->value,
      $class::cases()
    );

    if ($values === []) {
      throw new InvalidArgumentException("enum $class must have at least one case");
    }

    return [$values, is_int($values[0])];
  }
}
