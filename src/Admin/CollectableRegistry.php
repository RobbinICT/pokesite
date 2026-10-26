<?php

namespace App\Admin;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CollectableRegistry
{
    /** @var array<string, CollectableType> */
    private array $types = [];

    /**
     * @param iterable<CollectableType> $types
     */
    public function __construct(#[AutowireIterator('app.collectable_type', defaultPriorityMethod: 'getPriority')] iterable $types)
    {
        foreach ($types as $type)
        {
            $this->types[$type->getKey()] = $type;
        }
    }

    /** @return array<string, CollectableType> */
    public function all(): array
    {
        return $this->types;
    }

    public function first(): CollectableType
    {
        return reset($this->types);
    }

    public function get(string $key): CollectableType
    {
        return $this->types[$key] ?? throw new NotFoundHttpException(sprintf('Unknown collectable "%s"', $key));
    }
}
