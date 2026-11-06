<?php

namespace Esliph\Validator\Tests\Schemas;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\V;
use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\NullSchema;

use Closure;
use InvalidArgumentException;
use Override;
use stdClass;

class NullSchemaTest extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return new NullSchema();
  }

  #[Override]
  protected function getValidValue(): mixed {
    return null;
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 'x';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return null;
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value): mixed => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value): bool => $value === null;
  }

  #[Override]
  public function test_schema_WithNullValue_ShouldFailRequiredValidation(): void {
    $schema = $this->createSchema();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new NullSchema();

    $result = $schema->safeParse($value);

    if ($value === null) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertNull($result->data);
      return;
    }

    $this->assertFalse($result->success, "Type $typeName should be invalid");
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected null, received ' . get_debug_type($value), $result->issues[0]->message, "Type $typeName");
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_default_WithNonNullValue_ShouldAlwaysFail(): void {
    $schema = (new NullSchema())->_default(5);

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected null, received int', $result->issues[0]->message);
  }

  #[Override]
  protected function messageProbe(): array {
    return ['invalid_type', 'x'];
  }

  #[Override]
  public function test_messages_WithRequiredString_ShouldUseCustomMessage(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'required'; allowed: invalid_type");

    $this->createSchema()->messages(['required' => 'Informe o valor']);
  }

  #[Override]
  public function test_messages_WithRequiredClosure_ShouldReceiveNullAndEmptyParams(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("Unknown message code 'required'; allowed: invalid_type");

    $this->createSchema()->messages(['required' => fn(mixed $value, array $params): string => 'custom']);
  }

  public function test_messages_WithInvalidTypeClosure_ShouldReceiveValueAndParams(): void {
    $received = null;
    $schema = (new NullSchema())->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'só null';
      },
    ]);

    $result = $schema->safeParse(5);

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('só null', $result->issues[0]->message);
    $this->assertSame([5, ['expected' => 'null', 'received' => 'int']], $received);
  }

  public function test_validateType_WithNullSchemaInsideArray_ShouldAcceptNullElements(): void {
    $schema = V::_array(V::null());

    $valid = $schema->safeParse([null, null]);
    $invalid = $schema->safeParse([null, 1]);

    $this->assertTrue($valid->success);
    $this->assertSame([null, null], $valid->data);
    $this->assertFalse($invalid->success);
    $this->assertCount(1, $invalid->issues);
    $this->assertSame([1], $invalid->issues[0]->path);
    $this->assertSame('invalid_type', $invalid->issues[0]->code);
  }

  public function test_validateType_WithNullSchemaFieldMissingInObject_ShouldSetNull(): void {
    $schema = V::object(['deletedAt' => V::null()]);

    $result = $schema->safeParse(new stdClass());

    $this->assertTrue($result->success);
    $this->assertTrue(property_exists($result->data, 'deletedAt'));
    $this->assertNull($result->data->deletedAt);
  }

  public function test_null_FromFactory_ShouldReturnNullSchema(): void {
    $this->assertInstanceOf(NullSchema::class, V::null());
  }
}
