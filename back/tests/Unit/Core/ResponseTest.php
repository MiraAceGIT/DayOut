<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use Tests\TestCase;

final class ResponseTest extends TestCase
{
    public function testResponseErrorEnvelopeAndImmutableHeaders(): void
    {
        $response = Response::error('NOT_FOUND', 'Event not found', 404, ['id' => 3]);
        $withHeader = $response->withHeader('Allow', 'GET');

        $this->assertSame(['error' => ['code' => 'NOT_FOUND', 'message' => 'Event not found', 'details' => ['id' => 3]]], $response->payload());
        $this->assertSame(null, $response->header('Allow'));
        $this->assertSame('GET', $withHeader->header('allow'));
        $this->assertSame(['error' => ['code' => 'X', 'message' => 'm']], Response::error('X', 'm', 400)->payload(), 'no empty details');
    }
}
