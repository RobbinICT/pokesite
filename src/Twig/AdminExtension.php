<?php

namespace App\Twig;

use App\Admin\CollectableRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Sidebar data for the admin layout, so every admin page gets the same
 * navigation without each controller having to pass it.
 */
class AdminExtension extends AbstractExtension
{
    private EntityManagerInterface $entity_manager;
    private CollectableRegistry $registry;

    public function __construct(EntityManagerInterface $entity_manager, CollectableRegistry $registry)
    {
        $this->entity_manager = $entity_manager;
        $this->registry = $registry;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_collectables', [$this, 'getCollectables']),
        ];
    }

    /**
     * @return list<array{type: \App\Admin\CollectableType, count: int}>
     */
    public function getCollectables(): array
    {
        $collectables = [];
        foreach ($this->registry->all() as $type)
        {
            $collectables[] = [
                'type' => $type,
                'count' => $this->entity_manager->getRepository($type->getEntityClass())->count([]),
            ];
        }

        return $collectables;
    }
}
