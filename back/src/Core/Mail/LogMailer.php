<?php
declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Config;
use App\Core\Log\LogWriter;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Pas de serveur de mail dans le projet : chaque mail est ecrit, une ligne JSON par mail, dans
 * LOG_PATH/mail-AAAA-MM-JJ.log (meme rotation que les logs). Fichier a part, jamais dans info ou audit :
 * un mail contient des liens a usage unique (jeton de confirmation).
 */
final class LogMailer implements Mailer
{
    public const CHANNEL = 'mail';

    private readonly LogWriter $writer;

    public function __construct(Config $config)
    {
        $this->writer = new LogWriter($config->logPath, [self::CHANNEL => $config->logRetentionDays]);
    }

    public function send(string $to, string $subject, string $body): void
    {
        $this->writer->append(self::CHANNEL, json_encode([
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
            'to'        => $to,
            'subject'   => $subject,
            'body'      => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
