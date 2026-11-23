<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use App\Controller\Middleware\EmailVerificationRateLimit;
use App\Controller\Middleware\RegistrationAttemptRateLimit;
use App\Controller\Middleware\RegistrationRateLimit;
use App\Core\Config;
use App\Core\Http\ClientIp;
use App\Core\Http\RateLimit;
use App\Core\Http\RateLimiter;
use App\Core\HttpException;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
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

    /** @param class-string<RateLimit> $class */
    private function limit(string $class, Config $config = new Config()): RateLimit
    {
        return new $class($this->limiter, new ClientIp($config), $config);
    }

    private static function request(string $ip): Request
    {
        return new Request('POST', '/users', attributes: [Request::REMOTE_ADDR => $ip]);
    }

    private static function created(): \Closure
    {
        return static fn (Request $r): Response => Response::json([], 201);
    }

    private function count(string $where = '1 = 1'): int
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM rate_limit_hits WHERE $where");
        return $statement === false ? -1 : (int) $statement->fetchColumn();
    }

    public function testRegistrationsCountOnlyWhenTheyAreAcceptedAndPerIp(): void
    {
        $limit = $this->limit(RegistrationRateLimit::class);
        $refused = static fn (Request $r): Response => Response::error('VALIDATION_ERROR', 'Invalid', 422);
        $thrown = static fn (Request $r): Response => throw ValidationException::field('email', 'Invalid');

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(422, $limit->process(self::request('1.1.1.1'), $refused)->status, 'a refused form costs nothing');
            $this->assertThrows(ValidationException::class, fn () => $limit->process(self::request('1.1.1.1'), $thrown));
        }
        $this->assertSame(0, $this->count(), 'refused attempts give their place back');
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(201, $limit->process(self::request('1.1.1.1'), self::created())->status);
        }
        $error = $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('1.1.1.1'), self::created()));
        $this->assertSame([429, 'TOO_MANY_REQUESTS', '3600'], [$error->status, $error->errorCode, $error->headers['Retry-After'] ?? null]);
        $this->assertSame(5, $this->count(), 'a refused request is not counted');
        $this->assertSame(201, $limit->process(self::request('2.2.2.2'), self::created())->status, 'another IP is not blocked');

        $this->clock->advance('PT1H');
        $this->assertSame(201, $limit->process(self::request('1.1.1.1'), self::created())->status, 'the window has passed');
        $this->assertSame(1, $this->count(), 'the hits of the past hour are purged');
        $this->assertSame(0, $this->count("key_hash = '1.1.1.1'"), 'IP hashed');
    }

    /**
     * Rafale : chaque requete arrive pendant que la precedente est encore traitee (bcrypt, mail). Compter apres le
     * traitement les laisserait toutes passer ; la place est donc reservee avant.
     */
    public function testParallelRequestsCannotExceedTheLimit(): void
    {
        $limit = $this->limit(RegistrationRateLimit::class);
        $statuses = [];
        $burst = function (int $left) use ($limit, &$statuses, &$burst): void {
            if ($left === 0) {
                return;
            }
            try {
                $limit->process(self::request('1.1.1.1'), function (Request $r) use ($left, &$statuses, $burst): Response {
                    $burst($left - 1); // la suivante arrive avant la fin de celle-ci
                    $statuses[] = 201;
                    return Response::json([], 201);
                });
            } catch (HttpException $e) {
                $statuses[] = $e->status;
                $burst($left - 1);
            }
        };
        $burst(10);

        sort($statuses);
        $this->assertSame([...array_fill(0, 5, 201), ...array_fill(0, 5, 429)], $statuses);
        $this->assertSame(5, $this->count());
    }

    public function testEveryRegistrationAttemptCounts(): void
    {
        $limit = $this->limit(RegistrationAttemptRateLimit::class);
        $taken = static fn (Request $r): Response => Response::error('EMAIL_TAKEN', 'Taken', 409);
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame(409, $limit->process(self::request('1.1.1.1'), $taken)->status);
        }
        $this->assertSame(429, $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('1.1.1.1'), $taken))->status,
            'no endless probing of which addresses have an account');
    }

    public function testConfirmationsCountEveryAttempt(): void
    {
        $limit = $this->limit(EmailVerificationRateLimit::class);
        $invalid = static fn (Request $r): Response => Response::error('VERIFICATION_TOKEN_INVALID', 'Invalid', 422);
        for ($i = 0; $i < 30; $i++) {
            $limit->process(self::request('3.3.3.3'), $invalid);
        }
        $this->assertSame(429, $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('3.3.3.3'), $invalid))->status);
    }

    public function testLimitsComeFromTheConfig(): void
    {
        $limit = $this->limit(RegistrationRateLimit::class, new Config(rateLimitRegistrations: 2));
        $limit->process(self::request('1.1.1.1'), self::created());
        $limit->process(self::request('1.1.1.1'), self::created());
        $this->assertThrows(HttpException::class, fn () => $limit->process(self::request('1.1.1.1'), self::created()));
    }

    public function testClientsBehindATrustedProxyAreCountedSeparately(): void
    {
        $limit = $this->limit(RegistrationRateLimit::class, new Config(trustedProxies: ['10.0.0.1'], rateLimitRegistrations: 1));
        $viaProxy = static fn (string $client): Request => new Request('POST', '/users',
            headers: ['x-forwarded-for' => $client], attributes: [Request::REMOTE_ADDR => '10.0.0.1']);

        $this->assertSame(201, $limit->process($viaProxy('203.0.113.7'), self::created())->status);
        $this->assertSame(201, $limit->process($viaProxy('198.51.100.4'), self::created())->status, 'not the proxy address for everyone');
        $this->assertThrows(HttpException::class, fn () => $limit->process($viaProxy('203.0.113.7'), self::created()));
    }
}
