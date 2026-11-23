<?php
declare(strict_types=1);

namespace App\Model\DomainServices;

/** Nom public d'une demande createur : l'association, ou « Prenom N. » a titre individuel (jamais le nom complet). */
final class CreatorDisplayName
{
    public static function of(string $firstName, string $lastName, ?string $organizationName): string
    {
        if ($organizationName !== null) {
            return $organizationName;
        }
        $initial = mb_substr($lastName, 0, 1);
        return $firstName . ($initial === '' ? '' : ' ' . mb_strtoupper($initial) . '.');
    }
}
