<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Services\FaqService;

final class FaqController extends Controller
{
    public function __construct(private ?FaqService $faq = null)
    {
    }

    public function index(Request $request): Response
    {
        $this->faq ??= new FaqService();

        return $this->ok($this->faq->listVisible());
    }
}
