<?php
declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Log\LogWriter;

/**
 * Pas de serveur de mail dans le projet : chaque mail est ecrit, une ligne JSON par mail, dans
 * LOG_PATH/mail-AAAA-MM-JJ.log (meme rotation que les logs). Fichier a part, jamais dans info ou audit :
 * un mail contient des liens a usage unique (jeton de confirmation). En production (APP_ENV=prod), seuls le
 * destinataire et le sujet sont gardes : un fichier de log lu par erreur ne doit pas permettre de confirmer
 * l'adresse d'un autre. Un vrai envoi (SMTP) = une autre classe qui implemente Mailer.
 */
final class LogMailer implements Mailer
{
    public const CHANNEL = 'mail';

    public const BODY_OMITTED = '[contenu non journalise en production]';

    private readonly LogWriter $writer;

    public function __construct(private readonly Config $config, private readonly Clock $clock)
    {
        $this->writer = new LogWriter($config->logPath, [self::CHANNEL => $config->logRetentionDays]);
    }

    public function send(string $to, string $subject, string $body): void
    {
        $this->writer->append(self::CHANNEL, json_encode([
            'timestamp' => $this->clock->now()->format('Y-m-d\TH:i:s.v\Z'),
            'to'        => $to,
            'subject'   => $subject,
            'body'      => $this->config->isProduction() ? self::BODY_OMITTED : $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
