<?php

namespace Esliph\Validator;

use Esliph\Validator\Schemas\Complex\ObjectSchema;
use Esliph\Validator\Schemas\Complex\ArraySchema;
use Esliph\Validator\Schemas\Complex\UnionSchema;
use Esliph\Validator\Schemas\Primitive\BooleanSchema;
use Esliph\Validator\Schemas\Primitive\DateSchema;
use Esliph\Validator\Schemas\Primitive\StringSchema;
use Esliph\Validator\Schemas\Primitive\NumberSchema;
use Esliph\Validator\Schemas\Primitive\FloatSchema;
use Esliph\Validator\Schemas\Primitive\IntegerSchema;
use Esliph\Validator\Schemas\MixedSchema;
use Esliph\Validator\Schemas\NullSchema;
use Esliph\Validator\Schemas\LiteralSchema;
use Esliph\Validator\Schemas\Schema;
use UnitEnum;

final class V {

  public static function number(): NumberSchema {
    return new NumberSchema();
  }

  public static function int(): IntegerSchema {
    return new IntegerSchema();
  }

  public static function float(): FloatSchema {
    return new FloatSchema();
  }

  public static function string(): StringSchema {
    return new StringSchema();
  }

  public static function boolean(): BooleanSchema {
    return new BooleanSchema();
  }

  public static function date(): DateSchema {
    return new DateSchema();
  }

  public static function mixed(): MixedSchema {
    return new MixedSchema();
  }

  public static function null(): NullSchema {
    return new NullSchema();
  }

  public static function literal(string|int|float|bool|null|UnitEnum $value): LiteralSchema {
    return new LiteralSchema($value);
  }

  /**
   * @param array<string|int, Schema> $shape
   */
  public static function object(array $shape = []): ObjectSchema {
    return new ObjectSchema(shape: $shape);
  }

  public static function _array(?Schema $schema = null): ArraySchema {
    return new ArraySchema(elementSchema: $schema);
  }

  public static function union(Schema ...$schemas): UnionSchema {
    return new UnionSchema(schemas: $schemas);
  }
}
