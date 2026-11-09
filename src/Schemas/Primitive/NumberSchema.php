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

  #[Override]
  protected static function messageCodes(): array {
    return [...parent::messageCodes(), 'not_finite'];
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    $input = $value;

    if ($this->coerce && is_string($value) && is_numeric($value)) {
      // Aritmética de string numérica: inteiro que cabe vira int, o resto vira float.
      $value = $value + 0;
    }

    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $input, $path);
    }

    return ParseResult::fail($this->typeIssue('number', $input, $path));
  }

  /**
   * `$value` é o float não finito que vai no texto; `$input`, o valor original
   * de entrada, que vai para a Closure de mensagem.
   *
   * @param string[] $path
   */
  protected function nonFiniteIssue(float $value, mixed $input, array $path): ParseResult {
    return ParseResult::fail(
      $this->issue('not_finite', "Expected finite number, received {$value}", $input, $path)
    );
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function min(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'min');

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be greater than or equal to {$params['min']}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function max(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'max');

    return $this->addRule(new Rule(
      name: 'max',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be less than or equal to {$params['max']}",
      params: ['max' => $max]
    ));
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function gt(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'gt');

    return $this->addRule(new Rule(
      name: 'gt',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value > $params['min'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be greater than {$params['min']}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function gte(int|float $min, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'gte');

    return $this->addRule(new Rule(
      name: 'gte',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be greater than or equal to {$params['min']}",
      params: ['min' => $min]
    ));
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function lt(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'lt');

    return $this->addRule(new Rule(
      name: 'lt',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value < $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be less than {$params['max']}",
      params: ['max' => $max]
    ));
  }

  /**
   * @throws InvalidArgumentException se o limite não for finito
   */
  public function lte(int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($max, 'lte');

    return $this->addRule(new Rule(
      name: 'lte',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be less than or equal to {$params['max']}",
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
   * Vira duas regras, `between_min` (`too_small`) e `between_max` (`too_big`),
   * com os dois limites em params e a mesma mensagem.
   *
   * @throws InvalidArgumentException se algum limite não for finito ou se min > max
   */
  public function between(int|float $min, int|float $max, string|Closure|null $message = null): static {
    self::assertFiniteBound($min, 'between');
    self::assertFiniteBound($max, 'between');

    if ($min > $max) {
      throw new InvalidArgumentException('between min must be less than or equal to max');
    }

    $params = ['min' => $min, 'max' => $max];
    $message ??= static fn(int|float $value, array $params): string => "Must be between {$params['min']} and {$params['max']}";

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
   * @throws InvalidArgumentException se o limite não for finito
   */
  private static function assertFiniteBound(int|float $n, string $method): void {
    if (!is_finite($n)) {
      throw new InvalidArgumentException("$method bound must be a finite number");
    }
  }

  /**
   * Com operandos float, aceita o valor quando o quociente fica a menos de
   * MULTIPLE_OF_TOLERANCE de um inteiro (0.3 é múltiplo de 0.1). Quocientes
   * acima de ~1e7 podem dar falso negativo por perda de precisão do float.
   *
   * @throws InvalidArgumentException se o divisor não for finito e maior que zero
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
      message: $message ?? static fn(int|float $value, array $params): string => "Must be a multiple of {$params['divisor']}",
      params: ['divisor' => $divisor]
    ));
  }
}
