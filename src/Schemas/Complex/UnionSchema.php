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
  protected static function messageCodes(): array {
    return ['required', 'invalid_union'];
  }

  /**
   * O `null` segue para as opções, e cada uma decide com o próprio `optional()`,
   * default ou `V::null()`.
   */
  #[Override]
  protected function acceptsNull(): bool {
    return true;
  }

  /**
   * A primeira opção que passa vence. Se nenhuma passa: `required` para `null`;
   * senão o resultado da primeira opção suja, que reconheceu o tipo e falhou numa
   * regra ou num filho; senão uma única `invalid_union`.
   */
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

    $firstDirty = null;

    foreach ($this->schemas as $schema) {
      $result = $schema->_parse($value, $path);

      if ($result->success) {
        return $result;
      }

      if ($firstDirty === null && $result->dirty) {
        $firstDirty = $result;
      }
    }

    if ($value === null) {
      return ParseResult::fail($this->issue('required', 'Value is required', $value, $path));
    }

    if ($firstDirty !== null) {
      return $firstDirty;
    }

    return ParseResult::fail(
      new Issue(
        path: $path,
        message: 'Invalid input',
        code: 'invalid_union'
      )
    );
  }
}
