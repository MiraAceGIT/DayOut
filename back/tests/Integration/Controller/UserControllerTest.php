<?php
declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\HttpTestCase;
use Tests\Support\ReadsConfirmationMail;
use Tests\TestResponse;

final class UserControllerTest extends HttpTestCase
{
    use ReadsConfirmationMail;

    /** Corps valide ; l'e-mail est unique a chaque appel (la base de test n'est remise a zero qu'une fois par campagne). */
    public static function body(array $override = []): array
    {
        return $override + [
            'profile' => 'participant', 'first_name' => 'Lina', 'last_name' => 'Martin',
            'email' => 'lina.' . bin2hex(random_bytes(4)) . '@isep.fr', 'school_id' => 1, 'password' => 'Soiree2026',
            'terms_accepted' => true, 'interests' => ['soiree', 'tech'], 'search_radius_km' => 5,
        ];
    }

    private function register(array $override = []): TestResponse
    {
        return $this->post('/users', self::body($override));
    }

    public function testParticipantIsCreatedUnverifiedAndReceivesALink(): void
    {
        $body = self::body(['email' => '  Lina.Test@ISEP.fr ']);
        $response = $this->post('/users', $body);
        $this->assertStatus(201, $response);
        $user = $response->json()['data'];
        $this->assertSame(['id', 'email', 'first_name', 'role', 'creator_status', 'email_verified', 'demo_confirmation_url'], array_keys($user));
        $this->assertSame('lina.test@isep.fr', $user['email']);
        $this->assertSame(['participant', 'none', false, null], [$user['role'], $user['creator_status'], $user['email_verified'], $user['demo_confirmation_url']]);
        $this->assertSame(64, strlen($this->confirmationTokenFor('lina.test@isep.fr')));

        $audit = array_values(array_filter($this->readLog('audit'), static fn (array $e): bool => $e['message'] === 'user.register'));
        $this->assertTrue($audit !== [], 'user.register audited');
        $this->assertSame($user['id'], $audit[count($audit) - 1]['context']['resource']['id']);
        $raw = implode("\n", array_map('json_encode', array_merge($this->readLog('audit'), $this->readLog('info'))));
        $this->assertStringNotContains('Soiree2026', $raw, 'password never logged');
        $this->assertStringNotContains($this->confirmationTokenFor('lina.test@isep.fr'), $raw, 'token only in the mail log');
    }

    public function testCreatorRequestIsPending(): void
    {
        $response = $this->register(['profile' => 'creator', 'organization_name' => 'BDE ISEP']);
        $this->assertStatus(201, $response);
        $this->assertSame(['participant', 'pending'], [$response->json()['data']['role'], $response->json()['data']['creator_status']]);
    }

    public function testRefusesOtherDomainsAndAMismatchedSchool(): void
    {
        foreach ([['email' => 'lina@gmail.com'], ['email' => 'lina.' . bin2hex(random_bytes(4)) . '@epita.fr']] as $input) {
            $error = $this->assertError(422, 'VALIDATION_ERROR', $this->register($input));
            $this->assertSame(['email'], array_keys($error['details']));
        }
        $error = $this->assertError(422, 'VALIDATION_ERROR', $this->register(['interests' => ['afterwork']]));
        $this->assertSame(['interests'], array_keys($error['details']), 'inactive category');
    }

    public function testRefusesAnEmailAlreadyUsed(): void
    {
        $error = $this->assertError(409, 'EMAIL_TAKEN', $this->register(['email' => 'PENDING@efrei.net', 'school_id' => 13]));
        $this->assertSame(['field' => 'email'], $error['details']);
    }

    public function testInvalidFormIsRefusedByTheServerToo(): void
    {
        $error = $this->assertError(422, 'VALIDATION_ERROR', $this->register(['password' => 'motdepasse', 'terms_accepted' => false]));
        $this->assertSame(['password', 'terms_accepted'], array_keys($error['details']));
        $this->assertError(422, 'VALIDATION_ERROR', $this->post('/users', []));
        $this->assertError(415, 'UNSUPPORTED_MEDIA_TYPE', $this->request('POST', '/users', null, ['Content-Type' => 'text/plain'], 'profile=participant'));
    }
}
