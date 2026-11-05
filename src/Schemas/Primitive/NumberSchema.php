<?php

namespace Esliph\Validator\Schemas\Primitive;

use Closure;
use InvalidArgumentException;
use Override;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Validation\Rule;

class NumberSchema extends CoercibleSchema {

  private const float MULTIPLE_OF_TOLERANCE = 1e-9;

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->coerce && is_string($value) && is_numeric($value)) {
      // Aritmética de string numérica: inteiro que cabe vira int, o resto vira float.
      $value = $value + 0;
    }

    if (is_int($value)) {
      return ParseResult::ok($value);
    }

    if (is_float($value)) {
      return is_finite($value) ? ParseResult::ok($value) : $this->nonFiniteIssue($value, $path);
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected number, received ' . gettype($value),
        code: 'invalid_type'
      )
    );
  }

  /**
   * @param (string|int)[] $path
   */
  protected function nonFiniteIssue(float $value, array $path): ParseResult {
    return ParseResult::fail(
      new Issue(
        path: $path,
        message: "Expected finite number, received {$value}",
        code: 'invalid_type'
      )
    );
  }

  public function min(int|float $min, string|Closure|null $message = null): static {
    return $this->gte($min, $message);
  }

  public function max(int|float $max, string|Closure|null $message = null): static {
    return $this->lte($max, $message);
  }

  public function gt(int|float $min, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'gt',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value > $params['min'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be greater than {$params['min']}",
      params: ['min' => $min]
    ));
  }

  public function gte(int|float $min, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'gte',
      code: 'too_small',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be greater than or equal to {$params['min']}",
      params: ['min' => $min]
    ));
  }

  public function lt(int|float $max, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'lt',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value < $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be less than {$params['max']}",
      params: ['max' => $max]
    ));
  }

  public function lte(int|float $max, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'lte',
      code: 'too_big',
      check: static fn(int|float $value, array $params): bool => $value <= $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be less than or equal to {$params['max']}",
      params: ['max' => $max]
    ));
  }

  public function nonnegative(string|Closure|null $message = null): static {
    return $this->gte(0, $message ?? 'Must be a non-negative number');
  }

  public function nonpositive(string|Closure|null $message = null): static {
    return $this->lte(0, $message ?? 'Must be a non-positive number');
  }

  public function positive(string|Closure|null $message = null): static {
    return $this->gt(0, $message ?? 'Must be a positive number');
  }

  public function negative(string|Closure|null $message = null): static {
    return $this->lt(0, $message ?? 'Must be a negative number');
  }

  public function between(int|float $min, int|float $max, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'between',
      code: 'out_of_range',
      check: static fn(int|float $value, array $params): bool => $value >= $params['min'] && $value <= $params['max'],
      message: $message ?? static fn(int|float $value, array $params): string => "Must be between {$params['min']} and {$params['max']}",
      params: ['min' => $min, 'max' => $max]
    ));
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
