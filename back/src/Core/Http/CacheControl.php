<?php
declare(strict_types=1);

namespace App\Core\Http;

/** Valeurs de l'en-tete Cache-Control partagees par les routes. */
final class CacheControl
{
    /**
     * Referentiels stables (ecoles, categories) : cache partage 1 h. Une modification du back-office (SCRUM-78) se voit
     * au plus 1 h apres.
     */
    public const REFERENCE_DATA = 'public, max-age=3600';
}
