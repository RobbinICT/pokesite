<?php

namespace App\Controller;

use App\Form\UploadFileType;
use App\Service\ConfigManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use function Symfony\Component\Clock\now;

class UploadFileController extends AbstractController
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    #[Route(path: '/file/upload', name: 'upload_file')]
    public function uploadFile(Request $request): Response
    {
        $form = $this->createForm(UploadFileType::class);
        if ($request->isMethod('POST'))
        {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid())
            {
                /** @var UploadedFile $file */
                $file = $form->get('cards_file')->getData();
                if ($file)
                {
                    $new_file_name = 'cards.csv';
                    try
                    {
                        $base_dir = $this->kernel->getProjectDir();
                        $path = $base_dir . '/var/';
                        $file->move($path, $new_file_name);
                    }
                    catch (FileException $e)
                    {
                        return new JsonResponse($e->getMessage());
                    }
                }
                else
                {
                    return new JsonResponse('no file found');
                }
                return $this->redirectToRoute('show_final_pokemon');
            }
        }

        return $this->render('upload_file.html.twig', [
            ConfigManager::ENV_VAR_SUPER_ADMIN => ConfigManager::getSuperAdminEnvironmentVariable(),
            'form' => $form->createView(),
        ]);
    }
}