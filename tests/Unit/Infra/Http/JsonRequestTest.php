<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http;

use LeCercle\Infra\Http\HttpException;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonRequestTest extends TestCase
{
    public function testReadsTypedParameters(): void
    {
        $request = JsonRequest::fromBody('{"action":"move","id":"g1","n":3,"flag":true,"tc":{"base":300}}');

        self::assertSame('move', $request->action());
        self::assertSame('g1', $request->string('id'));
        self::assertSame(3, $request->int('n'));
        self::assertTrue($request->bool('flag'));
        self::assertSame(['base' => 300], $request->optionalArray('tc'));
        self::assertNull($request->optionalString('x'));
    }

    public function testOptionalParametersAcceptNullAndValues(): void
    {
        $request = JsonRequest::fromBody('{"action":"a","s":"v","n":7,"nul":null}');

        self::assertSame('v', $request->optionalString('s'));
        self::assertSame(7, $request->optionalInt('n'));
        self::assertNull($request->optionalInt('nul'));
        self::assertNull($request->optionalArray('nul'));
        self::assertNull($request->optionalInt('absent'));
    }

    /** @return iterable<string, array{string, \Closure(JsonRequest): mixed, string}> */
    public static function wrongTypes(): iterable
    {
        $body = '{"action":"a","id":"g1","n":3,"f":3.5,"b":1}';
        yield 'int sur une chaîne' => [$body, static fn(JsonRequest $r): int => $r->int('id'), 'id'];
        yield 'string sur un entier' => [$body, static fn(JsonRequest $r): string => $r->string('n'), 'n'];
        yield 'int sur un flottant' => [$body, static fn(JsonRequest $r): int => $r->int('f'), 'f'];
        yield 'bool sur un entier' => [$body, static fn(JsonRequest $r): bool => $r->bool('b'), 'b'];
        yield 'string absente' => [$body, static fn(JsonRequest $r): string => $r->string('absent'), 'absent'];
        yield 'optionalString sur un entier' => [$body, static fn(JsonRequest $r): ?string => $r->optionalString('n'), 'n'];
        yield 'optionalInt sur une chaîne' => [$body, static fn(JsonRequest $r): ?int => $r->optionalInt('id'), 'id'];
        yield 'optionalArray sur une chaîne' => [$body, static fn(JsonRequest $r): ?array => $r->optionalArray('id'), 'id'];
    }

    /** @param \Closure(JsonRequest): mixed $read */
    #[DataProvider('wrongTypes')]
    public function testWrongTypeIsValidation(string $body, \Closure $read, string $key): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Paramètre « $key » manquant ou invalide.");
        $read(JsonRequest::fromBody($body));
    }

    public function testNonObjectBodyIsInvalidJson(): void
    {
        try {
            JsonRequest::fromBody('[1]');
            self::fail('HttpException attendue.');
        } catch (HttpException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('Corps JSON invalide.', $e->getMessage());
        }
    }

    public function testMissingActionIsUnknownAction(): void
    {
        try {
            JsonRequest::fromBody('{"action":1}');
            self::fail('HttpException attendue.');
        } catch (HttpException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('Action inconnue.', $e->getMessage());
        }
    }
}
