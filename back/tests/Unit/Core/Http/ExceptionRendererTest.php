<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use App\Core\HttpException;
use App\Core\Http\ExceptionRenderer;
use App\Model\Exceptions\BusinessException;
use App\Model\Exceptions\ErrorKind;
use PDOException;
use Tests\TestCase;

final class SampleException extends BusinessException
{
    public static function of(ErrorKind $kind): self
    {
        return new self($kind, 'SAMPLE_CODE', 'Sample message', ['id' => 7], ['secret' => 'internal']);
    }
}

final class ExceptionRendererTest extends TestCase
{
    public function testRendererCopiesHttpExceptionHeaders(): void
    {
        $response = ExceptionRenderer::render(HttpException::methodNotAllowed(['GET', 'POST']));

        $this->assertSame(405, $response->status);
        $this->assertSame('GET, POST', $response->header('Allow'));
    }

    public function testBusinessExceptionKindSetsStatusAndHidesContext(): void
    {
        $expected = [404 => ErrorKind::NotFound, 403 => ErrorKind::Forbidden, 409 => ErrorKind::Conflict, 422 => ErrorKind::Invalid];
        foreach ($expected as $status => $kind) {
            $response = ExceptionRenderer::render(SampleException::of($kind));
            $this->assertSame($status, $response->status, $kind->name);
            $this->assertSame(
                ['code' => 'SAMPLE_CODE', 'message' => 'Sample message', 'details' => ['id' => 7]],
                $response->payload()['error'],
                $kind->name . ' : context stays out of the response'
            );
        }
    }

    private static function pdoError(string $state, int $errno, string $message): PDOException
    {
        $e = new PDOException("SQLSTATE[$state]: $errno $message");
        $e->errorInfo = [$state, $errno, $message];
        return $e;
    }

    public function testTriggerBusinessRuleBecomesClientError(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('45000', 50304, 'Event is full'));
        $this->assertSame(409, $response->status);
        $this->assertSame(['code' => 'EVENT_FULL', 'message' => 'Event is full'], $response->payload()['error']);

        $this->assertSame(403, ExceptionRenderer::render(self::pdoError('45000', 50301, 'User is banned'))->status);
        $unknown = ExceptionRenderer::render(self::pdoError('45000', 50999, 'Some new rule'));
        $this->assertSame('BUSINESS_RULE_VIOLATION', $unknown->payload()['error']['code']);
    }

    public function testNativeConstraintErrorsGetGenericMessages(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('23000', 1062, "Duplicate entry '1-3' for key 'uk_reg_event_user'"));

        $this->assertSame(409, $response->status);
        $this->assertSame(['code' => 'CONFLICT', 'message' => 'Resource already exists'], $response->payload()['error']);
    }

    public function testOtherDatabaseErrorsStayInternal(): void
    {
        $response = ExceptionRenderer::render(self::pdoError('42S22', 1054, "Unknown column 'x'"));
        $this->assertSame(500, $response->status);
        $this->assertSame('INTERNAL_ERROR', $response->payload()['error']['code']);
    }
}
