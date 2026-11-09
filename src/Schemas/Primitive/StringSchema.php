<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Validation\Rule;
use Closure;
use InvalidArgumentException;
use Override;

final class StringSchema extends CoercibleSchema {

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if (is_string($value)) {
      return ParseResult::ok($value);
    }

    if (
      $this->coerce
      && (is_scalar($value) || $value === null || is_object($value) && method_exists($value, '__toString'))
    ) {
      return ParseResult::ok((string) $value);
    }

    return ParseResult::fail($this->typeIssue('string', $value, $path));
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function min(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'min');

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_small',
      check: static fn(string $value, array $params): bool => mb_strlen($value) >= $params['length'],
      message: $message ?? static fn(string $value, array $params): string => "Must be at least {$params['length']} characters",
      params: ['length' => $length]
    ));
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function max(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'max');

    return $this->addRule(new Rule(
      name: 'max',
      code: 'too_big',
      check: static fn(string $value, array $params): bool => mb_strlen($value) <= $params['length'],
      message: $message ?? static fn(string $value, array $params): string => "Must be at most {$params['length']} characters",
      params: ['length' => $length]
    ));
  }

  public function email(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'email',
      code: 'invalid_format',
      check: static fn(string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
      message: $message ?? 'Invalid email address'
    ));
  }

  public function url(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'url',
      code: 'invalid_format',
      check: static fn(string $value): bool => filter_var($value, FILTER_VALIDATE_URL) !== false,
      message: $message ?? 'Invalid URL'
    ));
  }

  /**
   * @throws InvalidArgumentException se o pattern não compilar (inclusive sem delimitadores)
   */
  public function regex(string $pattern, string|Closure|null $message = null): static {
    if (@preg_match($pattern, '') === false) {
      throw new InvalidArgumentException("invalid regex pattern: $pattern");
    }

    return $this->addRule(new Rule(
      name: 'regex',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => preg_match($params['pattern'], $value) === 1,
      message: $message ?? 'Invalid format',
      params: ['pattern' => $pattern]
    ));
  }

  public function nonempty(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'nonempty',
      code: 'too_small',
      check: static fn(string $value): bool => mb_strlen($value) > 0,
      message: $message ?? 'String must not be empty'
    ));
  }

  public function startsWith(string $prefix, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'startsWith',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_starts_with($value, $params['prefix']),
      message: $message ?? static fn(string $value, array $params): string => "Must start with '{$params['prefix']}'",
      params: ['prefix' => $prefix]
    ));
  }

  public function endsWith(string $suffix, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'endsWith',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_ends_with($value, $params['suffix']),
      message: $message ?? static fn(string $value, array $params): string => "Must end with '{$params['suffix']}'",
      params: ['suffix' => $suffix]
    ));
  }

  public function includes(string $substring, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'includes',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_contains($value, $params['substring']),
      message: $message ?? static fn(string $value, array $params): string => "Must include '{$params['substring']}'",
      params: ['substring' => $substring]
    ));
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function length(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'length');

    return $this->addRule(new Rule(
      name: 'length',
      code: 'invalid_length',
      check: static fn(string $value, array $params): bool => mb_strlen($value) === $params['length'],
      message: $message ?? static fn(string $value, array $params): string => "Must be exactly {$params['length']} characters",
      params: ['length' => $length]
    ));
  }

  public function lowercase(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'lowercase',
      code: 'invalid_case',
      check: static fn(string $value): bool => mb_strtolower($value) === $value,
      message: $message ?? 'Must be all lowercase'
    ));
  }

  public function uppercase(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'uppercase',
      code: 'invalid_case',
      check: static fn(string $value): bool => mb_strtoupper($value) === $value,
      message: $message ?? 'Must be all uppercase'
    ));
  }

  public function trim(): static {
    return $this->transform(static fn(string $value): string => trim($value));
  }

  public function toLowerCase(): static {
    return $this->transform(static fn(string $value): string => mb_strtolower($value));
  }

  public function toUpperCase(): static {
    return $this->transform(static fn(string $value): string => mb_strtoupper($value));
  }
}
