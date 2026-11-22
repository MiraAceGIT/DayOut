<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Services;

use App\Controller\EmailVerificationController;
use App\Controller\UserController;
use App\Core\Config;
use App\Core\Container;
use App\Core\Mail\Mailer;
use App\Core\Orm\Connection;
use App\Core\Request;
use App\Core\ValidationException;
use App\Model\Dto\User\RegisteredUserDto;
use App\Model\Dto\User\UserCreateDto;
use App\Model\Enums\CreatorStatus;
use App\Model\Exceptions\RegistrationException;
use App\Model\Services\CategoryService;
use App\Model\Services\RegistrationService;
use App\Model\Services\SchoolService;
use App\Shared\Password;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\Support\FakeMailer;
use Tests\Support\TestContainer;
use Tests\TestCase;
use Tests\Unit\Model\Dto\UserCreateDtoTest;

final class RegistrationServiceTest extends TestCase
{
    private PDO $pdo;
    private FakeMailer $mailer;
    private Container $container;
    private RegistrationService $service;

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT UNIQUE, email_domain TEXT, password_hash TEXT,
            first_name TEXT, last_name TEXT, school_id INTEGER, search_radius_km INTEGER DEFAULT 3, role TEXT DEFAULT 'participant',
            creator_status TEXT DEFAULT 'none', email_verified INTEGER DEFAULT 0, email_verified_at TEXT, banned INTEGER DEFAULT 0)");
        $this->pdo->exec('CREATE TABLE schools (id INTEGER PRIMARY KEY, name TEXT, short_name TEXT, email_domain TEXT UNIQUE, city TEXT, active INTEGER)');
        $this->pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, slug TEXT UNIQUE, label TEXT, icon TEXT, active INTEGER)');
        $this->pdo->exec('CREATE TABLE user_interests (user_id INTEGER, category_id INTEGER, PRIMARY KEY (user_id, category_id))');
        $this->pdo->exec('CREATE TABLE creator_profiles (user_id INTEGER PRIMARY KEY, display_name TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE email_verifications (id INTEGER PRIMARY KEY, user_id INTEGER, token_hash TEXT UNIQUE,
            expires_at TEXT, used_at TEXT)');
        $this->pdo->exec("INSERT INTO schools VALUES (1, 'ISEP', 'ISEP', 'isep.fr', 'Issy', 1), (2, 'EPITA', 'EPITA', 'epita.fr', NULL, 1),
            (3, 'Fermee', NULL, 'fermee.fr', NULL, 0)");
        $this->pdo->exec("INSERT INTO categories VALUES (1, 'soiree', 'Soiree', NULL, 1), (2, 'sport', 'Sport', NULL, 1),
            (3, 'afterwork', 'Afterwork', NULL, 0), (4, 'culture', 'Culture', NULL, 1)");
        $this->pdo->exec("INSERT INTO users (id, email, email_domain, password_hash, first_name, last_name, school_id)
            VALUES (1, 'deja@isep.fr', 'isep.fr', 'x', 'Deja', 'Inscrit', 1)");

        $this->mailer = new FakeMailer();
        $this->container = $this->containerWith(mailDemo: false);
        $this->service = $this->container->get(RegistrationService::class);
    }

    private function containerWith(bool $mailDemo): Container
    {
        $container = TestContainer::create(new Connection($this->pdo));
        $container->set(Mailer::class, fn (): Mailer => $this->mailer);
        $container->set(Config::class, static fn (): Config => new Config(frontUrl: 'https://front.dayout.test', mailDemo: $mailDemo));
        return $container;
    }

    private function register(array $override = []): RegisteredUserDto
    {
        return $this->service->register(UserCreateDto::fromArray(UserCreateDtoTest::body($override)));
    }

    private function sql(string $sql): PDOStatement
    {
        return $this->pdo->query($sql) ?: throw new RuntimeException("Query failed: $sql");
    }

    private function count(string $table): int
    {
        return (int) $this->sql("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    public function testRegistersAnUnverifiedParticipantWithInterestsAndAHashedToken(): void
    {
        $user = $this->register(['email' => 'Lina.Martin@ISEP.fr', 'search_radius_km' => 7]);

        $this->assertSame(['id' => $user->id, 'email' => 'lina.martin@isep.fr', 'first_name' => 'Lina', 'role' => 'participant',
            'creator_status' => 'none', 'email_verified' => false, 'demo_confirmation_url' => null], $user->jsonSerialize());

        $row = $this->sql("SELECT * FROM users WHERE id = $user->id")->fetch();
        $this->assertSame('lina.martin@isep.fr', $row['email']);
        $this->assertSame('isep.fr', $row['email_domain']);
        $this->assertSame(7, (int) $row['search_radius_km']);
        $this->assertSame(0, (int) $row['email_verified']);
        $this->assertNotSame('Soiree2026', $row['password_hash']);
        $this->assertTrue(Password::verify('Soiree2026', $row['password_hash']), 'bcrypt hash of the password');
        $this->assertSame([1, 2], array_map('intval', $this->sql("SELECT category_id FROM user_interests WHERE user_id = $user->id ORDER BY category_id")->fetchAll(PDO::FETCH_COLUMN)));
        $this->assertSame(0, $this->count('creator_profiles'));

        $this->assertCount(1, $this->mailer->sent);
        $mail = $this->mailer->sent[0];
        $this->assertSame('lina.martin@isep.fr', $mail['to']);
        $token = $this->mailer->lastToken();
        $this->assertStringContains("https://front.dayout.test/confirm-email?token=$token", $mail['body']);
        $verification = $this->sql('SELECT * FROM email_verifications')->fetch();
        $this->assertSame(hash('sha256', $token), $verification['token_hash'], 'only the hash is stored');
        $this->assertSame((int) $user->id, (int) $verification['user_id']);
        $ttl = strtotime($verification['expires_at'] . ' UTC') - time();
        $this->assertTrue($ttl > 86000 && $ttl <= 86400, 'expires in 24 h');
    }

    public function testCreatorStaysParticipantWithAPendingRequest(): void
    {
        $club = $this->register(['profile' => 'creator', 'organization_name' => 'BDE ISEP']);
        $this->assertSame('participant', $club->role->value);
        $this->assertSame(CreatorStatus::Pending, $club->creatorStatus);
        $this->assertStringContains('administrateur', $this->mailer->sent[0]['body']);

        $solo = $this->register(['profile' => 'creator', 'individual' => true, 'email' => 'eve.durand@epita.fr', 'school_id' => 2,
            'first_name' => 'Eve', 'last_name' => 'durand']);
        $profiles = $this->sql('SELECT user_id, display_name FROM creator_profiles ORDER BY user_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->assertSame([$club->id => 'BDE ISEP', $solo->id => 'Eve D.'], array_map('strval', $profiles));
        $this->assertSame(['pending', 'participant'], array_values($this->sql("SELECT creator_status, role FROM users WHERE id = $solo->id")->fetch()));
    }

    public function testRejectsDomainsOutsideTheSchoolsOrNotMatchingTheChosenSchool(): void
    {
        foreach ([
            [['email' => 'lina@gmail.com'], 'email'],
            [['email' => 'lina@fermee.fr', 'school_id' => 3], 'school_id'],
            [['email' => 'lina@fermee.fr'], 'email'],
            [['email' => 'lina@epita.fr'], 'email'],
            [['school_id' => 99], 'school_id'],
            [['interests' => ['soiree', 'inconnue']], 'interests'],
            [['interests' => ['afterwork']], 'interests'],
        ] as [$input, $field]) {
            $error = $this->assertThrows(ValidationException::class, fn () => $this->register($input), json_encode($input) ?: '');
            $this->assertSame([$field], array_keys($error->errors), json_encode($input) ?: '');
        }
        $this->assertSame(1, $this->count('users'));
        $this->assertSame([], $this->mailer->sent);
    }

    public function testRejectsAnEmailAlreadyUsed(): void
    {
        $error = $this->assertThrows(RegistrationException::class, fn () => $this->register(['email' => ' DEJA@isep.fr']));
        $this->assertSame('EMAIL_TAKEN', $error->errorCode);
        $this->assertSame(['field' => 'email'], $error->details);
        $this->assertSame(1, $this->count('users'));
    }

    public function testNothingIsKeptWhenTheMailCannotBeSent(): void
    {
        $this->mailer->fail = true;
        $this->assertThrows(RuntimeException::class, fn () => $this->register(['profile' => 'creator', 'organization_name' => 'BDE']));
        foreach (['users' => 1, 'user_interests' => 0, 'creator_profiles' => 0, 'email_verifications' => 0] as $table => $count) {
            $this->assertSame($count, $this->count($table), $table);
        }
        $this->mailer->fail = false;
        $this->assertSame('lina.martin@isep.fr', $this->register()->email, 'the student can try again');
    }

    public function testDemoModeReturnsTheConfirmationLink(): void
    {
        $user = $this->containerWith(mailDemo: true)->get(RegistrationService::class)
            ->register(UserCreateDto::fromArray(UserCreateDtoTest::body()));
        $this->assertSame('https://front.dayout.test/confirm-email?token=' . $this->mailer->lastToken(), $user->demoConfirmationUrl);
    }

    public function testConfirmationActivatesTheAccountOnce(): void
    {
        $user = $this->register(['profile' => 'creator', 'organization_name' => 'BDE ISEP']);
        $token = $this->mailer->lastToken();

        $this->assertSame(['email_verified' => true, 'creator_status' => 'pending'], $this->service->confirmEmail($token)->jsonSerialize());
        $row = $this->sql("SELECT email_verified, email_verified_at, creator_status FROM users WHERE id = $user->id")->fetch();
        $this->assertSame(1, (int) $row['email_verified']);
        $this->assertTrue($row['email_verified_at'] !== null);
        $this->assertSame('pending', $row['creator_status'], 'the creator request still waits for an admin');
        $this->assertTrue($this->sql('SELECT used_at FROM email_verifications')->fetchColumn() !== null);

        $error = $this->assertThrows(RegistrationException::class, fn () => $this->service->confirmEmail($token));
        $this->assertSame('VERIFICATION_TOKEN_USED', $error->errorCode);
    }

    public function testParticipantConfirmationReturnsNoCreatorRequest(): void
    {
        $this->register();
        $this->assertSame('none', $this->service->confirmEmail($this->mailer->lastToken())->creatorStatus->value);
    }

    public function testExpiredUnknownAndMalformedTokensAreRejected(): void
    {
        $user = $this->register();
        $token = $this->mailer->lastToken();
        $this->pdo->exec("UPDATE email_verifications SET expires_at = '2020-01-01 00:00:00'");
        $error = $this->assertThrows(RegistrationException::class, fn () => $this->service->confirmEmail($token));
        $this->assertSame('VERIFICATION_TOKEN_EXPIRED', $error->errorCode);
        $this->assertSame(0, (int) $this->sql("SELECT email_verified FROM users WHERE id = $user->id")->fetchColumn());

        foreach ([str_repeat('a', 64), strtoupper($token), substr($token, 1), $token . 'a', '', "$token\n"] as $bad) {
            $error = $this->assertThrows(RegistrationException::class, fn () => $this->service->confirmEmail($bad));
            $this->assertSame('VERIFICATION_TOKEN_INVALID', $error->errorCode);
        }
    }

    public function testMalformedTokenIsRejectedBeforeTheDatabase(): void
    {
        $container = TestContainer::create(TestContainer::noDatabase());
        $container->set(Mailer::class, fn (): Mailer => $this->mailer);
        $error = $this->assertThrows(RegistrationException::class, fn () => $container->get(RegistrationService::class)->confirmEmail('../../etc'));
        $this->assertSame('VERIFICATION_TOKEN_INVALID', $error->errorCode);
    }

    public function testControllersAnswer201Then200(): void
    {
        $create = $this->container->get(UserController::class)->create(new Request('POST', '/users',
            headers: ['content-type' => 'application/json'], rawBody: (string) json_encode(UserCreateDtoTest::body())));
        $this->assertSame(201, $create->status);
        $this->assertSame('lina.martin@isep.fr', self::payload($create)['data']->email);

        $confirm = $this->container->get(EmailVerificationController::class)->create(new Request('POST', '/email-verifications',
            headers: ['content-type' => 'application/json'], rawBody: (string) json_encode(['token' => $this->mailer->lastToken()])));
        $this->assertSame(200, $confirm->status);
        $this->assertTrue(self::payload($confirm)['data']->emailVerified);

        $error = $this->assertThrows(ValidationException::class, fn () => $this->container->get(EmailVerificationController::class)
            ->create(new Request('POST', '/email-verifications', headers: ['content-type' => 'application/json'], rawBody: '{}')));
        $this->assertSame(['token'], array_keys($error->errors));
    }

    public function testListsActiveSchoolsAndCategoriesByName(): void
    {
        $schools = array_map(static fn ($s): array => $s->jsonSerialize(), $this->container->get(SchoolService::class)->findActive());
        $this->assertSame(['EPITA', 'ISEP'], array_column($schools, 'name'));
        $this->assertSame(['id' => 1, 'name' => 'ISEP', 'short_name' => 'ISEP', 'email_domain' => 'isep.fr', 'city' => 'Issy'], $schools[1]);

        $categories = array_map(static fn ($c): array => $c->jsonSerialize(), $this->container->get(CategoryService::class)->findActive());
        $this->assertSame(['culture', 'soiree', 'sport'], array_column($categories, 'slug'));
    }
}
