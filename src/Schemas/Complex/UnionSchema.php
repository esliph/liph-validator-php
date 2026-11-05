<?php

namespace Esliph\Validator\Schemas\Complex;

use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\Schema;
use Override;

final class UnionSchema extends Schema {

  /**
   * @param Schema[] $schemas
   */
  public function __construct(
    protected array $schemas = []
  ) {
    $this->schemas = $schemas;
  }

  public function __clone(): void {
    parent::__clone();

    $this->schemas = array_map(
      static fn(Schema $schema): Schema => clone $schema,
      $this->schemas
    );
  }

  #[Override]
  protected function parseType(mixed $value, array $path = []): ParseResult {
    if ($this->schemas === []) {
      return ParseResult::fail(
        new Issue(
          path: $path,
          message: 'Union has no members to match against',
          code: 'invalid_type'
        )
      );
    }

    $issues = [];

    foreach ($this->schemas as $schema) {
      $result = $schema->_parse($value, $path);

      if ($result->success) {
        return ParseResult::ok($result->data);
      }

      $issues = array_merge($issues, $result->issues);
    }

    return ParseResult::fails($issues);
  }
}
