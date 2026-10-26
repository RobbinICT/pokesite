<?php

namespace App\Controller\Admin;

use App\Entity\Pokemon;
use App\Form\Admin\CardsImportFormType;
use App\Service\AdminTaskRunner;
use App\Service\DatabaseManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin tools: cards CSV import and background maintenance tasks.
 * Routes have a priority so they win over the catch-all /admin/{type}.
 */
class ToolsController extends AbstractController
{
    private EntityManagerInterface $entity_manager;
    private DatabaseManager $database_manager;
    private AdminTaskRunner $task_runner;
    private string $project_dir;

    public function __construct(EntityManagerInterface $entity_manager, DatabaseManager $database_manager, AdminTaskRunner $task_runner, KernelInterface $kernel)
    {
        $this->entity_manager = $entity_manager;
        $this->database_manager = $database_manager;
        $this->task_runner = $task_runner;
        $this->project_dir = $kernel->getProjectDir();
    }

    #[Route(path: '/admin/tools', name: 'admin_tools', methods: ['GET', 'POST'], priority: 10)]
    public function index(Request $request): Response
    {
        $form = $this->createForm(CardsImportFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $data = $form->getData();
            $error = $this->importCards($data['cards_file'], $data['delimiter'], $data['mode'] === CardsImportFormType::MODE_REPLACE);
            if ($error === null)
            {
                return $this->redirectToRoute('admin_tools');
            }
            $this->addFlash('error', $error);
        }

        $tasks = [];
        foreach (AdminTaskRunner::getTasks() as $key => $task)
        {
            $tasks[] = $task + ['key' => $key, 'status' => $this->task_runner->getStatus($key)];
        }

        return $this->render('admin/tools.html.twig', [
            'import_form' => $form,
            'tasks' => $tasks,
            'active_key' => 'tools',
        ], $form->isSubmitted() ? new Response(status: 422) : null);
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
    private function importCards(UploadedFile $file, string $delimiter, bool $replace): ?string
    {
        // importCsvFile() reads var/cards.csv, like the update:database:cards command.
        try
        {
            $file->move($this->project_dir.'/var', 'cards.csv');
            $csv = Reader::createFromPath($this->project_dir.'/var/cards.csv', 'r');
            $csv->setDelimiter($delimiter);
            $csv->setHeaderOffset(0);
            $missing = array_diff(DatabaseManager::CARDS_CSV_COLUMNS, $csv->getHeader());
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
            $imported = $this->entity_manager->wrapInTransaction(function () use ($delimiter, $replace) {
                if ($replace)
                {
                    $this->entity_manager->getRepository(Pokemon::class)->clearPokemon();
                }
                return $this->database_manager->importCsvFile($delimiter);
            });
        }
        catch (\Throwable $e)
        {
            return 'Import failed, nothing was changed: '.$e->getMessage();
        }

        $this->addFlash('success', sprintf('%s %d card%s.', $replace ? 'Replaced the collection with' : 'Imported', $imported, $imported === 1 ? '' : 's'));
        return null;
    }
}
