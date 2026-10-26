<?php

namespace App\Admin\Type;

use App\Admin\CollectableType;
use App\Entity\Config;
use App\Entity\Pokemon;
use App\Form\Admin\PokemonCardFormType;
use App\Service\DatabaseManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class PokemonCardType implements CollectableType
{
    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    public static function getPriority(): int
    {
        return 100;
    }

    public function getKey(): string
    {
        return 'pokemon';
    }

    public function getLabel(): string
    {
        return 'Pokémon cards';
    }

    public function getItemLabel(): string
    {
        return 'card';
    }

    public function getIcon(): string
    {
        return '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="12" r="3.5"/><path d="M8.5 12h7"/>';
    }

    public function getEntityClass(): string
    {
        return Pokemon::class;
    }

    public function getFormType(): string
    {
        return PokemonCardFormType::class;
    }

    public function getFormOptions(): array
    {
        return [
            'series_suggestions' => $this->getDistinctValues('serie'),
            'list_suggestions' => $this->getDistinctValues('list'),
        ];
    }

    public function getColumns(): array
    {
        return [
            'name' => ['label' => 'Name', 'sort' => ['e.name', 'e.id']],
            'serie' => ['label' => 'Serie', 'sort' => ['e.serie', 'e.serie_nr', 'e.id']],
            'serie_nr' => ['label' => 'Nr', 'sort' => ['e.serie_nr', 'e.id'], 'class' => 'tabular-nums'],
            'dex_nr' => ['label' => 'Dex', 'sort' => ['e.dex_nr', 'e.serie_nr', 'e.id'], 'class' => 'tabular-nums'],
            'list' => ['label' => 'List', 'sort' => ['e.list', 'e.dex_nr', 'e.id']],
        ];
    }

    public function getDefaultSort(): array
    {
        return ['dex_nr', 'asc'];
    }

    public function applySearch(QueryBuilder $qb, string $search): void
    {
        $qb->andWhere('LOWER(e.name) LIKE LOWER(:search) OR LOWER(e.serie) LIKE LOWER(:search)')
            ->setParameter('search', '%'.$search.'%');
    }

    public function getFilters(): array
    {
        $serie = $this->getDistinctValues('serie');
        $list = $this->getDistinctValues('list');

        return [
            'serie' => ['label' => 'Serie', 'choices' => array_combine($serie, $serie)],
            'list' => ['label' => 'List', 'choices' => array_combine($list, $list)],
        ];
    }

    public function applyFilter(QueryBuilder $qb, string $key, string $value): void
    {
        match ($key) {
            'serie' => $qb->andWhere('e.serie = :f_serie')->setParameter('f_serie', $value),
            'list' => $qb->andWhere('e.list = :f_list')->setParameter('f_list', $value),
        };
    }

    public function getCsvColumns(): array
    {
        return DatabaseManager::CARDS_CSV_COLUMNS;
    }

    /** @param Pokemon $item */
    public function toCsvRow(object $item): array
    {
        return [$item->getDexNr(), $item->getName(), $item->getGen(), $item->getSerie(), $item->getSerieNr(), $item->getList(), $item->getUrl()];
    }

    /** @param Pokemon $item */
    public function getCells(object $item): array
    {
        return [
            'name' => $item->getName(),
            'serie' => $item->getSerie(),
            'serie_nr' => $item->getSerieNr(),
            'dex_nr' => $item->getDexNr(),
            'list' => $item->getList(),
        ];
    }

    /** @param Pokemon $item */
    public function getTitle(object $item): string
    {
        return $item->getName();
    }

    /** @param Pokemon $item */
    public function getSubtitle(object $item): string
    {
        return $item->getSerie().' #'.$item->getSerieNr();
    }

    public function getBadge(object $item): ?string
    {
        return null;
    }

    /** @param Pokemon|null $item */
    public function toFormData(?object $item): array
    {
        if ($item === null)
        {
            return ['list' => 'F'];
        }

        return [
            'name' => $item->getName(),
            'url' => $item->getUrl(),
            'serie' => $item->getSerie(),
            'serie_nr' => $item->getSerieNr(),
            'dex_nr' => $item->getDexNr(),
            'gen' => $item->getGen(),
            'list' => $item->getList(),
        ];
    }

    /** @param Pokemon|null $item */
    public function applyFormData(array $data, ?object $item): object
    {
        if ($item === null)
        {
            $item = new Pokemon($data['dex_nr'], $data['name'], $data['gen'], $data['serie'], $data['serie_nr'], $data['list']);
        }
        else
        {
            $item->setDexNr($data['dex_nr']);
            $item->setName($data['name']);
            $item->setGen($data['gen']);
            $item->setSerie($data['serie']);
            $item->setSerieNr($data['serie_nr']);
            $item->setList($data['list']);
        }
        $item->setUrl($data['url'] ?: null);

        return $item;
    }

    /** @param Pokemon|null $item */
    public function findDuplicate(array $data, ?object $item): ?object
    {
        $matches = $this->entity_manager->getRepository(Pokemon::class)->findBy([
            'serie' => $data['serie'],
            'serie_nr' => $data['serie_nr'],
        ]);
        foreach ($matches as $match)
        {
            if ($item === null || $match->getId() !== $item->getId())
            {
                return $match;
            }
        }
        return null;
    }

    public function getDuplicateField(): string
    {
        return 'serie_nr';
    }

    public function getAddAnotherFields(): array
    {
        return ['serie', 'gen', 'list'];
    }

    /** @param Pokemon $item */
    public function getLocalImagePath(object $item): string
    {
        // Same naming as ImageManager::downloadImages() and pokemon/_batch.html.twig
        return 'images/pokemon/'.$item->getUniqueIdentifierV2().'.png';
    }

    /** @param Pokemon $item */
    public function getImageUrl(object $item): ?string
    {
        return $item->getUrl();
    }

    public function usesLocalImages(): bool
    {
        return $this->entity_manager->getRepository(Config::class)->getConfig()?->getUseLocalCards() ?? true;
    }

    private function getDistinctValues(string $field): array
    {
        $rows = $this->entity_manager->getRepository(Pokemon::class)->createQueryBuilder('e')
            ->select('DISTINCT e.'.$field.' AS value')
            ->orderBy('value', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'value');
    }
}
