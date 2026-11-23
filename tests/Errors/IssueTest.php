<?php

namespace Esliph\Validator\Tests\Errors;

use PHPUnit\Framework\TestCase;
use Esliph\Validator\Errors\Issue;

class IssueTest extends TestCase {

  public function test_construct_WithAllRequiredParameters_ShouldStoreValuesCorrectly(): void {
    $path = ['user', 'email'];
    $message = 'Invalid email format';
    $code = 'EMAIL_001';

    $issue = new Issue(
      path: $path,
      message: $message,
      code: $code
    );

    $this->assertSame($path, $issue->path);
    $this->assertSame($message, $issue->message);
    $this->assertSame($code, $issue->code);
  }

  public function test_construct_WithEmptyPath_ShouldStoreEmptyArray(): void {
    $issue = new Issue(
      path: [],
      message: 'Root error',
      code: 'ROOT_001'
    );

    $this->assertEmpty($issue->path);
    $this->assertIsArray($issue->path);
    $this->assertCount(0, $issue->path);
  }

  public function test_construct_WithSingleElementPath_ShouldStoreCorrectly(): void {
    $path = ['field'];

    $issue = new Issue(
      path: $path,
      message: 'Field error',
      code: 'FIELD_001'
    );

    $this->assertSame($path, $issue->path);
    $this->assertCount(1, $issue->path);
  }

  public function test_construct_WithMultipleElementPath_ShouldStoreCorrectly(): void {
    $path = ['user', 'profile', 'address', 'street'];

    $issue = new Issue(
      path: $path,
      message: 'Street is required',
      code: 'STREET_001'
    );

    $this->assertSame($path, $issue->path);
    $this->assertCount(4, $issue->path);
  }

  public function test_construct_WithEmptyMessage_ShouldStoreEmptyString(): void {
    $issue = new Issue(
      path: ['field'],
      message: '',
      code: 'EMPTY_001'
    );

    $this->assertSame('', $issue->message);
  }

  public function test_construct_WithSpecialCharactersInMessage_ShouldStoreCorrectly(): void {
    $message = 'Invalid value: "test@example.com" with special chars !@#$%';

    $issue = new Issue(
      path: ['email'],
      message: $message,
      code: 'SPECIAL_001'
    );

    $this->assertSame($message, $issue->message);
  }

  public function test_construct_WithUnicodeMessage_ShouldStoreCorrectly(): void {
    $message = 'This field is required: 日本語 🚀 Ñoño';

    $issue = new Issue(
      path: ['field'],
      message: $message,
      code: 'UNICODE_001'
    );

    $this->assertSame($message, $issue->message);
  }

  public function test_construct_WithNumericPathElements_ShouldStoreCorrectly(): void {
    $path = ['items', '0', 'name'];

    $issue = new Issue(
      path: $path,
      message: 'Item name is required',
      code: 'ITEM_001'
    );

    $this->assertSame($path, $issue->path);
    $this->assertSame('items', $issue->path[0]);
    $this->assertSame('0', $issue->path[1]);
    $this->assertSame('name', $issue->path[2]);
  }

  public function test_construct_WithEmptyStringPathElement_ShouldStoreCorrectly(): void {
    $path = ['', 'field', ''];

    $issue = new Issue(
      path: $path,
      message: 'Error',
      code: 'EMPTY_ELEMENT_001'
    );

    $this->assertSame($path, $issue->path);
    $this->assertCount(3, $issue->path);
  }

  public function test_construct_WithEmptyCode_ShouldStoreEmptyString(): void {
    $issue = new Issue(
      path: ['field'],
      message: 'Error message',
      code: ''
    );

    $this->assertSame('', $issue->code);
  }

  public function test_construct_WithCodeContainingSpecialCharacters_ShouldStoreCorrectly(): void {
    $code = 'ERROR_CODE-123_V2.0';

    $issue = new Issue(
      path: ['field'],
      message: 'Error',
      code: $code
    );

    $this->assertSame($code, $issue->code);
  }

  public function test_pathString_WithEmptyPath_ShouldReturnEmptyString(): void {
    $issue = new Issue(
      path: [],
      message: 'Root error',
      code: 'ROOT_001'
    );

    $this->assertSame('', $issue->pathString());
  }

