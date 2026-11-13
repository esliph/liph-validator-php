<?php

namespace Esliph\Validator\Schemas\Primitive;

use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Results\ParseResult;
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
  protected static function messageCodes(): array {
    return [...parent::messageCodes(), 'invalid_date'];
  }

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if (is_int($value)) {
      return (new DateTimeImmutable())->setTimestamp($value);
    }

    if (is_string($value)) {
      return $this->parseDateString($value) ?? $value;
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if ($value instanceof DateTimeInterface) {
      return ParseResult::ok(DateTimeImmutable::createFromInterface($value));
    }

    if ($this->coerce && is_string($value)) {
      return $this->invalidDate($input, $path);
    }

    return ParseResult::fail($this->typeIssue('DateTimeImmutable', $input, $path));
  }

  private function parseDateString(string $value): ?DateTimeImmutable {
    try {
      $date = $this->format !== null
        ? DateTimeImmutable::createFromFormat($this->format, $value, $this->timezone)
        : new DateTimeImmutable($value, $this->timezone);
    } catch (Exception) {
      return null;
    }

    // Data impossível (ex.: 2024-02-30) só gera warning e seria normalizada para o mês seguinte.
    if ($date === false || DateTimeImmutable::getLastErrors() !== false) {
      return null;
    }

    return $date;
  }

  /**
   * @param string[] $path
   */
  private function invalidDate(mixed $input, array $path): ParseResult {
    return ParseResult::fail(
      $this->issue('invalid_date', 'Invalid date string', $input, $path, ['format' => $this->format])
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
    $minDate = $this->normalizeDate($min);

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_early',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value >= $params['min'],
      message: $message ?? "Must be on or after {$minDate->format(DateTimeInterface::ATOM)}",
      params: ['min' => $minDate]
    ));
  }

  /**
   * Limite inclusivo (<=), como `NumberSchema::max`; `before` é o estrito.
   */
  public function max(DateTimeInterface|string|int $max, string|Closure|null $message = null): static {
    $maxDate = $this->normalizeDate($max);

    return $this->addRule(new Rule(
      name: 'max',
      code: 'too_late',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value <= $params['max'],
      message: $message ?? "Must be on or before {$maxDate->format(DateTimeInterface::ATOM)}",
      params: ['max' => $maxDate]
    ));
  }

  public function after(DateTimeInterface|string|int $min, string|Closure|null $message = null): static {
    $minDate = $this->normalizeDate($min);

    return $this->addRule(new Rule(
      name: 'after',
      code: 'too_early',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value > $params['min'],
      message: $message ?? "Must be after {$minDate->format(DateTimeInterface::ATOM)}",
      params: ['min' => $minDate]
    ));
  }

  public function before(DateTimeInterface|string|int $max, string|Closure|null $message = null): static {
    $maxDate = $this->normalizeDate($max);

    return $this->addRule(new Rule(
      name: 'before',
      code: 'too_late',
      check: static fn(DateTimeImmutable $value, array $params): bool => $value < $params['max'],
      message: $message ?? "Must be before {$maxDate->format(DateTimeInterface::ATOM)}",
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
      message: $message ?? "Must be between {$minDate->format(DateTimeInterface::ATOM)} and {$maxDate->format(DateTimeInterface::ATOM)}",
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
