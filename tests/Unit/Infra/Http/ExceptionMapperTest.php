<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http;

use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpException;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionMapperTest extends TestCase
{
    /** @var list<string> */
    private array $logged = [];

    private function mapper(): ExceptionMapper
    {
        return new ExceptionMapper(function (string $line): void {
            $this->logged[] = $line;
        });
    }

    /** @return iterable<string, array{\Throwable, int, string}> */
    public static function table(): iterable
    {
        yield 'protocole' => [new HttpException(405, 'Méthode non autorisée.'), 405, 'Méthode non autorisée.'];
        yield 'validation' => [new ValidationException('Pseudo trop court.'), 422, 'Pseudo trop court.'];
        yield 'argument invalide' => [new \InvalidArgumentException('FEN interne'), 422, 'Requête invalide.'];
        yield 'introuvable' => [new NotFoundException('Partie introuvable.'), 404, 'Partie introuvable.'];
        yield 'interdit' => [new ForbiddenException('Connexion requise.'), 403, 'Connexion requise.'];
        yield 'conflit' => [new ConflictException('Pseudo déjà pris.'), 409, 'Pseudo déjà pris.'];
        yield 'trop de tentatives' => [new TooManyRequestsException('Réessaie plus tard.'), 429, 'Réessaie plus tard.'];
        yield 'inattendue' => [new \RuntimeException('détail interne'), 500, 'Erreur interne du serveur.'];
    }

    #[DataProvider('table')]
    public function testMapsEachExceptionToItsStatus(\Throwable $e, int $status, string $error): void
    {
        $response = $this->mapper()->toResponse($e);

        self::assertSame($status, $response->status);
        self::assertSame(['ok' => false, 'error' => $error], self::decode($response));
    }

    public function testUnexpectedExceptionIsHiddenAndLogged(): void
    {
        $response = $this->mapper()->toResponse(new \PDOException("SQLSTATE[28000] Access denied for user 'lecercle'"));

        self::assertSame(500, $response->status);
        self::assertSame('Erreur interne du serveur.', self::decode($response)['error']);
        self::assertStringNotContainsString('lecercle', $response->json);
        self::assertCount(1, $this->logged);
        self::assertStringStartsWith('[le-cercle] PDOException: SQLSTATE', $this->logged[0]);
        self::assertMatchesRegularExpression('/\(\S+ExceptionMapperTest\.php:\d+\)\z/', $this->logged[0]);
    }

    public function testPlainInvalidArgumentDoesNotLeak(): void
    {
        $response = $this->mapper()->toResponse(new \InvalidArgumentException('FEN interne'));

        self::assertSame(422, $response->status);
        self::assertStringNotContainsString('FEN', $response->json);
        self::assertSame([], $this->logged);
    }

    /** @return array<mixed> */
    private static function decode(JsonResponse $response): array
    {
        $data = json_decode($response->json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
