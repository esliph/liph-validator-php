<?php

namespace Esliph\Validator\Validation;

use Closure;

readonly final class Rule {

  /**
   * @param Closure(mixed, array): bool $check
   * @param string|Closure(mixed, array): string|null $message
   */
  public function __construct(
    public string $name,
    public string $code,
    public Closure $check,
    public string|Closure|null $message = null,
    public array $params = [],
  ) {
  }

  public function validate(mixed $value): bool {
    return (bool) ($this->check)($value, $this->params);
  }

  public function resolveMessage(mixed $value): string {
    if ($this->message instanceof Closure) {
      return ($this->message)($value, $this->params) ?? '';
    }

    return $this->message ?? '';
  }
}
