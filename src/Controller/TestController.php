<?php

namespace App\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends ArrayCollection
{
    #[Route(path: '/test', name: 'test')]
    public function test(Request $request)
    {
        try {

            return new JsonResponse([
                'message' => 'test'
            ], Response::HTTP_OK);
        }
        catch (\Exception $e)
        {
            return new JsonResponse([
                'message' => "{$e->getMessage()}"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}