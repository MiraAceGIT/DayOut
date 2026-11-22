<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Mail\Mailer;
use RuntimeException;

/** Mailer des tests unitaires : garde les mails en memoire, ou echoue sur demande. */
final class FakeMailer implements Mailer
{
    /** @var list<array{to: string, subject: string, body: string}> */
    public array $sent = [];

    public bool $fail = false;

    public function send(string $to, string $subject, string $body): void
    {
        if ($this->fail) {
            throw new RuntimeException('Mail server unavailable');
        }
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    }

    /** Jeton du dernier lien de confirmation envoye. */
    public function lastToken(): string
    {
        $last = end($this->sent) ?: throw new RuntimeException('No mail sent');
        preg_match('/[?&]token=([0-9a-f]{64})/', $last['body'], $match) === 1 ?: throw new RuntimeException('No token in mail');
        return $match[1];
    }
}
