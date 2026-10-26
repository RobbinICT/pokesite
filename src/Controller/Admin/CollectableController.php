<?php

namespace App\Controller\Admin;

use App\Admin\CollectableRegistry;
use App\Admin\CollectableType;
use App\Service\CollectableImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use League\Csv\Bom;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generic CRUD for every registered CollectableType (see App\Admin).
 */
#[Route(path: '/admin')]
class CollectableController extends AbstractController
{
    private const PER_PAGE = 50;

    private EntityManagerInterface $entity_manager;
    private CollectableRegistry $registry;
    private CollectableImageStorage $image_storage;

    public function __construct(EntityManagerInterface $entity_manager, CollectableRegistry $registry, CollectableImageStorage $image_storage)
    {
        $this->entity_manager = $entity_manager;
        $this->registry = $registry;
        $this->image_storage = $image_storage;
    }

    #[Route(path: '', name: 'admin_index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_list', ['type' => $this->registry->first()->getKey()]);
    }

    #[Route(path: '/{type}', name: 'admin_list', requirements: ['type' => '[a-z0-9-]+'], methods: ['GET'])]
    public function list(Request $request, string $type): Response
    {
        $collectable = $this->registry->get($type);
        $list = $this->buildListQuery($request, $collectable);

        $paginator = new Paginator($list['qb']->getQuery());
        $total = \count($paginator);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $request->query->getInt('page', 1)), $pages);
        $paginator->getQuery()
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $rows = [];
        foreach ($paginator as $item)
        {
            $rows[] = [
                'id' => $item->getId(),
                'cells' => $collectable->getCells($item),
                'title' => $collectable->getTitle($item),
                'subtitle' => $collectable->getSubtitle($item),
                'badge' => $collectable->getBadge($item),
                'image' => $this->image_storage->getDisplayUrl($collectable, $item),
                'has_url' => (bool) $collectable->getImageUrl($item),
            ];
        }

        return $this->renderAdmin('admin/list.html.twig', $collectable, [
            'rows' => $rows,
            'search' => $list['search'],
            'sort' => $list['sort'],
            'dir' => $list['dir'],
            'filters' => $list['filters'],
            'active_filters' => $list['active_filters'],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    /**
     * CSV of the list as shown (search, filters and sort applied), in the
     * same format as the Tools import so it can be edited and re-imported.
     */
    #[Route(path: '/{type}/export', name: 'admin_export', requirements: ['type' => '[a-z0-9-]+'], methods: ['GET'])]
    public function export(Request $request, string $type): Response
    {
        $collectable = $this->registry->get($type);
        $list = $this->buildListQuery($request, $collectable);

        $csv = Writer::createFromString();
        $csv->setDelimiter(';');
        // BOM so Excel and Google Sheets read accents (Pokémon) as UTF-8.
        $csv->setOutputBOM(Bom::Utf8);
        $csv->insertOne($collectable->getCsvColumns());
        foreach ($list['qb']->getQuery()->toIterable() as $item)
        {
            $csv->insertOne($collectable->toCsvRow($item));
        }

        $response = new Response($csv->toString());
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            sprintf('%s-%s.csv', $collectable->getKey(), date('Y-m-d'))
        ));
        return $response;
    }

    #[Route(path: '/{type}/bulk-delete', name: 'admin_bulk_delete', requirements: ['type' => '[a-z0-9-]+'], methods: ['POST'])]
    public function bulkDelete(Request $request, string $type): Response
    {
        $collectable = $this->registry->get($type);
        $return_url = $this->getReturnUrl($request, $collectable);

        if (!$this->isCsrfTokenValid('bulk-delete-'.$type, $request->request->getString('_token')))
        {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirect($return_url);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', array_filter($request->request->all('ids'), 'is_scalar')),
            fn (int $id) => $id > 0
        )));
        $items = $ids === [] ? [] : $this->entity_manager->getRepository($collectable->getEntityClass())->findBy(['id' => $ids]);
        if ($items === [])
        {
            $this->addFlash('warning', sprintf('No %s selected.', $collectable->getLabel()));
            return $this->redirect($return_url);
        }

        // Form data is needed after the rows are gone, to look for duplicates.
        $deleted = [];
        foreach ($items as $item)
        {
            $deleted[] = [$item, $collectable->toFormData($item), $collectable->getLocalImagePath($item)];
            $this->entity_manager->remove($item);
        }
        $this->entity_manager->flush();
        foreach ($deleted as [$item, $data, $path])
        {
            $this->removeImageIfUnused($collectable, $item, $data, $path);
        }

        $count = \count($items);
        $this->addFlash('success', sprintf('Deleted %d %s.', $count, $count === 1 ? $collectable->getItemLabel() : $collectable->getLabel()));
        return $this->redirect($return_url);
    }

    #[Route(path: '/{type}/new', name: 'admin_new', requirements: ['type' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    public function new(Request $request, string $type): Response
    {
        $collectable = $this->registry->get($type);

        // "Save & add another" carries some fields (like the serie) over.
        $data = $collectable->toFormData(null);
        $prefill = $request->query->all('prefill');
        foreach ($collectable->getAddAnotherFields() as $field)
        {
            if (isset($prefill[$field]) && \is_string($prefill[$field]))
            {
                $data[$field] = $prefill[$field];
            }
        }

        return $this->handleForm($request, $collectable, $data, null);
    }

    #[Route(path: '/{type}/{id}/edit', name: 'admin_edit', requirements: ['type' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $type, int $id): Response
    {
        $collectable = $this->registry->get($type);
        $item = $this->findOr404($collectable, $id);

        return $this->handleForm($request, $collectable, $collectable->toFormData($item), $item);
    }

    #[Route(path: '/{type}/{id}/delete', name: 'admin_delete', requirements: ['type' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, string $type, int $id): Response
    {
        $collectable = $this->registry->get($type);
        $item = $this->findOr404($collectable, $id);
        $return_url = $this->getReturnUrl($request, $collectable);

        if (!$this->isCsrfTokenValid('delete-'.$type.'-'.$id, $request->request->getString('_token')))
        {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirect($return_url);
        }

        $title = $collectable->getTitle($item);
        $data = $collectable->toFormData($item);
        $path = $collectable->getLocalImagePath($item);

        $this->entity_manager->remove($item);
        $this->entity_manager->flush();
        $this->removeImageIfUnused($collectable, $item, $data, $path);

        $this->addFlash('success', sprintf('Deleted "%s".', $title));
        return $this->redirect($return_url);
    }

    #[Route(path: '/{type}/{id}/image', name: 'admin_redownload', requirements: ['type' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['POST'])]
    public function redownload(Request $request, string $type, int $id): Response
    {
        $collectable = $this->registry->get($type);
        $item = $this->findOr404($collectable, $id);
        $return_url = $this->getReturnUrl($request, $collectable);

        if (!$this->isCsrfTokenValid('image-'.$type.'-'.$id, $request->request->getString('_token')))
        {
            $this->addFlash('error', 'Your session expired, please try again.');
            return $this->redirect($return_url);
        }

        $error = $this->image_storage->redownload($collectable, $item);
        if ($error === null)
        {
            $this->addFlash('success', sprintf('Downloaded the image of "%s".', $collectable->getTitle($item)));
        }
        else
        {
            $this->addFlash('warning', sprintf('Could not download the image of "%s": %s', $collectable->getTitle($item), $error));
        }
        // From the edit page: back to it, keeping its own way back to the list.
        if ($request->request->getBoolean('_stay'))
        {
            return $this->redirectToRoute('admin_edit', ['type' => $type, 'id' => $id, 'return' => $return_url]);
        }
        return $this->redirect($return_url);
    }

    private function handleForm(Request $request, CollectableType $collectable, array $data, ?object $item): Response
    {
        $form = $this->createForm($collectable->getFormType(), $data, $collectable->getFormOptions());
        $form->handleRequest($request);
        $return_url = $this->getReturnUrl($request, $collectable);
        $duplicate = null;

        if ($form->isSubmitted() && $form->isValid())
        {
            $data = $form->getData();
            $duplicate = $collectable->findDuplicate($data, $item);
            if ($duplicate !== null)
            {
                $form->get($collectable->getDuplicateField())->addError(
                    new FormError(sprintf('This %s already exists.', $collectable->getItemLabel()))
                );
            }
            else
            {
                $is_new = $item === null;
                $old_path = $is_new ? null : $collectable->getLocalImagePath($item);
                $old_url = $is_new ? null : $collectable->getImageUrl($item);
                $old_path_shared = !$is_new && $collectable->findDuplicate($collectable->toFormData($item), $item) !== null;

                $item = $collectable->applyFormData($data, $item);
                $this->entity_manager->persist($item);
                $this->entity_manager->flush();

                $warning = $this->image_storage->sync($collectable, $item, $old_path, $old_url, $old_path_shared);
                $this->addFlash('success', sprintf('%s "%s".', $is_new ? 'Added' : 'Saved', $collectable->getTitle($item)));
                if ($warning !== null)
                {
                    $this->addFlash('warning', $warning);
                }

                if ($request->request->has('add_another'))
                {
                    return $this->redirectToRoute('admin_new', [
                        'type' => $collectable->getKey(),
                        'prefill' => array_intersect_key($data, array_flip($collectable->getAddAnotherFields())),
                        'return' => $return_url,
                    ]);
                }
                return $this->redirect($return_url);
            }
        }

        return $this->renderAdmin('admin/form.html.twig', $collectable, [
            'form' => $form,
            'item' => $item,
            'item_title' => $item !== null ? $collectable->getTitle($item) : null,
            'image' => $item !== null ? $this->image_storage->getDisplayUrl($collectable, $item) : null,
            'duplicate' => $duplicate === null ? null : [
                'id' => $duplicate->getId(),
                'title' => $collectable->getTitle($duplicate),
                'subtitle' => $collectable->getSubtitle($duplicate),
            ],
            'return_url' => $return_url,
        ], $form->isSubmitted() ? new Response(status: 422) : null);
    }

    /**
     * Search, filters and sort from the query string, shared by the list and the export.
     * @return array{qb: QueryBuilder, search: string, sort: string, dir: string, filters: array, active_filters: array<string, string>}
     */
    private function buildListQuery(Request $request, CollectableType $collectable): array
    {
        $columns = $collectable->getColumns();
        $filters = $collectable->getFilters() + [
            'image' => ['label' => 'Image', 'choices' => ['missing' => 'Not on server', 'no_url' => 'No URL']],
        ];

        $search = trim($request->query->getString('q'));
        [$sort, $dir] = $collectable->getDefaultSort();
        if (isset($columns[$request->query->getString('sort')]['sort']))
        {
            $sort = $request->query->getString('sort');
            $dir = $request->query->getString('dir') === 'desc' ? 'desc' : 'asc';
        }

        // Only declared filters with one of their own values are applied.
        $active_filters = [];
        foreach ($request->query->all('f') as $key => $value)
        {
            if (\is_string($value) && isset($filters[$key]['choices'][$value]))
            {
                $active_filters[$key] = $value;
            }
        }

        $qb = $this->entity_manager->getRepository($collectable->getEntityClass())->createQueryBuilder('e');
        if ($search !== '')
        {
            $collectable->applySearch($qb, $search);
        }
        foreach ($active_filters as $key => $value)
        {
            if ($key !== 'image')
            {
                $collectable->applyFilter($qb, $key, $value);
            }
        }
        if (($active_filters['image'] ?? null) === 'no_url')
        {
            $qb->andWhere("e.url IS NULL OR e.url = ''");
        }
        elseif (($active_filters['image'] ?? null) === 'missing')
        {
            // A file check, so it can't be SQL: collect the ids without a local file.
            $ids = [];
            foreach ((clone $qb)->getQuery()->toIterable() as $item)
            {
                if (!$this->image_storage->hasLocalImage($collectable, $item))
                {
                    $ids[] = $item->getId();
                }
            }
            $qb->andWhere($ids === [] ? '1 = 0' : 'e.id IN (:f_ids)');
            if ($ids !== [])
            {
                $qb->setParameter('f_ids', $ids);
            }
        }
        foreach ($columns[$sort]['sort'] as $field)
        {
            $qb->addOrderBy($field, $dir);
        }

        return [
            'qb' => $qb,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'filters' => $filters,
            'active_filters' => $active_filters,
        ];
    }

    /**
     * Call after the item was deleted: removes its image file unless a
     * remaining row still uses it (e.g. a duplicate card).
     */
    private function removeImageIfUnused(CollectableType $collectable, object $item, array $data, string $path): void
    {
        $duplicate = $collectable->findDuplicate($data, null);
        if ($duplicate === null || $collectable->getLocalImagePath($duplicate) !== $path)
        {
            $this->image_storage->remove($collectable, $item);
        }
    }

    private function findOr404(CollectableType $collectable, int $id): object
    {
        return $this->entity_manager->getRepository($collectable->getEntityClass())->find($id)
            ?? throw $this->createNotFoundException(sprintf('No %s with id %d', $collectable->getItemLabel(), $id));
    }

    /**
     * Where to go after save/cancel/delete: back to the list page the user
     * came from (keeping search, sort and page), or the plain list.
     */
    private function getReturnUrl(Request $request, CollectableType $collectable): string
    {
        $list_url = $this->generateUrl('admin_list', ['type' => $collectable->getKey()]);
        $return = $request->request->getString('_return') ?: $request->query->getString('return');
        // Only allow local URLs of this list, never an external redirect.
        if ($return === $list_url || str_starts_with($return, $list_url.'?'))
        {
            return $return;
        }
        return $list_url;
    }

    private function renderAdmin(string $template, CollectableType $current, array $parameters, ?Response $response = null): Response
    {
        return $this->render($template, $parameters + [
            'collectable' => $current,
            'active_key' => $current->getKey(),
        ], $response);
    }
}
