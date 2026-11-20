<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Env;
use Tests\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesKeysValuesQuotesAndComments(): void
    {
        $content = "# commentaire\n\nDB_HOST = 127.0.0.1\r\nDB_PASSWORD=\"p#ss word\"\nLOG_LEVEL=debug   # fin de ligne\n"
            . "EMPTY=\nURL=https://x.test/a#b\nSINGLE='a b'\n";

        $this->assertSame([
            'DB_HOST'     => '127.0.0.1',
            'DB_PASSWORD' => 'p#ss word',
            'LOG_LEVEL'   => 'debug',
            'EMPTY'       => '',
            'URL'         => 'https://x.test/a#b',
            'SINGLE'      => 'a b',
        ], Env::parse($content));
    }

    public function testExistingEnvironmentWins(): void
    {
        $file = getenv('TEST_TMP_DIR') . '/env-' . bin2hex(random_bytes(4));
        file_put_contents($file, "ENV_TEST_KEPT=from-file\nENV_TEST_NEW=from-file\n");
        putenv('ENV_TEST_KEPT=from-env');
        try {
            Env::load($file);
            $this->assertSame('from-env', getenv('ENV_TEST_KEPT'));
            $this->assertSame('from-file', getenv('ENV_TEST_NEW'));
            Env::load($file . '-missing'); // fichier absent : rien a charger, pas d'erreur
        } finally {
            putenv('ENV_TEST_KEPT');
            putenv('ENV_TEST_NEW');
            unset($_ENV['ENV_TEST_NEW']);
            @unlink($file);
        }
    }
}
