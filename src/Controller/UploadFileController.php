<?php

namespace App\Controller;

use App\Entity\Config;
use App\Form\UploadFileType;
use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class UploadFileController extends AbstractController
{
    private KernelInterface $kernel;
    private EntityManagerInterface $entity_manager;

    public function __construct(KernelInterface $kernel, EntityManagerInterface $entity_manager)
    {
        $this->kernel = $kernel;
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/file/upload', name: 'upload_file')]
    public function uploadFile(Request $request): Response
    {
        /** @var Config $config */
        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        if ($config->getIsSuperActionsEnabled() !== true)
        {
            return $this->redirectToRoute('show_final_pokemon');
        }

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

        $config = $this->entity_manager->getRepository(Config::class)->getConfig();
        return $this->render('upload_file.html.twig', [
            'config' => $config,
            'form' => $form->createView(),
        ]);
    }
}