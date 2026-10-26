<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // Priority keeps these ahead of the catch-all /admin/{type} route.
    #[Route(path: '/admin/login', name: 'admin_login', priority: 10)]
    public function login(AuthenticationUtils $authentication_utils): Response
    {
        if ($this->isGranted('ROLE_ADMIN'))
        {
            return $this->redirectToRoute('admin_index');
        }

        return $this->render('admin/login.html.twig', [
            'last_username' => $authentication_utils->getLastUsername(),
            'error' => $authentication_utils->getLastAuthenticationError(),
        ]);
    }

    #[Route(path: '/admin/logout', name: 'admin_logout', priority: 10)]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }
}
