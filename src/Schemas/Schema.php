<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Validation\Rule;
use Closure;
use InvalidArgumentException;
use UnitEnum;

abstract class Schema {

  /** @var Rule[] */
  protected array $rules = [];
  /** @var Closure(mixed)[] */
  protected array $transforms = [];
  protected bool $isOptional = false;
  protected mixed $default = null;
  protected bool $hasDefault = false;
  /** @var array<string, string|Closure> */
  protected array $messages = [];

  /**
   * @param string[] $path
   */
  protected function _parse(mixed $value, array $path = []): ParseResult {
    if (is_null($value)) {
      if ($this->hasDefault) {
        $value = $this->resolveDefault();
      } elseif ($this->isOptional) {
        return ParseResult::ok();
      } elseif (!$this->acceptsNull()) {
        return ParseResult::fail($this->issue('required', 'Value is required', $value, $path));
      }
    }

    $typeResult = $this->parseType($value, $path);

    if (!$typeResult->success) {
      return $typeResult;
    }

    $value = $typeResult->data;

    $issues = $this->validateRules($value, $path);

    if (!empty($issues)) {
      return ParseResult::dirty($issues);
    }

    $typeResult = $this->validateType($value, $path);

    if (!$typeResult->success) {
      return $typeResult->dirty ? $typeResult : ParseResult::dirty($typeResult->issues);
    }

    $value = $typeResult->data;

    $value = $this->applyTransforms($value);

    return ParseResult::ok($value);
  }

  /**
   * Sem default nem `optional`, o `null` só segue para o `parseType` quando o
   * schema o aceita como valor.
   */
  protected function acceptsNull(): bool {
    return false;
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

  /**
   * Só falhas de validação viram ValidatorException. Exceções lançadas por check
   * de regra, Closure de mensagem, transform ou default se propagam sem tratamento.
   *
   * @throws ValidatorException se o valor não passar na validação
   */
  public function parse(mixed $value): mixed {
    $result = $this->safeParse($value);

    if (!$result->success) {
      throw new ValidatorException($result->issues);
    }

    return $result->data;
  }

  /**
   * Só falhas de validação viram `false`. Exceções lançadas por check de regra,
   * Closure de mensagem, transform ou default se propagam sem tratamento.
   */
  public function isValid(mixed $value): bool {
    return $this->safeParse($value)->success;
  }

  /**
   * Só falhas de validação viram ParseResult com issues. Exceções lançadas por
   * check de regra, Closure de mensagem, transform ou default se propagam sem
   * tratamento.
   */
  public function safeParse(mixed $value): ParseResult {
    return $this->_parse($value, []);
  }

  public function apply(Closure $callable): static {
    return $callable($this);
  }

  /**
   * O check recebe só o valor, então aceita callables nativos de um argumento
   * (`is_numeric(...)`). A Closure de mensagem continua recebendo `($value, [])`.
   *
   * @param Closure(mixed): mixed $callable
   */
  public function refine(Closure $callable, string|Closure|null $message = null): static {
    return $this->addRule(new Rule(
      name: 'refinement',
      code: 'custom',
      check: static fn(mixed $value): bool => (bool) $callable($value),
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

  /**
   * Um default objeto é clonado a cada uso para que a mutação feita por quem
   * recebe o resultado não vaze para o próximo parse.
   */
  private function resolveDefault(): mixed {
    if ($this->default instanceof Closure) {
      return ($this->default)();
    }

    if (self::isCloneableDefault($this->default)) {
      return clone $this->default;
    }

    return $this->default;
  }

  /**
   * Case de enum é singleton e o PHP proíbe cloná-lo.
   *
   * @phpstan-assert-if-true object $value
   */
  private static function isCloneableDefault(mixed $value): bool {
    return is_object($value) && !$value instanceof UnitEnum;
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

  /**
   * Troca, por código, as mensagens das issues que o próprio schema emite. Uma
   * string é texto literal; uma Closure recebe o valor original de entrada e os
   * params do código. As mensagens das `Rule`s ficam fora deste mapa.
   *
   * @param array<string, string|Closure(mixed, array<string, mixed>): string> $messages
   */
  public function messages(array $messages): static {
    foreach ($messages as $code => $message) {
      if (!in_array($code, static::messageCodes(), true)) {
        throw new InvalidArgumentException(
          "Unknown message code '$code'; allowed: " . implode(', ', static::messageCodes())
        );
      }

      if (!self::isMessage($message)) {
        throw new InvalidArgumentException("Message for code '$code' must be a string or Closure");
      }
    }

    $clone = clone $this;
    $clone->messages = array_replace($this->messages, $messages);

    return $clone;
  }

  /**
   * Recebe `mixed` porque o tipo do PHPDoc de `messages()` não vale em runtime
   * para quem chama sem análise estática.
   */
  private static function isMessage(mixed $message): bool {
    return is_string($message) || $message instanceof Closure;
  }

  /**
   * Códigos que a classe emite por conta própria. Cada subclasse estende ou
   * redefine a lista, que só contém o que ela pode emitir.
   *
   * @return list<string>
   */
  protected static function messageCodes(): array {
    return ['required', 'invalid_type'];
  }

  /**
   * Ponto único de criação das issues de schema. `$value` é o valor original de
   * entrada, antes da coerção.
   *
   * @param string[] $path
   * @param array<string, mixed> $params
   */
  protected function issue(string $code, string $default, mixed $value, array $path, array $params = []): Issue {
    $message = $this->messages[$code] ?? $default;

    if ($message instanceof Closure) {
      $message = (string) $message($value, $params);
    }

    return new Issue(path: $path, message: $message, code: $code);
  }

  /**
   * @throws InvalidArgumentException se a contagem for negativa
   */
  protected static function assertCount(int $n, string $method): void {
    if ($n < 0) {
      throw new InvalidArgumentException("$method length must be a non-negative int");
    }
  }

  /**
   * Vocabulário de tipos das mensagens: `int`, `float`, `bool`, `null`, nome de
   * classe, `resource (stream)`.
   */
  protected static function describeType(mixed $value): string {
    return get_debug_type($value);
  }

  /**
   * Representação de um valor esperado nas mensagens: o case de enum sai como
   * `Classe::Case`, e o resto como `var_export`.
   */
  protected static function formatValue(mixed $value): string {
    if ($value instanceof UnitEnum) {
      return $value::class . '::' . $value->name;
    }

    return var_export($value, true);
  }

  protected function isAssociativeArray(array $array): bool {
    if (empty($array)) {
      return false;
    }

    return array_keys($array) !== range(0, count($array) - 1);
  }
}
