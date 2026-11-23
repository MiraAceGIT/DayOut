<?php
declare(strict_types=1);

namespace App\Model\Mails;

/** Texte du mail de confirmation d'adresse (inscription), a part du service qui l'envoie. */
final class ConfirmationMail
{
    public const SUBJECT = 'Confirme ton adresse e-mail DayOut';

    public static function body(string $firstName, bool $creator, string $link): string
    {
        $next = $creator
            ? "Ton compte sera actif dès la confirmation. Ta demande de compte créateur sera ensuite examinée par un administrateur :\ntu pourras publier des événements une fois qu'elle sera validée."
            : 'Ton compte sera actif dès la confirmation.';
        return "Bonjour {$firstName},\n\n"
            . "Pour finaliser ton inscription sur DayOut, confirme ton adresse e-mail en ouvrant ce lien (valable 24 h, une seule fois) :\n"
            . "$link\n\n$next\n\n"
            . "Si tu n'es pas à l'origine de cette inscription, ignore ce message.";
    }
}
