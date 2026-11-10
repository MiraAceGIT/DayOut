<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Http\RequireAuth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Model\Dao\Event\Event;
use App\Model\Dao\School\School;
use App\Model\Services\TransportService;
use App\Model\Transport\OverpassTransportProvider;

final class TransportController extends Controller
{
    private ?TransportService $transport;

    /** TransportService injectable (tests) ; defaut = source Overpass + cache storage/cache/transport. */
    public function __construct(?TransportService $transport = null)
    {
        $this->transport = $transport;
    }

    public function forEvent(Request $request): Response
    {
        return $this->ok($this->service()->forEvent(self::eventId($request), self::optionalUserId($request)));
    }

    private static function eventId(Request $request): int
    {
        $rawId = $request->param('id');
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || preg_match('/^[1-9][0-9]*$/D', $rawId) !== 1) {
            throw HttpException::notFound('Event not found');
        }
        return $id;
    }

    /** Auth facultative : avec un token valide, on utilise l'ecole de l'utilisateur pour pre-remplir le depart. */
    private static function optionalUserId(Request $request): ?int
    {
        $userId = $request->attribute(RequireAuth::USER_ID);
        return is_int($userId) ? $userId : null;
    }

    private function service(): TransportService
    {
        if ($this->transport !== null) {
            return $this->transport;
        }
        $cacheDir = defined('APP_ROOT') ? APP_ROOT . '/storage/cache/transport' : '';
        $provider = new OverpassTransportProvider(cacheDir: $cacheDir);
        return $this->transport = new TransportService($provider, new Event(), new School());
    }
}
