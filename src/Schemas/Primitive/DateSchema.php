<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Validation\Rule;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Closure;
use Exception;
use Override;

final class DateSchema extends CoercibleSchema {

  protected ?string $format = null;
  protected ?DateTimeZone $timezone = null;

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($value instanceof DateTimeInterface) {
      return ParseResult::ok(DateTimeImmutable::createFromInterface($value));
    }

    if (!$this->coerce) {
      return ParseResult::fail(
        new Issue(
          path: $path,
          message: 'Expected DateTimeImmutable, received ' . gettype($value),
          code: 'invalid_type'
        )
      );
    }

    if (is_int($value)) {
      return ParseResult::ok((new DateTimeImmutable())->setTimestamp($value));
    }

    if (!is_string($value)) {
      return ParseResult::fail(
        new Issue(
          path: $path,
          message: 'Expected DateTimeImmutable, received ' . gettype($value),
          code: 'invalid_type'
        )
      );
    }

    // Em branco, o construtor devolveria o instante atual.
    if (trim($value) === '') {
      return $this->invalidDate($path);
    }

    try {
      $date = $this->format !== null
        ? DateTimeImmutable::createFromFormat($this->format, $value, $this->timezone)
        : new DateTimeImmutable($value, $this->timezone);
    } catch (Exception) {
      return $this->invalidDate($path);
    }

    // Data impossível (ex.: 2024-02-30) só gera warning e seria normalizada para o mês seguinte.
    if ($date === false || DateTimeImmutable::getLastErrors() !== false) {
      return $this->invalidDate($path);
    }

    return ParseResult::ok($date);
  }

  /**
   * @param (string|int)[] $path
   */
  private function invalidDate(array $path): ParseResult {
    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Invalid date string',
        code: 'invalid_date'
      )
    );
  }

  public function format(string $format): static {
    $clone = clone $this;
    $clone->format = $format;

    return $clone;
  }

  public function timezone(string|DateTimeZone $timezone): static {
    $clone = clone $this;
    $clone->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

    return $clone;
  }

  /**
   * Limite inclusivo (>=), como `NumberSchema::min`; `after` é o estrito.
   */
  public function min(DateTimeInterface|string|int $min, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_early',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value >= $params['min'],
      message: $message ?? static fn(DateTimeImmutable $value, array $params): string => "Must be on or after {$params['min']->format(DateTimeInterface::ATOM)}",
      params: ['min' => $this->normalizeDate($min)]
    ));
  }

  /**
   * Limite inclusivo (<=), como `NumberSchema::max`; `before` é o estrito.
   */
  public function max(DateTimeInterface|string|int $max, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'max',
      code: 'too_late',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value <= $params['max'],
      message: $message ?? static fn(DateTimeImmutable $value, array $params): string => "Must be on or before {$params['max']->format(DateTimeInterface::ATOM)}",
      params: ['max' => $this->normalizeDate($max)]
    ));
  }

  public function after(DateTimeInterface|string|int $min, string|Closure|null $message = null): static {
    $minDate = $this->normalizeDate($min);

    return $this->addRule(new Rule(
      name: 'after',
      code: 'too_early',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value > $params['min'],
      message: $message ?? static fn(DateTimeImmutable $value, array $params): string => "Must be after {$params['min']->format(DateTimeInterface::ATOM)}",
      params: ['min' => $minDate]
    ));
  }

  public function before(DateTimeInterface|string|int $max, string|Closure|null $message = null): static {
    $maxDate = $this->normalizeDate($max);

    return $this->addRule(new Rule(
      name: 'before',
      code: 'too_late',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value < $params['max'],
      message: $message ?? static fn(DateTimeImmutable $value, array $params): string => "Must be before {$params['max']->format(DateTimeInterface::ATOM)}",
      params: ['max' => $maxDate]
    ));
  }

  public function between(DateTimeInterface|string|int $min, DateTimeInterface|string|int $max, string|Closure|null $message = null): static {
    $minDate = $this->normalizeDate($min);
    $maxDate = $this->normalizeDate($max);

    return $this->addRule(new Rule(
      name: 'between',
      code: 'out_of_range',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value >= $params['min'] && $value <= $params['max'],
      message: $message ?? static fn(DateTimeImmutable $value, array $params): string => "Must be between {$params['min']->format(DateTimeInterface::ATOM)} and {$params['max']->format(DateTimeInterface::ATOM)}",
      params: ['min' => $minDate, 'max' => $maxDate]
    ));
  }

  protected function normalizeDate(DateTimeInterface|string|int $value): DateTimeImmutable {
    if ($value instanceof DateTimeInterface) {
      return DateTimeImmutable::createFromInterface($value);
    }

    if (is_int($value)) {
      return (new DateTimeImmutable())->setTimestamp($value);
    }

    return new DateTimeImmutable($value);
  }
}
