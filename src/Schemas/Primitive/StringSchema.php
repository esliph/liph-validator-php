<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Validation\Rule;
use Closure;
use InvalidArgumentException;
use Override;
use Stringable;

final class StringSchema extends CoercibleSchema {

  /** @var list<'trim'|'lower'|'upper'> */
  private array $normalizers = [];

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (is_int($value) || is_float($value) && is_finite($value) || $value instanceof Stringable) {
      return (string) $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (is_string($value)) {
      return ParseResult::ok($value);
    }

    return ParseResult::fail($this->typeIssue('string', $input, $path));
  }

  /**
   * Aplica os normalizadores na ordem de registro, entre a checagem de tipo e
   * as regras. Nunca falha. Com UTF-8 inválido, os de caixa devolvem a string
   * intacta, porque o mbstring trocaria os bytes inválidos.
   *
   * @param string[] $path
   */
  #[Override]
  protected function validateType(mixed $value, array $path = []): ParseResult {
    foreach ($this->normalizers as $normalizer) {
      $value = match ($normalizer) {
        'trim' => trim($value),
        'lower' => mb_check_encoding($value, 'UTF-8') ? mb_strtolower($value, 'UTF-8') : $value,
        'upper' => mb_check_encoding($value, 'UTF-8') ? mb_strtoupper($value, 'UTF-8') : $value,
      };
    }

    return ParseResult::ok($value);
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function min(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'min');

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_small',
      check: static fn(string $value, array $params): bool => mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') >= $params['length'],
      message: $message ?? "Must be at least {$length} characters",
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
      check: static fn(string $value, array $params): bool => mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') <= $params['length'],
      message: $message ?? "Must be at most {$length} characters",
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
   * Formato 8-4-4-4-12 em hexa, sem conferir versão nem variante (aceita o nil).
   */
  public function uuid(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'uuid',
      code: 'invalid_format',
      check: static fn(string $value): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $value) === 1,
      message: $message ?? 'Invalid UUID'
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
      check: static fn(string $value): bool => $value !== '',
      message: $message ?? 'String must not be empty'
    ));
  }

  public function startsWith(string $prefix, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'startsWith',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_starts_with($value, $params['prefix']),
      message: $message ?? "Must start with '{$prefix}'",
      params: ['prefix' => $prefix]
    ));
  }

  public function endsWith(string $suffix, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'endsWith',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_ends_with($value, $params['suffix']),
      message: $message ?? "Must end with '{$suffix}'",
      params: ['suffix' => $suffix]
    ));
  }

  public function includes(string $substring, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'includes',
      code: 'invalid_format',
      check: static fn(string $value, array $params): bool => str_contains($value, $params['substring']),
      message: $message ?? "Must include '{$substring}'",
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
      check: static fn(string $value, array $params): bool => mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') === $params['length'],
      message: $message ?? "Must be exactly {$length} characters",
      params: ['length' => $length]
    ));
  }

  public function lowercase(string|Closure|null $message = null): static {
    // UTF-8 inválido já falha: o mbstring troca os bytes inválidos, e a comparação não bate.
    return $this->addRule(new Rule(
      name: 'lowercase',
      code: 'invalid_case',
      check: static fn(string $value): bool => mb_strtolower($value, 'UTF-8') === $value,
      message: $message ?? 'Must be all lowercase'
    ));
  }

  public function uppercase(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'uppercase',
      code: 'invalid_case',
      check: static fn(string $value): bool => mb_strtoupper($value, 'UTF-8') === $value,
      message: $message ?? 'Must be all uppercase'
    ));
  }

  public function trim(): static {
    return $this->addNormalizer('trim');
  }

  public function toLowerCase(): static {
    return $this->addNormalizer('lower');
  }

  public function toUpperCase(): static {
    return $this->addNormalizer('upper');
  }

  /**
   * @param 'trim'|'lower'|'upper' $normalizer
   */
  private function addNormalizer(string $normalizer): static {
    $clone = clone $this;
    $clone->normalizers[] = $normalizer;

    return $clone;
  }
}
