<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;
use Tests\Support\ReadsConfirmationMail;

final class EmailVerificationControllerTest extends HttpTestCase
{
    use ReadsConfirmationMail;

    private function registeredToken(array $override = []): string
    {
        $body = UserControllerTest::body($override);
        $this->assertStatus(201, $this->post('/users', $body));
        return $this->confirmationTokenFor($body['email']);
    }

    public function testLinkConfirmsOnceThenIsRefused(): void
    {
        $token = $this->registeredToken();
        $response = $this->post('/email-verifications', ['token' => $token]);
        $this->assertStatus(200, $response);
        $this->assertSame(['email_verified' => true, 'creator_status' => 'none'], $response->json()['data']);

        $this->assertError(409, 'VERIFICATION_TOKEN_USED', $this->post('/email-verifications', ['token' => $token]));
    }

    public function testCreatorIsConfirmedWithItsRequestStillPending(): void
    {
        $token = $this->registeredToken(['profile' => 'creator', 'individual' => true]);
        $this->assertSame('pending', $this->post('/email-verifications', ['token' => $token])->json()['data']['creator_status']);
    }

    public function testUnknownOrMalformedTokensAreRefused(): void
    {
        $this->assertError(422, 'VERIFICATION_TOKEN_INVALID', $this->post('/email-verifications', ['token' => str_repeat('0', 64)]));
        $this->assertError(422, 'VERIFICATION_TOKEN_INVALID', $this->post('/email-verifications', ['token' => 'abc']));
        $this->assertError(422, 'VALIDATION_ERROR', $this->post('/email-verifications', []));
    }
}
