<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use BackedEnum;
use InvalidArgumentException;
use Override;

/**
 * Accepts a value from a list of scalars or a backing value of a
 * `BackedEnum`, returning the case.
 *
 * The list is searched by type, keeping the `===` semantics: int in a map of
 * ints, string in a map of strings (the canonical numeric string becomes an int key
 * in PHP, and the `isset` lookup applies the same conversion), and bool/float in a
 * strict `in_array` over the few remaining values.
 */
final class EnumSchema extends Schema {

  /** @var list<string|int|float|bool> */
  private readonly array $values;
  /** @var array<int, true> */
  private readonly array $intSet;
  /** @var array<string|int, true> */
  private readonly array $stringSet;
  /** @var list<bool|float> */
  private readonly array $otherValues;
  /** @var class-string<BackedEnum>|null */
  private readonly ?string $enumClass;
  private readonly bool $intBacked;
  /**
   * Built once: in a union, the failure of an enum option is common and the
   * message is discarded, so it must not cost O(n) on every parse.
   */
  private readonly string $failureMessage;

  /**
   * @param list<string|int|float|bool>|array<string|int|float|bool>|class-string<BackedEnum> $values
   * @throws InvalidArgumentException if the list is empty or has a value that is not a finite scalar, or if the class is not an enum with a backing type and cases
   */
  public function __construct(array|string $values) {
    if (is_string($values)) {
      $this->enumClass = $values;
      [$this->values, $this->intBacked] = self::readBackedEnum($values);
      $this->intSet = [];
      $this->stringSet = [];
      $this->otherValues = [];
    } else {
      self::assertValidList($values);

      $this->enumClass = null;
      $this->intBacked = false;
      $this->values = array_values($values);

      $intSet = [];
      $stringSet = [];
      $otherValues = [];

      foreach ($this->values as $value) {
        if (is_int($value)) {
          $intSet[$value] = true;
        } elseif (is_string($value)) {
          $stringSet[$value] = true;
        } else {
          $otherValues[] = $value;
        }
      }

      $this->intSet = $intSet;
      $this->stringSet = $stringSet;
      $this->otherValues = $otherValues;
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
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($this->enumClass === null) {
      $found = match (true) {
        is_int($value) => isset($this->intSet[$value]),
        is_string($value) => isset($this->stringSet[$value]),
        default => in_array($value, $this->otherValues, true),
      };

      if ($found) {
        return ParseResult::ok($value);
      }

      return $this->failure($value, $path);
    }

    if ($value instanceof $this->enumClass) {
      return ParseResult::ok($value);
    }

    // `tryFrom` converts the type (`IntBacked::tryFrom('1')` finds the case) and
    // throws `TypeError` for arrays and objects, so the type is checked first.
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
