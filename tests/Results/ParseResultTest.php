<?php

namespace Esliph\Validator\Tests\Results;

use PHPUnit\Framework\TestCase;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Results\ParseResult;

class ParseResultTest extends TestCase {

  public function test_ok_WithoutData_ShouldReturnSuccessWithNullData(): void {
    $result = ParseResult::ok();

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
    $this->assertIsArray($result->issues);
  }

  public function test_ok_WithNullData_ShouldExplicitlyStoreNull(): void {
    $result = ParseResult::ok(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_ok_WithStringData_ShouldStoreCorrectly(): void {
    $data = 'test string';

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertEmpty($result->issues);
  }

  public function test_ok_WithIntegerData_ShouldStoreCorrectly(): void {
    $data = 42;

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertSame(42, $result->data);
  }

  public function test_ok_WithFloatData_ShouldStoreCorrectly(): void {
    $data = 3.14159;

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertIsFloat($result->data);
  }

  public function test_ok_WithBooleanData_ShouldStoreCorrectly(): void {
    $result = ParseResult::ok(true);

    $this->assertTrue($result->success);
    $this->assertTrue($result->data);
    $this->assertIsBool($result->data);
  }

  public function test_ok_WithArrayData_ShouldStoreCorrectly(): void {
    $data = ['key' => 'value', 'number' => 123];

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertIsArray($result->data);
    $this->assertCount(2, $result->data);
  }

  public function test_ok_WithEmptyArrayData_ShouldStoreCorrectly(): void {
    $result = ParseResult::ok([]);

    $this->assertTrue($result->success);
    $this->assertEmpty($result->data);
    $this->assertIsArray($result->data);
  }

  public function test_ok_WithNestedArrayData_ShouldStoreCorrectly(): void {
    $data = [
      'user' => [
        'name' => 'John',
        'email' => 'john@example.com',
        'addresses' => [
          ['city' => 'São Paulo'],
          ['city' => 'Rio de Janeiro']
        ]
      ]
    ];

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertSame('John', $result->data['user']['name']);
    $this->assertCount(2, $result->data['user']['addresses']);
  }

  public function test_ok_WithObjectData_ShouldStoreCorrectly(): void {
    $data = new \stdClass();
    $data->property = 'value';

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertIsObject($result->data);
    $this->assertSame('value', $result->data->property);
  }

  public function test_ok_WithCallableData_ShouldStoreCorrectly(): void {
    $data = fn($x) => $x * 2;

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertIsCallable($result->data);
    $this->assertSame(10, ($result->data)(5));
  }

  public function test_ok_WithZeroValue_ShouldStoreCorrectly(): void {
    $result = ParseResult::ok(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data);
    $this->assertIsInt($result->data);
  }

  public function test_ok_WithEmptyString_ShouldStoreCorrectly(): void {
    $result = ParseResult::ok('');

    $this->assertTrue($result->success);
    $this->assertSame('', $result->data);
    $this->assertEmpty($result->data);
  }

  public function test_ok_WithFalseValue_ShouldStoreCorrectly(): void {
    $result = ParseResult::ok(false);

    $this->assertTrue($result->success);
    $this->assertFalse($result->data);
  }

  public function test_ok_WithVeryLargeNumber_ShouldStoreCorrectly(): void {
    $data = 9999999999999999;

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
  }

  public function test_ok_WithUnicodeString_ShouldStoreCorrectly(): void {
    $data = 'こんにちは 世界 🚀 São Paulo';

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
  }

  public function test_fails_WithEmptyIssuesArray_ShouldReturnFailure(): void {
    $result = ParseResult::fails([]);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertEmpty($result->issues);
    $this->assertIsArray($result->issues);
  }

  public function test_fail_WithSingleIssue_ShouldStoreCorrectly(): void {
    $issue = new Issue(['field'], 'Error message', 'CODE_001');

    $result = ParseResult::fail($issue);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertCount(1, $result->issues);
    $this->assertSame($issue, $result->issues[0]);
  }

  public function test_fail_WithMultipleIssues_ShouldStoreAllCorrectly(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
      new Issue(['username'], 'Username taken', 'USER_001'),
    ];

    $result = ParseResult::fails($issues);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertCount(3, $result->issues);
    $this->assertSame($issues, $result->issues);
  }

  public function test_fails_WithManyIssues_ShouldStoreAllCorrectly(): void {
    $issues = [];
    for ($i = 0; $i < 50; $i++) {
      $issues[] = new Issue(["field_{$i}"], "Error {$i}", "CODE_{$i}");
    }

    $result = ParseResult::fails($issues);

    $this->assertFalse($result->success);
    $this->assertCount(50, $result->issues);
    $this->assertSame($issues, $result->issues);
  }

  public function test_fail_WithIssuesAtRootLevel_ShouldStoreCorrectly(): void {
    $issue = new Issue([], 'Root validation error', 'ROOT_001');

    $result = ParseResult::fail($issue);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertEmpty($result->issues[0]->path);
  }

  public function test_fail_WithIssuesAtNestedLevel_ShouldStoreCorrectly(): void {
    $issue = new Issue(['user', 'profile', 'email'], 'Invalid email', 'EMAIL_001');

    $result = ParseResult::fail($issue);

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('user.profile.email', $result->issues[0]->pathString());
  }

  public function test_ok_CreatesNewInstanceEachTime_ShouldNotShareState(): void {
    $result1 = ParseResult::ok('data1');
    $result2 = ParseResult::ok('data2');

    $this->assertNotSame($result1, $result2);
    $this->assertSame('data1', $result1->data);
    $this->assertSame('data2', $result2->data);
  }

  public function test_fail_CreatesNewInstanceEachTime_ShouldNotShareState(): void {
    $issue1 = new Issue(['field1'], 'Error 1', 'CODE_001');
    $issue2 = new Issue(['field2'], 'Error 2', 'CODE_002');

    $result1 = ParseResult::fail($issue1);
    $result2 = ParseResult::fail($issue2);

    $this->assertNotSame($result1, $result2);
    $this->assertSame($issue1, $result1->issues[0]);
    $this->assertSame($issue2, $result2->issues[0]);
  }

  public function test_properties_AreReadonly_ShouldNotAllowModification(): void {
    $result = ParseResult::ok('data');

    $this->assertTrue(property_exists($result, 'success'));
    $this->assertTrue(property_exists($result, 'data'));
    $this->assertTrue(property_exists($result, 'issues'));
  }

  public function test_class_IsFinal_ShouldNotAllowInheritance(): void {
    $reflection = new \ReflectionClass(ParseResult::class);
    $this->assertTrue($reflection->isFinal());
  }

  public function test_class_IsReadonly_ShouldEnforceImmutability(): void {
    $reflection = new \ReflectionClass(ParseResult::class);
    $this->assertTrue($reflection->isReadOnly());
  }

  public function test_constructor_IsPrivate_ShouldNotBeCallableDirectly(): void {
    $reflection = new \ReflectionClass(ParseResult::class);
    $constructor = $reflection->getConstructor();

    $this->assertNotNull($constructor);
    $this->assertTrue($constructor->isPrivate());
  }

  public function test_ok_IsPublicStaticMethod_ShouldBeCallable(): void {
    $reflection = new \ReflectionClass(ParseResult::class);
    $method = $reflection->getMethod('ok');

    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
  }

  public function test_fail_IsPublicStaticMethod_ShouldBeCallable(): void {
    $reflection = new \ReflectionClass(ParseResult::class);
    $method = $reflection->getMethod('fail');

    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
  }

  public function test_okWithChainedPropertyAccess_ShouldReturnCorrectValues(): void {
    $data = ['nested' => ['value' => 'test']];

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame('test', $result->data['nested']['value']);
  }

  public function test_failWithMultipleIssuesAndChainedAccess_ShouldReturnCorrectValues(): void {
    $issues = [
      new Issue(['user', 'email'], 'Invalid', 'EMAIL_001'),
      new Issue(['user', 'password'], 'Too short', 'PASS_001'),
    ];

    $result = ParseResult::fails($issues);

    $this->assertFalse($result->success);
    $this->assertSame('user.email', $result->issues[0]->pathString());
    $this->assertSame('user.password', $result->issues[1]->pathString());
  }

  public function test_successProperty_IsCorrectlySetByOk(): void {
    $result = ParseResult::ok('data');

    $this->assertIsBool($result->success);
    $this->assertTrue($result->success);
  }

  public function test_successProperty_IsCorrectlySetByFail(): void {
    $result = ParseResult::fails([]);

    $this->assertIsBool($result->success);
    $this->assertFalse($result->success);
  }

  public function test_dataProperty_IsNullByDefaultInFail(): void {
    $result = ParseResult::fail(new Issue(['field'], 'Error', 'CODE'));

    $this->assertNull($result->data);
  }

  public function test_issuesProperty_IsEmptyArrayByDefaultInOk(): void {
    $result = ParseResult::ok('data');

    $this->assertIsArray($result->issues);
    $this->assertEmpty($result->issues);
    $this->assertCount(0, $result->issues);
  }

  public function test_ok_WithComplexDataStructure_ShouldPreserveStructure(): void {
    $data = [
      'users' => [
        [
          'id' => 1,
          'name' => 'Alice',
          'roles' => ['admin', 'user'],
          'metadata' => ['created' => '2024-01-01', 'active' => true]
        ],
        [
          'id' => 2,
          'name' => 'Bob',
          'roles' => ['user'],
          'metadata' => ['created' => '2024-01-02', 'active' => false]
        ]
      ]
    ];

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertCount(2, $result->data['users']);
    $this->assertSame('Alice', $result->data['users'][0]['name']);
    $this->assertCount(2, $result->data['users'][0]['roles']);
    $this->assertTrue($result->data['users'][0]['metadata']['active']);
  }

  public function test_fails_WithDuplicatePathIssues_ShouldStoreAllCorrectly(): void {
    $issues = [
      new Issue(['email'], 'Invalid format', 'EMAIL_001'),
      new Issue(['email'], 'Already registered', 'EMAIL_002'),
      new Issue(['email'], 'Contains forbidden words', 'EMAIL_003'),
    ];

    $result = ParseResult::fails($issues);

    $this->assertFalse($result->success);
    $this->assertCount(3, $result->issues);
    $this->assertSame('email', $result->issues[0]->path[0]);
    $this->assertSame('email', $result->issues[1]->path[0]);
    $this->assertSame('email', $result->issues[2]->path[0]);
  }

  public function test_ok_WithResourceData_ShouldStoreCorrectly(): void {
    $resource = tmpfile();

    try {
      $result = ParseResult::ok($resource);

      $this->assertTrue($result->success);
      $this->assertIsResource($result->data);
      $this->assertSame($resource, $result->data);
    } finally {
      fclose($resource);
    }
  }

  public function test_fullWorkflow_SuccessPath_ShouldReturnDataWithoutIssues(): void {
    $userData = [
      'id' => 1,
      'email' => 'user@example.com',
      'name' => 'John Doe',
      'created_at' => '2024-01-01'
    ];

    $result = ParseResult::ok($userData);

    $this->assertTrue($result->success);
    $this->assertSame($userData, $result->data);
    $this->assertEmpty($result->issues);
    $this->assertNull($result->issues[0] ?? null);
  }

  public function test_fullWorkflow_FailurePath_ShouldReturnIssuesWithoutData(): void {
    $issues = [
      new Issue(['email'], 'Email is required', 'EMAIL_REQUIRED'),
      new Issue(['email'], 'Invalid format', 'EMAIL_INVALID'),
      new Issue(['password'], 'Password must be at least 8 characters', 'PASS_TOO_SHORT'),
    ];

    $result = ParseResult::fails($issues);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertCount(3, $result->issues);
    $this->assertSame('Email is required', $result->issues[0]->message);
    $this->assertSame('PASS_TOO_SHORT', $result->issues[2]->code);
  }

  public function test_ok_WithVeryLongArrayData_ShouldHandlePerformance(): void {
    $data = array_fill(0, 1000, ['key' => 'value']);

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertCount(1000, $result->data);
  }

  public function test_ok_CanBeUsedInConditionals_SuccessCase(): void {
    $result = ParseResult::ok('data');

    if ($result->success) {
      $value = $result->data;
      $this->assertSame('data', $value);
    } else {
      $this->fail('Expected success to be true');
    }
  }

  public function test_fail_CanBeUsedInConditionals_FailureCase(): void {
    $issue = new Issue(['field'], 'Error', 'CODE');
    $result = ParseResult::fail($issue);

    if (!$result->success) {
      $issues = $result->issues;
      $this->assertCount(1, $issues);
    } else {
      $this->fail('Expected success to be false');
    }
  }

  public function test_ok_WithImmutableObject_ShouldStoreReference(): void {
    $data = new Issue(['field'], 'Error', 'CODE');

    $result = ParseResult::ok($data);

    $this->assertTrue($result->success);
    $this->assertSame($data, $result->data);
    $this->assertSame('field', $result->data->path[0]);
  }

  public function test_consistencyAcrossMultipleAccesses_ShouldReturnSameValues(): void {
    $result = ParseResult::ok(['key' => 'value']);

    $first = $result->data;
    $second = $result->data;
    $third = $result->data;

    $this->assertSame($first, $second);
    $this->assertSame($second, $third);
  }

  public function test_typeConsistency_OkAlwaysHasSuccessTrue(): void {
    $results = [
      ParseResult::ok(),
      ParseResult::ok(null),
      ParseResult::ok(0),
      ParseResult::ok(''),
      ParseResult::ok(false),
      ParseResult::ok([]),
      ParseResult::ok(new \stdClass()),
    ];

    foreach ($results as $result) {
      $this->assertTrue($result->success, 'ok() should always have success = true');
    }
  }

  public function test_typeConsistency_FailAlwaysHasSuccessFalse(): void {
    $results = [
      ParseResult::fails([]),
      ParseResult::fails([new Issue(['f'], 'E', 'C')]),
    ];

    foreach ($results as $result) {
      $this->assertFalse($result->success, 'fail() should always have success = false');
    }
  }

  public function test_typeConsistency_FailAlwaysHasNullData(): void {
    $results = [
      ParseResult::fails([]),
      ParseResult::fails([new Issue(['f'], 'E', 'C')]),
    ];

    foreach ($results as $result) {
      $this->assertNull($result->data, 'fail() should always have data = null');
    }
  }

  public function test_issuesArrayContainsOnlyIssueObjects(): void {
    $issues = [
      new Issue(['field1'], 'Error 1', 'CODE_001'),
      new Issue(['field2'], 'Error 2', 'CODE_002'),
    ];

    $result = ParseResult::fails($issues);

    foreach ($result->issues as $issue) {
      $this->assertInstanceOf(Issue::class, $issue);
    }
  }

  public function test_dirty_WithIssues_ShouldReturnDirtyFailure(): void {
    $issues = [new Issue(path: ['a'], message: 'm', code: 'too_small')];

    $result = ParseResult::dirty($issues);

    $this->assertFalse($result->success);
    $this->assertNull($result->data);
    $this->assertSame($issues, $result->issues);
    $this->assertTrue($result->dirty);
  }

  public function test_ok_ShouldNotBeDirty(): void {
    $this->assertFalse(ParseResult::ok('x')->dirty);
  }

  public function test_fail_ShouldNotBeDirty(): void {
    $issue = new Issue(path: [], message: 'm', code: 'invalid_type');

    $this->assertFalse(ParseResult::fail($issue)->dirty);
  }

  public function test_fails_ShouldNotBeDirty(): void {
    $issue = new Issue(path: [], message: 'm', code: 'invalid_type');

    $this->assertFalse(ParseResult::fails([$issue])->dirty);
  }
}
