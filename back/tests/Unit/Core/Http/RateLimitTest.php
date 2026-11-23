<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use App\Controller\Middleware\EmailVerificationRateLimit;
use App\Controller\Middleware\RegistrationRateLimit;
use App\Core\Http\RateLimiter;
use App\Core\HttpException;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Core\Response;
use PDO;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class RateLimitTest extends TestCase
{
    private PDO $pdo;
    private FixedClock $clock;
    private RateLimiter $limiter;

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE rate_limit_hits (id INTEGER PRIMARY KEY, bucket TEXT, key_hash TEXT, hit_at TEXT)');
        $this->clock = new FixedClock();
        $this->limiter = new RateLimiter(new Connection($this->pdo), $this->clock);
    }

    private static function request(string $ip): Request
    {
        return new Request('POST', '/users', attributes: [Request::CLIENT_IP => $ip]);
    }

    public function testRegistrationsCountOnlyWhenTheyAreAcceptedAndPerIp(): void
    {
        $limit = new RegistrationRateLimit($this->limiter);
        $refused = static fn (Request $r): Response => Response::error('VALIDATION_ERROR', 'Invalid', 422);
        $created = static fn (Request $r): Response => Response::json([], 201);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(422, $limit->process(self::request('1.1.1.1'), $refused)->status, 'a refused form costs nothing');
        }
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(201, $limit->process(self::request('1.1.1.1'), $created)->status);
        }
        $error = $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('1.1.1.1'), $created));
        $this->assertSame([429, 'TOO_MANY_REQUESTS', '3600'], [$error->status, $error->errorCode, $error->headers['Retry-After'] ?? null]);
        $this->assertSame(201, $limit->process(self::request('2.2.2.2'), $created)->status, 'another IP is not blocked');

        $this->clock->advance('PT1H');
        $this->assertSame(201, $limit->process(self::request('1.1.1.1'), $created)->status, 'the window has passed');
        $this->assertSame(1, $this->count('1 = 1'), 'the 6 hits of the past hour are purged');
        $this->assertSame(0, $this->count("key_hash = '1.1.1.1'"), 'IP hashed');
    }

    private function count(string $where): int
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM rate_limit_hits WHERE $where");
        return $statement === false ? -1 : (int) $statement->fetchColumn();
    }

    public function testConfirmationsCountEveryAttempt(): void
    {
        $limit = new EmailVerificationRateLimit($this->limiter);
        $invalid = static fn (Request $r): Response => Response::error('VERIFICATION_TOKEN_INVALID', 'Invalid', 422);
        for ($i = 0; $i < 30; $i++) {
            $limit->process(self::request('3.3.3.3'), $invalid);
        }
        $this->assertSame(429, $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('3.3.3.3'), $invalid))->status);
    }
}
