<?php
declare(strict_types=1);

namespace Tests\Unit\Core\Mail;

use App\Core\Config;
use App\Core\Mail\LogMailer;
use Tests\Support\FixedClock;
use Tests\TestCase;

final class LogMailerTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/dayout-mail-' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    /** @return array<string, string> derniere ligne du log des mails */
    private function send(string $appEnv): array
    {
        (new LogMailer(new Config(logPath: $this->dir, appEnv: $appEnv), new FixedClock()))
            ->send('lina@isep.fr', 'Confirme ton adresse', 'Lien : https://front/confirm-email?token=' . str_repeat('a', 64));
        $lines = file($this->dir . '/mail-' . gmdate('Y-m-d') . '.log', FILE_IGNORE_NEW_LINES) ?: [];
        return json_decode((string) end($lines), true);
    }

    public function testWritesTheWholeMailInDevelopment(): void
    {
        $mail = $this->send(Config::ENV_DEV);
        $this->assertSame(['timestamp', 'to', 'subject', 'body'], array_keys($mail));
        $this->assertSame('2026-11-23T10:00:00.000Z', $mail['timestamp']);
        $this->assertStringContains('token=', $mail['body']);
    }

    public function testNeverWritesTheLinkInProduction(): void
    {
        $mail = $this->send(Config::ENV_PROD);
        $this->assertSame(['lina@isep.fr', 'Confirme ton adresse', LogMailer::BODY_OMITTED], [$mail['to'], $mail['subject'], $mail['body']]);
    }
}
