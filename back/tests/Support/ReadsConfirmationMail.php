<?php
declare(strict_types=1);

namespace Tests\Support;

/** Pour un HttpTestCase : jeton du dernier lien de confirmation envoye (mail-*.log du serveur de test). */
trait ReadsConfirmationMail
{
    protected function confirmationTokenFor(string $email): string
    {
        $mails = array_values(array_filter($this->readLog('mail'), static fn (array $m): bool => $m['to'] === $email));
        $this->assertTrue($mails !== [], "mail sent to $email");
        preg_match('/[?&]token=([0-9a-f]{64})/', (string) ($mails[count($mails) - 1]['body'] ?? ''), $match);
        $this->assertCount(2, $match, 'confirmation link in the mail');
        return $match[1];
    }
}
