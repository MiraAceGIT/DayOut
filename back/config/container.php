<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Mail\LogMailer;
use App\Core\Mail\Mailer;
use App\Core\Orm\Connection;

/**
 * Racine de composition : seules les dependances que le Container ne sait pas construire seul
 * (valeurs, connexion) sont declarees ici ; le reste est resolu par les types des constructeurs.
 * Une seule Connection par conteneur : une transaction ouverte couvre tous les models de la requete.
 *
 * @return Closure(Config): Container
 */
return static function (Config $config): Container {
    $container = new Container();
    $container->set(Config::class, static fn (): Config => $config);
    $container->set(Connection::class, static fn (): Connection => Database::connection($config));
    $container->set(Mailer::class, static fn (Container $c): Mailer => $c->get(LogMailer::class));
    return $container;
};
