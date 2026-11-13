<?php
declare(strict_types=1);

// php -S sans routeur ne passe par index.php que pour les URL sans point : /openapi.json et /events/1.5
// recevraient un 404 HTML sur PHP < 8.5. Ce routeur envoie toutes les requetes au front controller.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 2) . '/public/index.php';

require $_SERVER['SCRIPT_FILENAME'];
