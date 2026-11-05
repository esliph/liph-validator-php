<?php

namespace Esliph\Validator\Schemas\Primitive;

use Closure;
use Override;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Validation\Rule;

class NumberSchema extends CoercibleSchema {

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if (is_int($value) || is_float($value)) {
      return ParseResult::ok($value);
    }

    if ($this->coerce && is_numeric($value)) {
      $value = str_contains($value, '.') ? (float) $value : (int) $value;

      return ParseResult::ok($value);
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Expected number, received ' . gettype($value),
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

  public function multipleOf(int|float $divisor, int $precision = 0, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'multipleOf',
      code: 'not_multiple',
      check: static function (int|float $value, array $params): bool {
        if (is_int($value) && is_int($params['divisor'])) {
          return $value % $params['divisor'] === 0;
        }

        $scale = 10 ** $params['precision'];

        $scaledValue = (int) round($value * $scale);
        $scaledDivisor = (int) round($params['divisor'] * $scale);

        return $scaledValue % $scaledDivisor === 0;
      },
      message: $message ?? static fn(int|float $value, array $params): string => "Must be a multiple of {$params['divisor']}",
      params: [
        'divisor' => $divisor,
        'precision' => $precision
      ]
    ));
  }
}
