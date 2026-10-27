<?php

namespace App\Controller\Admin;

use App\Admin\CollectableRegistry;
use App\Entity\Minifigure;
use App\Entity\Pokemon;
use App\Form\Admin\CsvImportFormType;
use App\Service\AdminTaskRunner;
use App\Service\CollectableHealth;
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
    private CollectableRegistry $registry;
    private CollectableHealth $health;
    private string $project_dir;

    public function __construct(EntityManagerInterface $entity_manager, DatabaseManager $database_manager, AdminTaskRunner $task_runner, FormFactoryInterface $form_factory, CollectableRegistry $registry, CollectableHealth $health, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->database_manager = $database_manager;
        $this->task_runner = $task_runner;
        $this->form_factory = $form_factory;
        $this->registry = $registry;
        $this->health = $health;
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * The CSV imports on the tools page. Each one moves the upload to var/<file>,
     * where the DatabaseManager import (and the matching console command) reads it.
     * collectable: CollectableType key of the Tools section the import is shown in.
     * @return array<string, array{collectable: string, title: string, columns: string[], optional_columns: string[], file: string, replace_label: string, replace_text: string}>
     */
    private static function getImports(): array
    {
        return [
            'cards' => [
                'collectable' => 'pokemon',
                'title' => 'Import CSV',
                'columns' => DatabaseManager::CARDS_CSV_COLUMNS,
                'optional_columns' => [],
                'file' => 'cards.csv',
                'replace_label' => 'Replace all cards',
                'replace_text' => 'Every Pokémon card in your collection is deleted and replaced by the cards in this CSV. Images on disk are kept.',
            ],
            'minifigures' => [
                'collectable' => 'lego',
                'title' => 'Import CSV',
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

        // One section per collectable, in sidebar order, with its imports and tasks.
        $sections = [];
        foreach ($this->registry->all() as $collectable)
        {
            $key = $collectable->getKey();
            $sections[] = [
                'collectable' => $collectable,
                'health' => $this->health->get($collectable),
                'fix_tasks' => self::getFixTasks($key),
                'imports' => array_values(array_filter($imports, fn (array $import) => $import['collectable'] === $key)),
                'tasks' => array_values(array_filter($tasks, fn (array $task) => $task['collectable'] === $key)),
            ];
        }

        return $this->render('admin/tools.html.twig', [
            'sections' => $sections,
            'active_key' => 'tools',
        ], $submitted ? new Response(status: 422) : null);
    }

    /**
     * Health summary HTML per collectable key, so the Tools page can refresh
     * it after a task finishes without duplicating the markup in JS.
     */
    #[Route(path: '/admin/tools/health', name: 'admin_tools_health', methods: ['GET'], priority: 11)]
    public function health(): JsonResponse
    {
        $html = [];
        foreach ($this->registry->all() as $collectable)
        {
            $html[$collectable->getKey()] = $this->renderView('admin/tools/_health.html.twig', [
                'collectable' => $collectable,
                'health' => $this->health->get($collectable),
                'fix_tasks' => self::getFixTasks($collectable->getKey()),
            ]);
        }

        return new JsonResponse($html);
    }

    /**
     * Which task fixes each health issue of a collectable ("missing_images" => "card_images").
     * @return array<string, string>
     */
    private static function getFixTasks(string $collectable): array
    {
        $fix_tasks = [];
        foreach (AdminTaskRunner::getTasks() as $key => $task)
        {
            if ($task['collectable'] === $collectable)
            {
                foreach ($task['fixes'] as $issue)
                {
                    $fix_tasks[$issue] ??= $key;
                }
            }
        }
        return $fix_tasks;
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

    #[Route(path: '/admin/tools/{task}/cancel', name: 'admin_tools_cancel', requirements: ['task' => '[a-z_]+'], methods: ['POST'], priority: 10)]
    public function cancel(Request $request, string $task): Response
    {
        $definition = AdminTaskRunner::getTasks()[$task] ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('cancel-'.$task, $request->request->getString('_token')))
        {
            $this->addFlash('error', 'Your session expired, please try again.');
        }
        elseif ($this->task_runner->cancel($task))
        {
            $this->addFlash('success', sprintf('Cancelled "%s". What it finished before stopping is kept.', $definition['label']));
        }
        else
        {
            $this->addFlash('warning', sprintf('"%s" was not running anymore.', $definition['label']));
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
            [$message, $imported] = $this->entity_manager->wrapInTransaction(fn () => match ($key) {
                'cards' => $this->importCards($delimiter, $replace),
                'minifigures' => $this->importMinifigures($delimiter, $replace),
            });
        }
        catch (\Throwable $e)
        {
            return 'Import failed, nothing was changed: '.$e->getMessage();
        }

        $this->addFlash('success', $message);
        // Downloading can take minutes for a big import, so it runs in the background.
        $download_task = ['cards' => 'card_images', 'minifigures' => 'minifigure_downloads'][$key];
        if ($imported > 0 && $this->task_runner->start($download_task))
        {
            $this->addFlash('success', sprintf('Downloading the %s images in the background. You can follow it below.', $key === 'cards' ? 'card' : 'minifigure'));
        }
        return null;
    }

    /** @return array{0: string, 1: int} message and number of imported cards */
    private function importCards(string $delimiter, bool $replace): array
    {
        if ($replace)
        {
            $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
        }
        $imported = $this->database_manager->importCsvFile($delimiter);

        return [sprintf('%s %d card%s.', $replace ? 'Replaced the collection with' : 'Imported', $imported, $imported === 1 ? '' : 's'), $imported];
    }

    /** @return array{0: string, 1: int} message and number of imported minifigures */
    private function importMinifigures(string $delimiter, bool $replace): array
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
        return [$message, $report['imported']];
    }
}
