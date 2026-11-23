<?php
declare(strict_types=1);

namespace Tests\Support;

/** Pour un HttpTestCase : jeton du dernier lien de confirmation envoye (mail-*.log du serveur de test). */
trait ReadsConfirmationMail
{
    /** Tous les tests viennent de 127.0.0.1 : sans remise a zero, la limite d'inscriptions par IP les bloquerait. */
    public function setUp(): void
    {
        TestDatabase::pdo()->exec('DELETE FROM rate_limit_hits');
    }

    protected function confirmationTokenFor(string $email): string
    {
        $mails = array_values(array_filter($this->readLog('mail'), static fn (array $m): bool => $m['to'] === $email));
        $this->assertTrue($mails !== [], "mail sent to $email");
        preg_match(FakeMailer::TOKEN_PATTERN, (string) ($mails[count($mails) - 1]['body'] ?? ''), $match);
        $this->assertCount(2, $match, 'confirmation link in the mail');
        return $match[1];
    }
}
