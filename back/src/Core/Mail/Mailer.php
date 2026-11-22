<?php
declare(strict_types=1);

namespace App\Core\Mail;

/**
 * Envoi d'un mail en texte brut, commun aux fonctionnalites qui ecrivent aux utilisateurs
 * (confirmation d'inscription, mot de passe oublie). Implementation choisie dans config/container.php.
 */
interface Mailer
{
    /** Leve une exception si le mail n'a pas pu partir. */
    public function send(string $to, string $subject, string $body): void;
}
