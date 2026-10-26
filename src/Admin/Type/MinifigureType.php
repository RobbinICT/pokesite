<?php

namespace App\Admin\Type;

use App\Admin\CollectableType;
use App\Entity\Config;
use App\Entity\Minifigure;
use App\Form\Admin\MinifigureFormType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class MinifigureType implements CollectableType
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    public static function getPriority(): int
    {
        return 90;
    }

    public function getKey(): string
    {
        return 'lego';
    }

    public function getLabel(): string
    {
        return 'LEGO minifigures';
    }

    public function getItemLabel(): string
    {
        return 'minifigure';
    }

    public function getIcon(): string
    {
        return '<rect x="8" y="2.5" width="8" height="7" rx="2"/><path d="M10.5 2.5v-1h3v1"/><path d="M6 21v-7a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v7"/><path d="M12 14v7"/>';
    }

    public function getEntityClass(): string
    {
        return Minifigure::class;
    }

    public function getFormType(): string
    {
        return MinifigureFormType::class;
    }

    public function getFormOptions(): array
    {
        $rows = $this->entity_manager->getRepository(Minifigure::class)->createQueryBuilder('e')
            ->select('DISTINCT e.series AS value')
            ->orderBy('value', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return ['series_suggestions' => array_column($rows, 'value')];
    }

    public function getColumns(): array
    {
        return [
            'name' => ['label' => 'Name', 'sort' => ['e.name', 'e.id']],
            'series' => ['label' => 'Series', 'sort' => ['e.series', 'e.identifier', 'e.id']],
            'identifier' => ['label' => 'Identifier', 'sort' => ['e.identifier', 'e.id'], 'class' => 'font-mono text-xs'],
        ];
    }

    public function getDefaultSort(): array
    {
        return ['series', 'asc'];
    }

    public function applySearch(QueryBuilder $qb, string $search): void
    {
        $qb->andWhere('LOWER(e.name) LIKE LOWER(:search) OR LOWER(e.identifier) LIKE LOWER(:search) OR LOWER(e.series) LIKE LOWER(:search)')
            ->setParameter('search', '%'.$search.'%');
    }

    /** @param Minifigure $item */
    public function getCells(object $item): array
    {
        return [
            'name' => $item->getName(),
            'series' => $item->getSeries(),
            'identifier' => $item->getIdentifier(),
        ];
    }

    /** @param Minifigure $item */
    public function getTitle(object $item): string
    {
        return $item->getName();
    }

    /** @param Minifigure $item */
    public function getSubtitle(object $item): string
    {
        return $item->getSeries().' · '.$item->getIdentifier();
    }

    /** @param Minifigure $item */
    public function getBadge(object $item): ?string
    {
        return $item->isTemporary() ? 'Temporary ID' : null;
    }

    /** @param Minifigure|null $item */
    public function toFormData(?object $item): array
    {
        if ($item === null)
        {
            return [];
        }

        return [
            'name' => $item->getName(),
            'url' => $item->getUrl(),
            'series' => $item->getSeries(),
            'identifier' => $item->getIdentifier(),
        ];
    }

    /** @param Minifigure|null $item */
    public function applyFormData(array $data, ?object $item): object
    {
        if ($item === null)
        {
            $item = new Minifigure($data['identifier'], $data['series'], $data['name']);
        }
        else
        {
            $item->setIdentifier($data['identifier']);
            $item->setSeries($data['series']);
            $item->setName($data['name']);
        }
        // No URL given: fall back to BrickLink's catalog image for real IDs.
        $item->setUrl($data['url'] ?: $item->getBricklinkImageUrl());

        return $item;
    }

    /** @param Minifigure|null $item */
    public function findDuplicate(array $data, ?object $item): ?object
    {
        // SQLite compares case-sensitively, but "SH0912" and "sh0912" would
        // still map to the same image file on a case-insensitive disk.
        $match = $this->entity_manager->getRepository(Minifigure::class)->createQueryBuilder('e')
            ->where('LOWER(e.identifier) = LOWER(:identifier)')
            ->setParameter('identifier', $data['identifier'])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($match !== null && ($item === null || $match->getId() !== $item->getId()))
        {
            return $match;
        }
        return null;
    }

    public function getDuplicateField(): string
    {
        return 'identifier';
    }

    public function getAddAnotherFields(): array
    {
        return ['series'];
    }

    /** @param Minifigure $item */
    public function getLocalImagePath(object $item): string
    {
        // Same naming as lego/_batch.html.twig
        return 'images/lego/'.$item->getIdentifier().'.png';
    }

    /** @param Minifigure $item */
    public function getImageUrl(object $item): ?string
    {
        return $item->getUrl();
    }

    public function usesLocalImages(): bool
    {
        return $this->entity_manager->getRepository(Config::class)->getConfig()?->getUseLocalMinifigures() ?? true;
    }
}
