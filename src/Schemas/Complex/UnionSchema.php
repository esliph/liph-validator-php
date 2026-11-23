<?php

namespace Esliph\Validator\Schemas\Complex;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\Schema;
use InvalidArgumentException;
use Override;

final class UnionSchema extends Schema {

  /** @var list<Schema> */
  protected array $schemas;

  /**
   * @param Schema[] $schemas
   *
   * @throws InvalidArgumentException with fewer than 2 options
   */
  public function __construct(array $schemas = []) {
    if (count($schemas) < 2) {
      throw new InvalidArgumentException('union must have at least 2 options');
    }

    $this->schemas = array_values($schemas);
  }

  /**
   * @return list<Schema>
   */
  public function getOptions(): array {
    return $this->schemas;
  }

  #[Override]
  protected static function messageCodes(): array {
    return ['required', 'invalid_union'];
  }

  /**
   * `null` goes on to the options, and each one decides with its own `optional()`,
   * default or `V::null()`.
   */
  #[Override]
  protected function acceptsNull(): bool {
    return true;
  }

  /**
   * The first option that passes wins. If none passes: `required` for `null`;
   * otherwise the result of the first dirty option, which recognized the type and failed on a
   * rule or a child; otherwise a single `invalid_union`.
   */
  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
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

    return ParseResult::fail($this->issue('invalid_union', 'Invalid input', $value, $path));
  }
}
