<?php

namespace App\Controller\Admin;

use App\Entity\Minifigure;
use App\Entity\Pokemon;
use App\Form\Admin\CsvImportFormType;
use App\Service\AdminTaskRunner;
use App\Service\DatabaseManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin tools: CSV imports and background maintenance tasks.
 * Routes have a priority so they win over the catch-all /admin/{type}.
 */
class ToolsController extends AbstractController
{
    private EntityManagerInterface $entity_manager;
    private DatabaseManager $database_manager;
    private AdminTaskRunner $task_runner;
    private FormFactoryInterface $form_factory;
    private string $project_dir;

    public function __construct(EntityManagerInterface $entity_manager, DatabaseManager $database_manager, AdminTaskRunner $task_runner, FormFactoryInterface $form_factory, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->database_manager = $database_manager;
        $this->task_runner = $task_runner;
        $this->form_factory = $form_factory;
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * The CSV imports on the tools page. Each one moves the upload to var/<file>,
     * where the DatabaseManager import (and the matching console command) reads it.
     * @return array<string, array{title: string, columns: string[], optional_columns: string[], file: string, replace_label: string, replace_text: string}>
     */
    private static function getImports(): array
    {
        return [
            'cards' => [
                'title' => 'Import cards CSV',
                'columns' => DatabaseManager::CARDS_CSV_COLUMNS,
                'optional_columns' => [],
                'file' => 'cards.csv',
                'replace_label' => 'Replace all cards',
                'replace_text' => 'Every Pokémon card in your collection is deleted and replaced by the cards in this CSV. Images on disk are kept.',
            ],
            'minifigures' => [
                'title' => 'Import minifigures CSV',
                'columns' => DatabaseManager::MINIFIGURES_CSV_COLUMNS,
                'optional_columns' => ['Url'],
                'file' => 'minifigures.csv',
                'replace_label' => 'Replace all minifigures',
                'replace_text' => 'Every minifigure in your collection is deleted and replaced by the minifigures in this CSV. Images on disk are kept.',
            ],
        ];
    }

    #[Route(path: '/admin/tools', name: 'admin_tools', methods: ['GET', 'POST'], priority: 10)]
    public function index(Request $request): Response
    {
        $imports = [];
        $submitted = false;
        foreach (self::getImports() as $key => $import)
        {
            $form = $this->form_factory->createNamed($key.'_import', CsvImportFormType::class, null, [
                'replace_label' => $import['replace_label'],
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted())
            {
                $submitted = true;
                if ($form->isValid())
                {
                    $data = $form->getData();
                    $error = $this->import($key, $data['file'], $data['delimiter'], $data['mode'] === CsvImportFormType::MODE_REPLACE);
                    if ($error === null)
                    {
                        return $this->redirectToRoute('admin_tools');
                    }
                    $this->addFlash('error', $error);
                }
            }
            $imports[] = $import + ['key' => $key, 'form' => $form->createView()];
        }

        $tasks = [];
        foreach (AdminTaskRunner::getTasks() as $key => $task)
        {
            $tasks[] = $task + ['key' => $key, 'status' => $this->task_runner->getStatus($key)];
        }

        return $this->render('admin/tools.html.twig', [
            'imports' => $imports,
            'tasks' => $tasks,
            'active_key' => 'tools',
        ], $submitted ? new Response(status: 422) : null);
    }

    #[Route(path: '/admin/tools/{task}/run', name: 'admin_tools_run', requirements: ['task' => '[a-z_]+'], methods: ['POST'], priority: 10)]
    public function run(Request $request, string $task): Response
    {
        $definition = AdminTaskRunner::getTasks()[$task] ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('run-'.$task, $request->request->getString('_token')))
        {
            $this->addFlash('error', 'Your session expired, please try again.');
        }
        elseif ($this->task_runner->start($task))
        {
            $this->addFlash('success', sprintf('Started "%s". You can leave this page; it keeps running.', $definition['label']));
        }
        else
        {
            $this->addFlash('warning', sprintf('"%s" is already running.', $definition['label']));
        }

        return $this->redirectToRoute('admin_tools');
    }

    #[Route(path: '/admin/tools/{task}/status', name: 'admin_tools_status', requirements: ['task' => '[a-z_]+'], methods: ['GET'], priority: 10)]
    public function status(string $task): JsonResponse
    {
        if (!isset(AdminTaskRunner::getTasks()[$task]))
        {
            throw $this->createNotFoundException();
        }

        return new JsonResponse($this->task_runner->getStatus($task));
    }

    /**
     * @return string|null an error message, or null on success (a flash is added)
     */
    private function import(string $key, UploadedFile $file, string $delimiter, bool $replace): ?string
    {
        $import = self::getImports()[$key];
        $path = $this->project_dir.'/var/'.$import['file'];
        try
        {
            $file->move(\dirname($path), basename($path));
            $csv = Reader::createFromPath($path, 'r');
            $csv->setDelimiter($delimiter);
            $csv->setHeaderOffset(0);
            $missing = array_diff($import['columns'], $csv->getHeader());
        }
        catch (\Throwable $e)
        {
            return 'The file could not be read: '.$e->getMessage();
        }
        if (!empty($missing))
        {
            return sprintf('The CSV is missing the column(s) %s. Is the delimiter right?', implode(', ', $missing));
        }

        try
        {
            // One transaction: a failed import never leaves you with an emptied collection.
            $message = $this->entity_manager->wrapInTransaction(fn () => match ($key) {
                'cards' => $this->importCards($delimiter, $replace),
                'minifigures' => $this->importMinifigures($delimiter, $replace),
            });
        }
        catch (\Throwable $e)
        {
            return 'Import failed, nothing was changed: '.$e->getMessage();
        }

        $this->addFlash('success', $message);
        return null;
    }

    private function importCards(string $delimiter, bool $replace): string
    {
        if ($replace)
        {
            $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
        }
        $imported = $this->database_manager->importCsvFile($delimiter);

        return sprintf('%s %d card%s.', $replace ? 'Replaced the collection with' : 'Imported', $imported, $imported === 1 ? '' : 's');
    }

    private function importMinifigures(string $delimiter, bool $replace): string
    {
        if ($replace)
        {
            $this->entity_manager->getRepository(Minifigure::class)->clearMinifigures();
        }
        $report = $this->database_manager->importMinifiguresCsv($delimiter);

        $message = sprintf('%s %d minifigure%s.', $replace ? 'Replaced the collection with' : 'Imported', $report['imported'], $report['imported'] === 1 ? '' : 's');
        $skipped = \count($report['skipped']);
        if ($skipped > 0)
        {
            $message .= sprintf(' Skipped %d already in your collection.', $skipped);
        }
        return $message;
    }
}