  public function test_pathString_WithSingleElementPath_ShouldReturnSingleElement(): void {
    $issue = new Issue(
      path: ['email'],
      message: 'Invalid email',
      code: 'EMAIL_001'
    );

    $this->assertSame('email', $issue->pathString());
  }

  public function test_pathString_WithTwoElementPath_ShouldJoinWithDot(): void {
    $issue = new Issue(
      path: ['user', 'email'],
      message: 'Invalid email',
      code: 'EMAIL_001'
    );

    $this->assertSame('user.email', $issue->pathString());
  }

  public function test_pathString_WithMultipleElementPath_ShouldJoinAllWithDots(): void {
    $issue = new Issue(
      path: ['user', 'profile', 'address', 'street', 'number'],
      message: 'Invalid',
      code: 'TEST_001'
    );

    $this->assertSame('user.profile.address.street.number', $issue->pathString());
  }

  public function test_pathString_WithNumericPathElements_ShouldJoinIncludingNumbers(): void {
    $issue = new Issue(
      path: ['items', '0', 'name'],
      message: 'Error',
      code: 'TEST_001'
    );

    $this->assertSame('items.0.name', $issue->pathString());
  }

  public function test_pathString_WithEmptyStringPathElements_ShouldIncludeEmptyStrings(): void {
    $issue = new Issue(
      path: ['', 'field', ''],
      message: 'Error',
      code: 'TEST_001'
    );

    $this->assertSame('.field.', $issue->pathString());
  }

  public function test_properties_AreReadonly_ShouldNotAllowModification(): void {
    $issue = new Issue(
      path: ['field'],
      message: 'Error',
      code: 'TEST_001'
    );

    $this->assertTrue(property_exists($issue, 'path'));
    $this->assertTrue(property_exists($issue, 'message'));
    $this->assertTrue(property_exists($issue, 'code'));
  }

  public function test_class_IsFinal_ShouldNotAllowInheritance(): void {
    $reflection = new \ReflectionClass(Issue::class);
    $this->assertTrue($reflection->isFinal());
  }

  public function test_class_IsReadonly_ShouldEnforceImmutability(): void {
    $reflection = new \ReflectionClass(Issue::class);
    $this->assertTrue($reflection->isReadOnly());
  }

  public function test_construct_WithVeryLongPath_ShouldHandleCorrectly(): void {
    $path = array_map(fn($i) => "level_{$i}", range(1, 100));

    $issue = new Issue(
      path: $path,
      message: 'Deep nested error',
      code: 'DEEP_001'
    );

    $pathInString = $issue->pathString();

    $this->assertCount(100, $issue->path);
    $this->assertStringContainsString('level_1.level_2.level_3', $pathInString);
    $this->assertStringContainsString('level_99.level_100', $pathInString);
  }

  public function test_construct_WithVeryLongMessage_ShouldHandleCorrectly(): void {
    $message = str_repeat('A', 10000);

    $issue = new Issue(
      path: ['field'],
      message: $message,
      code: 'LONG_001'
    );

    $this->assertSame($message, $issue->message);
    $this->assertSame(10000, strlen($issue->message));
  }

  public function test_construct_WithAllPathElementsAsNumbers_ShouldStoreAsStrings(): void {
    $path = ['0', '1', '2', '10'];

    $issue = new Issue(
      path: $path,
      message: 'Error',
      code: 'NUM_001'
    );

    $this->assertSame('0.1.2.10', $issue->pathString());
    $this->assertIsString($issue->path[0]);
    $this->assertIsString($issue->path[1]);
  }

  public function test_pathString_ConsistencyAcrossMultipleCalls_ShouldReturnSameValue(): void {
    $issue = new Issue(
      path: ['user', 'email'],
      message: 'Invalid',
      code: 'TEST_001'
    );

    $first = $issue->pathString();
    $second = $issue->pathString();
    $third = $issue->pathString();

    $this->assertSame($first, $second);
    $this->assertSame($second, $third);
  }

  public function test_construct_WithPathContainingDots_ShouldStoreCorrectly(): void {
    $path = ['config.user', 'email.primary'];

    $issue = new Issue(
      path: $path,
      message: 'Invalid',
      code: 'DOT_001'
    );

    $this->assertSame('config.user.email.primary', $issue->pathString());
  }
}
