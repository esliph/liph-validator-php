<?php

namespace Esliph\Validator\Tests\Errors;

use PHPUnit\Framework\TestCase;
use Esliph\Validator\Errors\Issue;
use Esliph\Validator\Errors\ValidatorException;

class ValidatorExceptionTest extends TestCase {

  public function test_construct_WithEmptyIssuesArray_ShouldCreateExceptionWithDefaultMessage(): void {
    $exception = new ValidatorException();

    $this->assertEmpty($exception->getIssues());
    $this->assertIsArray($exception->getIssues());
    $this->assertCount(0, $exception->getIssues());
    $this->assertSame('Validation failed: ', $exception->getMessage());
  }

  public function test_construct_WithDefaultIssuesParameter_ShouldHaveEmptyArray(): void {
    $exception = new ValidatorException([]);

    $this->assertEmpty($exception->getIssues());
  }

  public function test_construct_WithSingleIssue_ShouldStoreCorrectly(): void {
    $issue = new Issue(
      path: ['email'],
      message: 'Invalid email',
      code: 'EMAIL_001'
    );

    $exception = new ValidatorException([$issue]);

    $this->assertCount(1, $exception->getIssues());
    $this->assertSame($issue, $exception->getIssues()[0]);
  }

  public function test_construct_WithMultipleIssues_ShouldStoreAllCorrectly(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
      new Issue(['username'], 'Username already exists', 'USER_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertCount(3, $exception->getIssues());
    $this->assertSame($issues, $exception->getIssues());
  }

  public function test_construct_BuildsMessageFromIssues_ShouldIncludeAllIssueData(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);

    $message = $exception->getMessage();
    $this->assertStringContainsString('Validation failed:', $message);
    $this->assertStringContainsString('[email]', $message);
    $this->assertStringContainsString('Invalid email', $message);
    $this->assertStringContainsString('EMAIL_001', $message);
    $this->assertStringContainsString('[password]', $message);
    $this->assertStringContainsString('Password too short', $message);
    $this->assertStringContainsString('PASS_001', $message);
  }

  public function test_construct_WithRootLevelIssue_ShouldShowRootInMessage(): void {
    $issue = new Issue([], 'Root error', 'ROOT_001');

    $exception = new ValidatorException([$issue]);

    $message = $exception->getMessage();
    $this->assertStringContainsString('[(root)]', $message);
    $this->assertStringContainsString('Root error', $message);
  }

  public function test_construct_WithIssueAtFirstListIndex_ShouldShowIndexInsteadOfRoot(): void {
    $issue = new Issue([0], 'Item error', 'invalid_type');

    $exception = new ValidatorException([$issue]);

    $this->assertStringContainsString('[0] Item error', $exception->getMessage());
    $this->assertStringNotContainsString('(root)', $exception->getMessage());
  }

  public function test_getIssues_ShouldReturnAllStoredIssues(): void {
    $issues = [
      new Issue(['field1'], 'Error 1', 'CODE_001'),
      new Issue(['field2'], 'Error 2', 'CODE_002'),
    ];

    $exception = new ValidatorException($issues);
    $retrieved = $exception->getIssues();

    $this->assertSame($issues, $retrieved);
    $this->assertCount(2, $retrieved);
  }

  public function test_getFirstIssue_WithIssues_ShouldReturnFirstOne(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);

