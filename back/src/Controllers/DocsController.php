<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Docs\OpenApiSpec;

final class DocsController extends Controller
{
    private const SWAGGER_UI = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14';
    private const SWAGGER_UI_CSS_SRI = 'sha384-wxLW6kwyHktdDGr6Pv1zgm/VGJh99lfUbzSn6HNHBENZlCN7W602k9VkGdxuFvPn';
    private const SWAGGER_UI_JS_SRI  = 'sha384-wmyclcVGX/WhUkdkATwhaK1X1JtiNrr2EoYJ+diV3vj4v6OC5yCeSu+yW13SYJep';

    // Spec cherchee a cote de la page : marche aussi sous un prefixe XAMPP et avec /api/docs/.
    private const INIT_SCRIPT = 'SwaggerUIBundle({ url: location.pathname.replace(/\/docs\/?$/, "/openapi.json"),'
        . ' dom_id: "#swagger-ui", deepLinking: true });';

    private readonly string $specDir;

    public function __construct(?string $specDir = null)
    {
        $this->specDir = $specDir ?? dirname(__DIR__, 2) . '/docs/openapi';
    }

    /**
     * En JSON et hors enveloppe `data` : c'est ce que lisent Swagger UI et les generateurs de client.
     * Relue a chaque appel (quelques Ko) : une modif des YAML est visible sans redemarrer.
     */
    public function spec(Request $request): Response
    {
        $json = json_encode(OpenApiSpec::load($this->specDir), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return Response::raw($json, 'application/json; charset=utf-8');
    }

    public function ui(Request $request): Response
    {
        [$cdn, $cssSri, $jsSri, $init] = [self::SWAGGER_UI, self::SWAGGER_UI_CSS_SRI, self::SWAGGER_UI_JS_SRI, self::INIT_SCRIPT];
        $html = <<<HTML
            <!doctype html>
            <html lang="fr">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>DayOut API</title>
              <link rel="stylesheet" href="$cdn/swagger-ui.css" integrity="$cssSri" crossorigin="anonymous">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="$cdn/swagger-ui-bundle.js" integrity="$jsSri" crossorigin="anonymous"></script>
              <script>$init</script>
            </body>
            </html>
            HTML;

        return Response::raw($html, 'text/html; charset=utf-8')
            ->withHeader('Content-Security-Policy', $this->csp());
    }

    /** La seule page HTML de l'API : scripts du CDN epingles + script d'init autorise par son hash, rien d'autre. */
    private function csp(): string
    {
        $initHash = base64_encode(hash('sha256', self::INIT_SCRIPT, true));
        return implode('; ', [
            "default-src 'none'",
            'script-src ' . self::SWAGGER_UI . "/ 'sha256-$initHash'",
            'style-src ' . self::SWAGGER_UI . "/ 'unsafe-inline'", // Swagger UI pose des attributs style
            "img-src 'self' data:",
            "connect-src 'self'", // la spec et "Try it out"
            "base-uri 'none'",
            "form-action 'none'",
            "frame-ancestors 'none'",
        ]);
    }
}
