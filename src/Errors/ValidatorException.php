<?php

namespace Esliph\Validator\Errors;

use RuntimeException;

final class ValidatorException extends RuntimeException {

  /**
   * @param Issue[] $issues
   */
  public function __construct(
    private readonly array $issues = []
  ) {
    parent::__construct($this->buildMessage(), 0, null);
  }

  /**
   * @return Issue[]
   */
  public function getIssues(): array {
    return $this->issues;
  }

  public function getFirstIssue(): ?Issue {
    return $this->issues[0] ?? null;
  }

  /**
   * @return array<string, string[]>
   */
  public function getMessagesByPath(): array {
    $grouped = $this->getIssuesByPath();
    $result = [];

    foreach ($grouped as $path => $issues) {
      $result[$path] = array_map(
        static fn(Issue $e): string => $e->message,
        $issues
      );
    }

    return $result;
  }

  /**
   * @return array<string, string>
   */
  public function getFlatMessages(): array {
    $grouped = $this->getIssuesByPath();
    $result = [];

    foreach ($grouped as $path => $issues) {
      $result[$path] = $issues[0]->message;
    }

    return $result;
  }

  public function hasIssueAt(string $path): bool {
    $grouped = $this->getIssuesByPath();

    return isset($grouped[$path]);
  }

  /**
   * @return Issue[]
   */
  public function getIssuesAt(string $path): array {
    $grouped = $this->getIssuesByPath();

    return $grouped[$path] ?? [];
  }

  /**
   * @return array<int, array{code: string, message: string, path: string[]}>
   */
  public function toArray(): array {
    return array_map(
      static fn(Issue $e): array => [
        'path' => $e->path,
        'message' => $e->message,
        'code' => $e->code,
      ],
      $this->issues
    );
  }

  public function toJson(): string {
    return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<string, Issue[]>
   */
  public function getIssuesByPath(): array {
    $grouped = [];

    foreach ($this->issues as $issue) {
      $key = $issue->pathString();

      if (!isset($grouped[$key])) {
        $grouped[$key] = [];
      }

      $grouped[$key][] = $issue;
    }

    return $grouped;
  }

  private function buildMessage(): string {
    $lines = array_map(
      static fn(Issue $e): string => '[' . ($e->path === [] ? '(root)' : $e->pathString()) . "] {$e->message} (code: {$e->code})",
      $this->issues
    );

    return 'Validation failed: ' . implode(' | ', $lines);
  }
}