    $first = $exception->getFirstIssue();
    $this->assertNotNull($first);
    $this->assertSame($issues[0], $first);
    $this->assertSame('email', $first->path[0]);
  }

  public function test_getFirstIssue_WithoutIssues_ShouldReturnNull(): void {
    $exception = new ValidatorException([]);

    $first = $exception->getFirstIssue();
    $this->assertNull($first);
  }

  public function test_getFirstIssue_WithSingleIssue_ShouldReturnThatIssue(): void {
    $issue = new Issue(['field'], 'Error', 'CODE_001');
    $exception = new ValidatorException([$issue]);

    $first = $exception->getFirstIssue();
    $this->assertSame($issue, $first);
  }

  public function test_getMessagesByPath_WithoutIssues_ShouldReturnEmptyArray(): void {
    $exception = new ValidatorException([]);

    $messages = $exception->getMessagesByPath();

    $this->assertIsArray($messages);
    $this->assertEmpty($messages);
  }

  public function test_getMessagesByPath_WithSingleIssuePerPath_ShouldReturnCorrectStructure(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getMessagesByPath();

    $this->assertCount(2, $messages);
    $this->assertArrayHasKey('email', $messages);
    $this->assertArrayHasKey('password', $messages);
    $this->assertSame(['Invalid email'], $messages['email']);
    $this->assertSame(['Password too short'], $messages['password']);
  }

  public function test_getMessagesByPath_WithMultipleIssuesSamePath_ShouldGroupCorrectly(): void {
    $issues = [
      new Issue(['email'], 'Invalid email format', 'EMAIL_001'),
      new Issue(['email'], 'Email already registered', 'EMAIL_002'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getMessagesByPath();

    $this->assertCount(2, $messages);
    $this->assertCount(2, $messages['email']);
    $this->assertCount(1, $messages['password']);
    $this->assertSame('Invalid email format', $messages['email'][0]);
    $this->assertSame('Email already registered', $messages['email'][1]);
  }

  public function test_getMessagesByPath_WithNestedPaths_ShouldUsePathString(): void {
    $issues = [
      new Issue(['user', 'email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['user', 'profile', 'age'], 'Age must be positive', 'AGE_001'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getMessagesByPath();

    $this->assertArrayHasKey('user.email', $messages);
    $this->assertArrayHasKey('user.profile.age', $messages);
  }

  public function test_getMessagesByPath_WithRootPath_ShouldUseEmptyString(): void {
    $issues = [
      new Issue([], 'Root error', 'ROOT_001'),
      new Issue(['field'], 'Field error', 'FIELD_001'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getMessagesByPath();

    $this->assertArrayHasKey('', $messages);
    $this->assertArrayHasKey('field', $messages);
  }

  public function test_getMessagesByPath_WithInterleavedPaths_ShouldKeepFirstOccurrenceOrder(): void {
    $exception = new ValidatorException([
      new Issue(path: ['a'], message: '1', code: 'custom'),
      new Issue(path: ['b'], message: '2', code: 'custom'),
      new Issue(path: ['a'], message: '3', code: 'custom'),
    ]);

    $this->assertSame(['a' => ['1', '3'], 'b' => ['2']], $exception->getMessagesByPath());
  }

  public function test_getMessagesByPath_WithNumericPaths_ShouldKeepFirstOccurrenceOrder(): void {
    $exception = new ValidatorException([
      new Issue(path: ['1'], message: 'x', code: 'custom'),
      new Issue(path: ['0'], message: 'y', code: 'custom'),
      new Issue(path: ['1'], message: 'z', code: 'custom'),
    ]);

    $this->assertSame([1 => ['x', 'z'], 0 => ['y']], $exception->getMessagesByPath());
  }

  public function test_getFlatMessages_WithoutIssues_ShouldReturnEmptyArray(): void {
    $exception = new ValidatorException([]);

    $messages = $exception->getFlatMessages();

    $this->assertIsArray($messages);
    $this->assertEmpty($messages);
  }

  public function test_getFlatMessages_WithSingleIssuePerPath_ShouldReturnCorrectStructure(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getFlatMessages();

    $this->assertCount(2, $messages);
    $this->assertSame('Invalid email', $messages['email']);
    $this->assertSame('Password too short', $messages['password']);
  }

  public function test_getFlatMessages_WithMultipleIssuesSamePath_ShouldUseFirstOne(): void {
    $issues = [
      new Issue(['email'], 'Invalid email format', 'EMAIL_001'),
      new Issue(['email'], 'Email already registered', 'EMAIL_002'),
    ];

    $exception = new ValidatorException($issues);
    $messages = $exception->getFlatMessages();

    $this->assertCount(1, $messages);
    $this->assertSame('Invalid email format', $messages['email']);
  }

  public function test_hasIssueAt_WithExistingPath_ShouldReturnTrue(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertTrue($exception->hasIssueAt('email'));
    $this->assertTrue($exception->hasIssueAt('password'));
  }

  public function test_hasIssueAt_WithNonExistingPath_ShouldReturnFalse(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertFalse($exception->hasIssueAt('password'));
    $this->assertFalse($exception->hasIssueAt('username'));
  }

  public function test_hasIssueAt_WithNestedPath_ShouldReturnCorrectly(): void {
    $issues = [
      new Issue(['user', 'profile', 'email'], 'Invalid email', 'EMAIL_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertTrue($exception->hasIssueAt('user.profile.email'));
    $this->assertFalse($exception->hasIssueAt('user.profile'));
  }

  public function test_hasIssueAt_WithRootPath_ShouldReturnCorrectly(): void {
    $issues = [
      new Issue([], 'Root error', 'ROOT_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertTrue($exception->hasIssueAt(''));
  }

  public function test_hasIssueAt_OnEmptyException_ShouldReturnFalse(): void {
    $exception = new ValidatorException([]);

    $this->assertFalse($exception->hasIssueAt('any_path'));
    $this->assertFalse($exception->hasIssueAt(''));
  }

  public function test_getIssuesAt_WithExistingPath_ShouldReturnIssues(): void {
    $issue1 = new Issue(['email'], 'Invalid email format', 'EMAIL_001');
    $issue2 = new Issue(['email'], 'Email already registered', 'EMAIL_002');

    $exception = new ValidatorException([$issue1, $issue2]);

    $issues = $exception->getIssuesAt('email');
    $this->assertCount(2, $issues);
    $this->assertSame($issue1, $issues[0]);
    $this->assertSame($issue2, $issues[1]);
  }

  public function test_getIssuesAt_WithNonExistingPath_ShouldReturnEmptyArray(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
    ];

    $exception = new ValidatorException($issues);
    $result = $exception->getIssuesAt('password');

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  public function test_getIssuesAt_WithNestedPath_ShouldReturnCorrectIssues(): void {
    $issue = new Issue(['user', 'profile', 'email'], 'Invalid email', 'EMAIL_001');

    $exception = new ValidatorException([$issue]);

    $issues = $exception->getIssuesAt('user.profile.email');
    $this->assertCount(1, $issues);
    $this->assertSame($issue, $issues[0]);
  }

  public function test_toArray_WithoutIssues_ShouldReturnEmptyArray(): void {
    $exception = new ValidatorException([]);

    $result = $exception->toArray();

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  public function test_toArray_WithIssues_ShouldConvertCorrectly(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['user', 'password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);
    $result = $exception->toArray();

    $this->assertCount(2, $result);

    $this->assertArrayHasKey('path', $result[0]);
    $this->assertArrayHasKey('message', $result[0]);
    $this->assertArrayHasKey('code', $result[0]);

    $this->assertSame(['email'], $result[0]['path']);
    $this->assertSame('Invalid email', $result[0]['message']);
    $this->assertSame('EMAIL_001', $result[0]['code']);

    $this->assertSame(['user', 'password'], $result[1]['path']);
    $this->assertSame('Password too short', $result[1]['message']);
    $this->assertSame('PASS_001', $result[1]['code']);
  }

  public function test_toArray_WithRootLevelIssue_ShouldIncludeEmptyPath(): void {
    $issue = new Issue([], 'Root error', 'ROOT_001');

    $exception = new ValidatorException([$issue]);
    $result = $exception->toArray();

    $this->assertCount(1, $result);
    $this->assertSame([], $result[0]['path']);
  }

  public function test_toJson_WithoutIssues_ShouldReturnEmptyJsonArray(): void {
    $exception = new ValidatorException([]);

    $json = $exception->toJson();

    $this->assertIsString($json);
    $this->assertJson($json);
    $this->assertSame('[]', $json);
  }

  public function test_toJson_WithIssues_ShouldReturnValidJson(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
    ];

    $exception = new ValidatorException($issues);
    $json = $exception->toJson();

    $this->assertJson($json);

    $decoded = json_decode($json, true);
    $this->assertIsArray($decoded);
    $this->assertCount(2, $decoded);
    $this->assertSame('Invalid email', $decoded[0]['message']);
    $this->assertSame('PASS_001', $decoded[1]['code']);
  }

  public function test_toJson_WithUnicodeCharacters_ShouldPreserveUnicode(): void {
    $issue = new Issue(['field'], 'Campo obrigatório: 日本語 🚀', 'UNICODE_001');

    $exception = new ValidatorException([$issue]);
    $json = $exception->toJson();

    $this->assertStringContainsString('日本語', $json);
    $this->assertStringContainsString('🚀', $json);

    $decoded = json_decode($json, true);
    $this->assertSame('Campo obrigatório: 日本語 🚀', $decoded[0]['message']);
  }

  public function test_toJson_WithSpecialJsonCharacters_ShouldEscapeCorrectly(): void {
    $issue = new Issue(['field'], 'Error with "quotes" and \\ backslash', 'SPECIAL_001');

    $exception = new ValidatorException([$issue]);
    $json = $exception->toJson();

    $decoded = json_decode($json, true);
    $this->assertStringContainsString('quotes', $decoded[0]['message']);
  }

  public function test_getIssuesByPath_WithoutIssues_ShouldReturnEmptyArray(): void {
    $exception = new ValidatorException([]);

    $grouped = $exception->getIssuesByPath();

    $this->assertIsArray($grouped);
    $this->assertEmpty($grouped);
  }

  public function test_getIssuesByPath_WithSingleIssueSinglePath_ShouldReturnCorrectly(): void {
    $issue = new Issue(['email'], 'Invalid email', 'EMAIL_001');

    $exception = new ValidatorException([$issue]);
    $grouped = $exception->getIssuesByPath();

    $this->assertCount(1, $grouped);
    $this->assertArrayHasKey('email', $grouped);
    $this->assertCount(1, $grouped['email']);
    $this->assertSame($issue, $grouped['email'][0]);
  }

  public function test_getIssuesByPath_WithMultipleIssuesSamePath_ShouldGroupTogether(): void {
    $issue1 = new Issue(['email'], 'Invalid email format', 'EMAIL_001');
    $issue2 = new Issue(['email'], 'Email already registered', 'EMAIL_002');

    $exception = new ValidatorException([$issue1, $issue2]);
    $grouped = $exception->getIssuesByPath();

    $this->assertCount(1, $grouped);
    $this->assertCount(2, $grouped['email']);
    $this->assertSame([$issue1, $issue2], $grouped['email']);
  }

  public function test_getIssuesByPath_WithMultiplePaths_ShouldGroupByPath(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['password'], 'Password too short', 'PASS_001'),
      new Issue(['email'], 'Email already exists', 'EMAIL_002'),
      new Issue(['username'], 'Username taken', 'USER_001'),
      new Issue(['password'], 'Missing uppercase', 'PASS_002'),
    ];

    $exception = new ValidatorException($issues);
    $grouped = $exception->getIssuesByPath();

    $this->assertCount(3, $grouped);
    $this->assertCount(2, $grouped['email']);
    $this->assertCount(2, $grouped['password']);
    $this->assertCount(1, $grouped['username']);
  }

  public function test_getIssuesByPath_WithNestedPaths_ShouldUsePathString(): void {
    $issues = [
      new Issue(['user', 'profile', 'email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['user', 'email'], 'Invalid email', 'EMAIL_002'),
    ];

    $exception = new ValidatorException($issues);
    $grouped = $exception->getIssuesByPath();

    $this->assertCount(2, $grouped);
    $this->assertArrayHasKey('user.profile.email', $grouped);
    $this->assertArrayHasKey('user.email', $grouped);
  }

  public function test_exceptionIsThrowable_ShouldBeCatchableAsRuntimeException(): void {
    $issue = new Issue(['field'], 'Error', 'CODE_001');
    $exception = new ValidatorException([$issue]);

    $this->assertInstanceOf(\RuntimeException::class, $exception);
  }

  public function test_exceptionExtends_RuntimeException_ShouldInheritBehavior(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertIsString($exception->getMessage());
    $this->assertIsInt($exception->getCode());
    $this->assertNull($exception->getPrevious());
  }

  public function test_class_IsFinal_ShouldNotAllowInheritance(): void {
    $reflection = new \ReflectionClass(ValidatorException::class);
    $this->assertTrue($reflection->isFinal());
  }

  public function test_exceptionWithManyIssues_ShouldHandlePerformanceCorrectly(): void {
    $issues = [];
    for ($i = 0; $i < 100; $i++) {
      $issues[] = new Issue(
        ["field_{$i}"],
        "Error message {$i}",
        "CODE_{$i}"
      );
    }

    $exception = new ValidatorException($issues);

    $this->assertCount(100, $exception->getIssues());
    $this->assertCount(100, $exception->getIssuesByPath());
    $this->assertCount(100, $exception->toArray());
  }

  public function test_buildMessage_WithComplexIssues_ShouldFormatCorrectly(): void {
    $issues = [
      new Issue([], 'Root validation error', 'ROOT_001'),
      new Issue(['user', 'email'], 'Invalid email format', 'EMAIL_001'),
      new Issue(['user', 'addresses', '0', 'zipcode'], 'Invalid zipcode', 'ZIP_001'),
    ];

    $exception = new ValidatorException($issues);
    $message = $exception->getMessage();

    $this->assertStringStartsWith('Validation failed:', $message);
    $this->assertStringContainsString('[(root)]', $message);
    $this->assertStringContainsString('[user.email]', $message);
    $this->assertStringContainsString('[user.addresses.0.zipcode]', $message);
    $this->assertStringContainsString(' | ', $message);
  }

  public function test_messageFormat_ShouldFollowPattern_PathMessageCode(): void {
    $issue = new Issue(['field'], 'Test message', 'TEST_001');

    $exception = new ValidatorException([$issue]);
    $message = $exception->getMessage();

    $this->assertStringContainsString('[field]', $message);
    $this->assertStringContainsString('Test message', $message);
    $this->assertStringContainsString('(code: TEST_001)', $message);
  }

  public function test_getIssuesByPath_CanBeCalledMultipleTimes_ShouldReturnConsistentResults(): void {
    $issues = [
      new Issue(['email'], 'Invalid email', 'EMAIL_001'),
      new Issue(['email'], 'Email taken', 'EMAIL_002'),
    ];

    $exception = new ValidatorException($issues);

    $first = $exception->getIssuesByPath();
    $second = $exception->getIssuesByPath();
    $third = $exception->getIssuesByPath();

    $this->assertSame($first, $second);
    $this->assertSame($second, $third);
  }

  public function test_complexScenario_FormValidationWithMultipleFieldsAndErrors(): void {
    $issues = [
      new Issue(['email'], 'Email is required', 'EMAIL_REQUIRED'),
      new Issue(['email'], 'Invalid email format', 'EMAIL_INVALID'),
      new Issue(['password'], 'Password must be at least 8 characters', 'PASS_MIN_LENGTH'),
      new Issue(['password'], 'Password must contain uppercase letter', 'PASS_NO_UPPER'),
      new Issue(['confirm_password'], 'Passwords do not match', 'PASS_NO_MATCH'),
      new Issue(['terms'], 'You must accept the terms', 'TERMS_NOT_ACCEPTED'),
    ];

    $exception = new ValidatorException($issues);

    $this->assertCount(6, $exception->getIssues());
    $this->assertCount(6, $exception->toArray());

    $grouped = $exception->getIssuesByPath();
    $this->assertCount(4, $grouped);
    $this->assertCount(2, $grouped['email']);
    $this->assertCount(2, $grouped['password']);

    $messages = $exception->getMessagesByPath();
    $this->assertCount(2, $messages['email']);

    $this->assertTrue($exception->hasIssueAt('email'));
    $this->assertTrue($exception->hasIssueAt('password'));
    $this->assertTrue($exception->hasIssueAt('terms'));

    $flat = $exception->getFlatMessages();
    $this->assertSame('Email is required', $flat['email']);

    $json = $exception->toJson();
    $decoded = json_decode($json, true);
    $this->assertCount(6, $decoded);
  }
}
