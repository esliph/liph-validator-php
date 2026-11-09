<?php

namespace Esliph\Validator\Schemas\Complex;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Validation\Rule;
use Closure;
use InvalidArgumentException;
use Override;

final class ArraySchema extends CoercibleSchema {

  public function __construct(
    protected ?Schema $elementSchema = null
  ) {
  }

  #[Override]
  protected function coerceInput(mixed $value): mixed {
    if (self::isBlank($value)) {
      return null;
    }

    if (is_string($value)) {
      return self::decodeJson($value, '[');
    }

    return $value;
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    if (!is_array($value)) {
      return ParseResult::fail($this->typeIssue('array', $input, $path));
    }

    if ($this->isAssociativeArray($value)) {
      return ParseResult::fail($this->typeIssue('list', $input, $path, 'associative array'));
    }

    return ParseResult::ok($value);
  }

  protected function validateType(mixed $value, array $path = []): ParseResult {
    $parsedValue = [];
    $issues = [];

    if ($this->elementSchema !== null) {
      foreach ($value as $index => $item) {
        $itemPath = array_merge($path, [$index]);
        $result = $this->elementSchema->_parse($item, $itemPath);

        if (!$result->success) {
          $issues = array_merge($issues, $result->issues);
        } else {
          $parsedValue[] = $result->data;
        }
      }
    } else {
      $parsedValue = $value;
    }

    if (!empty($issues)) {
      return ParseResult::fails($issues);
    }

    return ParseResult::ok($parsedValue);
  }

  public function getItem(): ?Schema {
    return $this->elementSchema;
  }

  public function of(Schema $schema): static {
    $clone = clone $this;
    $clone->elementSchema = $schema;

    return $clone;
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function length(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'length');

    return $this->addRule(new Rule(
      name: 'length',
      code: 'invalid_length',
      check: static fn(array $value, array $params): bool => count($value) === $params['length'],
      message: $message ?? static fn(array $value, array $params): string => "Array must have exactly {$params['length']} elements",
      params: ['length' => $length]
    ));
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  public function min(int $length, string|Closure|null $message = null): static {
    self::assertCount($length, 'min');

    return $this->addRule(new Rule(
      name: 'min',
      code: 'too_small',
      check: static fn(array $value, array $params): bool => count($value) >= $params['length'],
      message: $message ?? static fn(array $value, array $params): string => "Array must have at least {$params['length']} elements",
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
      check: static fn(array $value, array $params): bool => count($value) <= $params['length'],
      message: $message ?? static fn(array $value, array $params): string => "Array must have at most {$params['length']} elements",
      params: ['length' => $length]
    ));
  }

  public function nonempty(string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'nonempty',
      code: 'too_small',
      check: static fn(array $value): bool => count($value) > 0,
      message: $message ?? 'Array must not be empty'
    ));
  }
}
