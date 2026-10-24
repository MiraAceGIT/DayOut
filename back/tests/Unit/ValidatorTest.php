<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Core\Validator;
use LogicException;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    /** Erreurs par champ renvoyees par check(), ou [] si les donnees sont valides. */
    private function errors(array $data, array $rules): array
    {
        try {
            Validator::check($data, $rules);
            return [];
        } catch (HttpException $e) {
            return $e->details;
        }
    }

    public function testValidDataIsReturned(): void
    {
        $data = Validator::check(
            ['title' => 'Soiree', 'capacity' => 50],
            ['title' => 'required|string', 'capacity' => 'int']
        );
        $this->assertSame(['title' => 'Soiree', 'capacity' => 50], $data);
    }

    public function testFieldsWithoutRuleAreDropped(): void
    {
        $data = Validator::check(['title' => 'Soiree', 'role' => 'admin'], ['title' => 'string']);
        $this->assertSame(['title' => 'Soiree'], $data);
    }

    public function testFailureThrows422WithOneMessagePerField(): void
    {
        $e = $this->assertThrows(HttpException::class, fn () => Validator::check(
            ['email' => 'nope'],
            ['email' => 'required|email', 'password' => 'required|string|min:8']
        ));
        $this->assertSame(422, $e->status);
        $this->assertSame('VALIDATION_ERROR', $e->errorCode);
        $this->assertSame([
            'email'    => 'Must be a valid email address',
            'password' => 'This field is required',
        ], $e->details);
    }

    public function testRulesAcceptArraySyntax(): void
    {
        $this->assertSame([], $this->errors(['a' => 'x'], ['a' => ['required', 'string', 'max:3']]));
    }

    public function testRequiredRejectsMissingNullBlankAndEmptyArray(): void
    {
        foreach ([[], ['a' => null], ['a' => '   '], ['a' => []]] as $data) {
            $this->assertSame(['a' => 'This field is required'], $this->errors($data, ['a' => 'required']));
        }
        $this->assertSame([], $this->errors(['a' => 0], ['a' => 'required']));
        $this->assertSame([], $this->errors(['a' => false], ['a' => 'required']));
    }

    public function testOptionalMissingFieldIsSkipped(): void
    {
        $this->assertSame([], Validator::check([], ['a' => 'string|max:3']));
    }

    public function testNullRequiresNullable(): void
    {
        $this->assertSame(['a' => 'This field cannot be null'], $this->errors(['a' => null], ['a' => 'string']));
        $this->assertSame(['a' => null], Validator::check(['a' => null], ['a' => 'nullable|string|min:3']));
    }

    public function testString(): void
    {
        $this->assertSame(['a' => 'Must be a string'], $this->errors(['a' => 12], ['a' => 'string']));
    }

    public function testIntAcceptsIntegerStringsAndCastsThem(): void
    {
        $this->assertSame(['a' => 12], Validator::check(['a' => '12'], ['a' => 'int']));
        $this->assertSame(['a' => -3], Validator::check(['a' => -3], ['a' => 'int']));
        foreach ([1.5, '1.5', '1e3', 'abc', true, '', '99999999999999999999'] as $value) {
            $this->assertSame(['a' => 'Must be an integer'], $this->errors(['a' => $value], ['a' => 'int']), json_encode($value));
        }
    }

    public function testBoolAcceptsCommonFormsAndCastsThem(): void
    {
        foreach ([true, 1, '1', 'true'] as $value) {
            $this->assertSame(['a' => true], Validator::check(['a' => $value], ['a' => 'bool']));
        }
        foreach ([false, 0, '0', 'false'] as $value) {
            $this->assertSame(['a' => false], Validator::check(['a' => $value], ['a' => 'bool']));
        }
        $this->assertSame(['a' => 'Must be a boolean'], $this->errors(['a' => 'yes'], ['a' => 'bool']));
    }

    public function testArray(): void
    {
        $this->assertSame([], $this->errors(['a' => [1, 2]], ['a' => 'array']));
        $this->assertSame(['a' => 'Must be an array'], $this->errors(['a' => '1,2'], ['a' => 'array']));
    }

    public function testEmail(): void
    {
        $this->assertSame([], $this->errors(['a' => 'yovanne@isep.fr'], ['a' => 'email']));
        $this->assertSame(['a' => 'Must be a valid email address'], $this->errors(['a' => 'yovanne@'], ['a' => 'email']));
    }

    public function testUrlOnlyAcceptsHttp(): void
    {
        $this->assertSame([], $this->errors(['a' => 'https://dayout.fr/e/1'], ['a' => 'url']));
        foreach (['javascript:alert(1)', 'ftp://x.fr', 'not a url'] as $value) {
            $this->assertSame(['a' => 'Must be a valid http(s) URL'], $this->errors(['a' => $value], ['a' => 'url']), $value);
        }
    }

    public function testDate(): void
    {
        $this->assertSame([], $this->errors(['a' => '2026-11-07'], ['a' => 'date']));
        foreach (['2026-02-31', '07/11/2026', '2026-11-07T20:00:00Z'] as $value) {
            $this->assertSame(['a' => 'Must be a date (YYYY-MM-DD)'], $this->errors(['a' => $value], ['a' => 'date']), $value);
        }
    }

    public function testDatetimeRequiresTimezone(): void
    {
        foreach (['2026-11-07T20:00:00Z', '2026-11-07T20:00:00+01:00', '2026-11-07T20:00:00.123Z'] as $value) {
            $this->assertSame([], $this->errors(['a' => $value], ['a' => 'datetime']), $value);
        }
        foreach (['2026-11-07T20:00:00', '2026-11-07 20:00:00', '2026-11-07', '2026-13-07T20:00:00Z'] as $value) {
            $this->assertSame(
                ['a' => 'Must be an ISO 8601 datetime with timezone'],
                $this->errors(['a' => $value], ['a' => 'datetime']),
                $value
            );
        }
    }

    public function testMinMaxOnStringLengthCountsCharactersNotBytes(): void
    {
        $this->assertSame([], $this->errors(['a' => 'été'], ['a' => 'string|min:3|max:3']));
        $this->assertSame(['a' => 'Must be at least 4 characters'], $this->errors(['a' => 'été'], ['a' => 'string|min:4']));
        $this->assertSame(['a' => 'Must be at most 2 characters'], $this->errors(['a' => 'été'], ['a' => 'string|max:2']));
    }

    public function testMinMaxOnIntValue(): void
    {
        $this->assertSame([], $this->errors(['a' => '1'], ['a' => 'int|min:1|max:100']));
        $this->assertSame(['a' => 'Must be at least 1'], $this->errors(['a' => 0], ['a' => 'int|min:1']));
        $this->assertSame(['a' => 'Must be at most 100'], $this->errors(['a' => '101'], ['a' => 'int|max:100']));
    }

    public function testMinMaxOnArraySize(): void
    {
        $this->assertSame(['a' => 'Must contain at least 3 items'], $this->errors(['a' => [1, 2]], ['a' => 'array|min:3']));
        $this->assertSame(['a' => 'Must contain at most 2 items'], $this->errors(['a' => [1, 2, 3]], ['a' => 'array|max:2']));
    }

    public function testIn(): void
    {
        $this->assertSame([], $this->errors(['a' => 'EUR'], ['a' => 'in:EUR,USD']));
        $this->assertSame(['a' => 'Must be one of: EUR, USD'], $this->errors(['a' => 'eur'], ['a' => 'in:EUR,USD']));
        $this->assertSame(['a' => 'Must be one of: EUR, USD'], $this->errors(['a' => ['EUR']], ['a' => 'in:EUR,USD']));
    }

    public function testFirstFailingRuleWins(): void
    {
        $this->assertSame(['a' => 'Must be a string'], $this->errors(['a' => 5], ['a' => 'string|min:10']));
    }

    public function testUnknownRuleIsAProgrammingError(): void
    {
        $this->assertThrows(LogicException::class, fn () => Validator::check(['a' => 'x'], ['a' => 'strnig']));
        $this->assertThrows(LogicException::class, fn () => Validator::check(['a' => 'x'], ['a' => 'max:abc']));
    }

    public function testInvalidRulesAreDetectedEvenWhenTheFieldIsAbsent(): void
    {
        foreach (['strnig', 'max:abc', 'min', 'in:', 'string:5'] as $rule) {
            $this->assertThrows(LogicException::class, fn () => Validator::check([], ['a' => $rule]), $rule);
        }
        $this->assertThrows(LogicException::class, fn () => Validator::check(['a' => null], ['a' => 'nullable|strnig']));
    }

    public function testSpacesAroundRulesAreIgnored(): void
    {
        $this->assertSame(['a' => 5], Validator::check(['a' => '5'], ['a' => ' required | int | min: 1 ']));
    }
}
