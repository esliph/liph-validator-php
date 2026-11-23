<?php

namespace Esliph\Validator\Schemas\Primitive;

use Closure;
use InvalidArgumentException;
use Override;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Validation\Rule;

class NumberSchema extends CoercibleSchema {

  private const float MULTIPLE_OF_TOLERANCE = 1e-9;

  protected const string INT_PATTERN = '/^[+-]?\d+\z/';

  protected const string FLOAT_PATTERN = '/^[+-]?(\d+(\.\d+)?|\.\d+)([eE][+-]?\d+)?\z/';

  #[Override]
  protected static function messageCodes(): array {
    return [...parent::messageCodes(), 'not_finite'];
  }

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (!is_string($value)) {
      return $value;
    }

    $trimmed = trim($value);

    if ($trimmed === '') {
      return null;
    }

    if (preg_match(self::FLOAT_PATTERN, $trimmed) !== 1) {
      return $value;
    }

    if (strpbrk($trimmed, '.eE') === false) {
      return self::toExactInt($trimmed) ?? (float) $trimmed;
    }

    return (float) $trimmed;
  }

  /**
   * Receives a string that matches INT_PATTERN and returns the exact int, or null if it does not fit.
   */
  protected static function toExactInt(string $trimmed): ?int {
    $int = (int) $trimmed;

    if (strlen($trimmed) <= 18) {
      return $int;
    }


    $digits = ltrim(ltrim($trimmed, '+-'), '0');
    $canonical = $digits === '' ? '0' : ($trimmed[0] === '-' ? '-' : '') . $digits;

    return (string) $int === $canonical ? $int : null;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $input, $path);
    }

    return ParseResult::fail($this->typeIssue('number', $input, $path));
  }

  /**
   * `$value` is the non-finite float that goes into the text; `$input` is the original input
   * value, which goes to the message Closure.
   *
   * @param string[] $path
   */
  protected function nonFiniteIssue(float $value, mixed $input, array $path): ParseResult {
    // Not a string cast: PHP 8.5 raises a warning when NAN is cast to string.
    $text = is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');

    return ParseResult::fail(
      $this->issue('not_finite', "Expected finite number, received {$text}", $input, $path)
    );
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function min(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'min');

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? "Must be greater than or equal to {$min}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function max(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'max');

    return $this->addRule(new Rule(
      name: 'max',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? "Must be less than or equal to {$max}",
      params: ['max' => $max]
    ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function gt(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'gt');

    return $this->addRule(new Rule(
      name: 'gt',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value > $params['min'],
      message: $message ?? "Must be greater than {$min}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function gte(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'gte');

    return $this->addRule(new Rule(
      name: 'gte',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? "Must be greater than or equal to {$min}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function lt(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'lt');

    return $this->addRule(new Rule(
      name: 'lt',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value < $params['max'],
      message: $message ?? "Must be less than {$max}",
      params: ['max' => $max]
    ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  public function lte(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'lte');

    return $this->addRule(new Rule(
      name: 'lte',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? "Must be less than or equal to {$max}",
      params: ['max' => $max]
    ));
  }

  public function nonnegative(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'nonnegative',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? 'Must be a non-negative number',
      params: ['min' => 0]
    ));
  }

  public function nonpositive(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'nonpositive',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? 'Must be a non-positive number',
      params: ['max' => 0]
    ));
  }

  public function positive(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'positive',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value > $params['min'],
      message: $message ?? 'Must be a positive number',
      params: ['min' => 0]
    ));
  }

  public function negative(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'negative',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value < $params['max'],
      message: $message ?? 'Must be a negative number',
      params: ['max' => 0]
    ));
  }

  /**
   * Becomes two rules, `between_min` (`too_small`) and `between_max` (`too_big`),
   * with both bounds in params and the same message.
   *
   * @throws InvalidArgumentException if any bound is not finite or if min > max
   */
  public function between(int|float $min, int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'between');
    self::assertFiniteBound($max, 'between');

    if ($min > $max) {
      throw new InvalidArgumentException('between min must be less than or equal to max');
    }

    $params = ['min' => $min, 'max' => $max];
    $message ??= "Must be between {$min} and {$max}";

    return $this
      ->addRule(new Rule(
        name: 'between_min',
        code: 'too_small',
        check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
        message: $message,
        params: $params
      ))
      ->addRule(new Rule(
        name: 'between_max',
        code: 'too_big',
        check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
        message: $message,
        params: $params
      ));
  }

  /**
   * @throws InvalidArgumentException if the bound is not finite
   */
  private static function assertFiniteBound(int|float $n, string $method): void {
    if (!is_finite($n)) {
      throw new InvalidArgumentException("$method bound must be a finite number");
    }
  }

  /**
   * With float operands, accepts the value when the quotient is within
   * MULTIPLE_OF_TOLERANCE of an integer (0.3 is a multiple of 0.1). Quotients
   * above ~1e7 may give a false negative from float precision loss.
   *
   * @throws InvalidArgumentException if the divisor is not finite and greater than zero
   */
  public function multipleOf(int|float $divisor, string|Closure|null $message = null): static {
    if (!is_finite($divisor) || $divisor <= 0) {
      throw new InvalidArgumentException('multipleOf divisor must be a finite number greater than 0');
    }

    return $this->addRule(new Rule(
      name: 'multipleOf',
      code: 'not_multiple',
      check: static function (int|float $value, array $params): bool {
        if (is_int($value) && is_int($params['divisor'])) {
          return $value % $params['divisor'] === 0;
        }

        if (!is_finite($value)) {
          return false;
        }

        $quotient = $value / $params['divisor'];

        return abs($quotient - round($quotient)) < self::MULTIPLE_OF_TOLERANCE;
      },
      message: $message ?? "Must be a multiple of {$divisor}",
      params: ['divisor' => $divisor]
    ));
  }
}
