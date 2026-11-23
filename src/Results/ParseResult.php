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
    /** @internal True when the failure happened after the type check. */
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
   * Failure after the type check (rules or children). The union uses
   * the flag to report the option that recognized the value's type.
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
