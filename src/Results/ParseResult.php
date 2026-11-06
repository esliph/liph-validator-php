<?php

namespace Esliph\Validator\Results;

use Esliph\Validator\Errors\Issue;

readonly final class ParseResult {

  /**
   * @param Issue[] $issues
   */
  private function __construct(
    public bool $success,
    public mixed $data = null,
    public array $issues = [],
    /** @internal Verdadeiro quando a falha aconteceu depois da checagem de tipo. */
    public bool $dirty = false
  ) {
  }

  public static function ok(mixed $data = null): self {
    return new self(
      success: true,
      data: $data
    );
  }

  public static function fail(Issue $issue): self {
    return new self(
      success: false,
      issues: [$issue]
    );
  }

  /**
   * @param Issue[] $issues
   */
  public static function fails(array $issues): self {
    return new self(
      success: false,
      issues: $issues
    );
  }

  /**
   * Falha ocorrida depois da checagem de tipo (regras ou filhos). O union usa
   * a marca para relatar a opção que reconheceu o tipo do valor.
   *
   * @internal
   * @param Issue[] $issues
   */
  public static function dirty(array $issues): self {
    return new self(
      success: false,
      issues: $issues,
      dirty: true
    );
  }
}
