<?php

namespace App\Service;

use App\Admin\CollectableType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Counts for the Tools health summary: how many items a collectable has and
 * how many still need an image.
 */
class CollectableHealth
{
    private EntityManagerInterface $entity_manager;
    private CollectableImageStorage $image_storage;

    public function __construct(EntityManagerInterface $entity_manager, CollectableImageStorage $image_storage)
    {
        $this->entity_manager = $entity_manager;
        $this->image_storage = $image_storage;
    }

    /**
     * @return array{total: int, missing_images: int, no_url: int, checks: list<array{label: string, count: int, filter: array<string, string>}>}
     *         missing_images: no local file; no_url: no image URL at all
     */
    public function get(CollectableType $type): array
    {
        $total = 0;
        $missing_images = 0;
        $no_url = 0;
        // A file check per item, so this can't be one query; cheap for a few thousand items.
        $query = $this->entity_manager->getRepository($type->getEntityClass())->createQueryBuilder('e')->getQuery();
        foreach ($query->toIterable() as $item)
        {
            $total++;
            if (!$type->getImageUrl($item))
            {
                $no_url++;
            }
            if (!$this->image_storage->hasLocalImage($type, $item))
            {
                $missing_images++;
            }
        }

        return [
            'total' => $total,
            'missing_images' => $missing_images,
            'no_url' => $no_url,
            'checks' => $type->getHealthChecks(),
        ];
    }
}
