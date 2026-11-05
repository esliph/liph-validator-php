<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Validation\Rule;
use Closure;

abstract class Schema {

  /** @var Rule[] */
  protected array $rules = [];
  /** @var Closure(mixed)[] */
  protected array $transforms = [];
  protected bool $isOptional = false;
  protected mixed $default = null;
  protected bool $hasDefault = false;

  public function __clone(): void {
    $this->rules = array_map(
      static fn(Rule $rule): Rule => clone $rule,
      $this->rules
    );

    $this->transforms = array_values($this->transforms);

    if ($this->hasDefault && is_object($this->default)) {
      $this->default = clone $this->default;
    }
  }

  /**
   * @param string[] $path
   */
  protected function _parse(mixed $value, array $path = []): ParseResult {
    if (is_null($value)) {
      if (!$this->hasDefault) {
        if ($this->isOptional) {
          return ParseResult::ok();
        }

        return ParseResult::fail(
          new Issue(
            path: $path,
            message: 'Value is required',
            code: 'required'
          )
        );
      }

      $value = $this->default instanceof Closure ? ($this->default)() : $this->default;
    }

    $typeResult = $this->parseType($value, $path);

    if (!$typeResult->success) {
      return $typeResult;
    }

    $value = $typeResult->data;

    $issues = $this->validateRules($value, $path);

    if (!empty($issues)) {
      return ParseResult::fails($issues);
    }

    $typeResult = $this->validateType($value, $path);

    if (!$typeResult->success) {
      return $typeResult;
    }

    $value = $typeResult->data;

    $value = $this->applyTransforms($value);

    return ParseResult::ok($value);
  }

  /**
   * @param string[] $path
   */
  abstract protected function parseType(mixed $value, array $path = []): ParseResult;

  /**
   * @param string[] $path
   */
  protected function validateType(mixed $value, array $path = []): ParseResult {
    return ParseResult::ok($value);
  }

  /**
   * @param string[] $path
   * @return Issue[]
   */
  protected function validateRules(mixed $value, array $path = []): array {
    $issues = [];

    foreach ($this->rules as $rule) {
      if ($rule->validate($value) === false) {
        $issues[] = new Issue(
          path: $path,
          message: $rule->resolveMessage($value),
          code: $rule->code
        );
      }
    }

    return $issues;
  }

  public function parse(mixed $value): mixed {
    $result = $this->safeParse($value);

    if (!$result->success) {
      throw new ValidatorException($result->issues);
    }

    return $result->data;
  }

  public function isValid(mixed $value): bool {
    return $this->safeParse($value)->success;
  }

  public function safeParse(mixed $value): ParseResult {
    return $this->_parse($value, []);
  }

  public function apply(Closure $callable): static {
    return $callable($this);
  }

  public function refine(Closure $callable, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'refinement',
      code: 'custom',
      check: $callable,
      message: $message ?? 'Invalid value'
    ));
  }

  protected function addRule(Rule $rule): static {
    $clone = clone $this;
    $clone->rules[] = $rule;

    return $clone;
  }

  public function transform(Closure $callable): static {
    $clone = clone $this;
    $clone->transforms[] = $callable;

    return $clone;
  }

  public function _default(mixed $value): static {
    $clone = clone $this;
    $clone->default = $value;
    $clone->hasDefault = true;

    return $clone;
  }

  protected function applyTransforms(mixed $value): mixed {
    foreach ($this->transforms as $fn) {
      $value = $fn($value);
    }

    return $value;
  }

  public function optional(): static {
    $clone = clone $this;
    $clone->isOptional = true;

    return $clone;
  }

  protected function isAssociativeArray(array $array): bool {
    if (empty($array)) {
      return false;
    }

    return array_keys($array) !== range(0, count($array) - 1);
  }
}
