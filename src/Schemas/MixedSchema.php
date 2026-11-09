<?php

namespace Esliph\Validator\Schemas;

use Esliph\Validator\Results\ParseResult;
use Override;

class MixedSchema extends Schema {

  #[Override]
  protected static function messageCodes(): array {
    return ['required'];
  }

  #[Override]
  protected function parseType(mixed $value, array $path, mixed $input): ParseResult {
    return ParseResult::ok($value);
  }
}
