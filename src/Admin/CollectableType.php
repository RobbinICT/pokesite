<?php

namespace App\Admin;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Describes one kind of collectable for the admin CRUD (Pokémon cards, LEGO
 * minifigures, ...). Adding a new collectable to the admin means adding one
 * implementation of this interface plus a form type; the controller, sidebar
 * and templates are generic.
 *
 * Forms work on plain arrays (toFormData / applyFormData) instead of binding
 * directly to the entity, because the entities have non-nullable typed
 * properties and constructor arguments.
 */
#[AutoconfigureTag('app.collectable_type')]
interface CollectableType
{
    /** Sidebar order: higher comes first */
    public static function getPriority(): int;

    /** URL segment, e.g. "pokemon" -> /admin/pokemon */
    public function getKey(): string;

    /** Plural label for the sidebar and page title, e.g. "Pokémon cards" */
    public function getLabel(): string;

    /** Singular label for buttons and messages, e.g. "card" */
    public function getItemLabel(): string;

    /** Inner markup of a 24x24 outline SVG icon */
    public function getIcon(): string;

    public function getEntityClass(): string;

    public function getFormType(): string;

    public function getFormOptions(): array;

    /**
     * Table columns, keyed by column name.
     * @return array<string, array{label: string, sort: string[]|null, class?: string}>
     */
    public function getColumns(): array;

    /** @return array{0: string, 1: string} column key and direction */
    public function getDefaultSort(): array;

    /** Narrows the list query (alias "e") to items matching the search term */
    public function applySearch(QueryBuilder $qb, string $search): void;

    /**
     * List filters (dropdowns), keyed by filter name. An "image" filter is
     * added by the controller for every type.
     * @return array<string, array{label: string, choices: array<string, string>}> choices map value => label
     */
    public function getFilters(): array;

    /** Narrows the list query (alias "e") for a validated filter value */
    public function applyFilter(QueryBuilder $qb, string $key, string $value): void;

    /** CSV export header, the same columns the Tools import reads */
    public function getCsvColumns(): array;

    /** @return array<int, string|int|null> values in getCsvColumns() order */
    public function toCsvRow(object $item): array;

    /** @return array<string, string|int|null> cell values keyed like getColumns() */
    public function getCells(object $item): array;

    public function getTitle(object $item): string;

    public function getSubtitle(object $item): string;

    /** Optional short label shown as a badge in the list, e.g. "Temporary" */
    public function getBadge(object $item): ?string;

    public function toFormData(?object $item): array;

    /** Creates a new entity ($item = null) or updates the given one from form data */
    public function applyFormData(array $data, ?object $item): object;

    /** Returns another item with the same unique identifier, if any */
    public function findDuplicate(array $data, ?object $item): ?object;

    /** Form field that gets the "already exists" error */
    public function getDuplicateField(): string;

    /** Form fields carried over to the next form on "Save & add another" */
    public function getAddAnotherFields(): array;

    /** Local image path relative to public/, e.g. "images/lego/sh0912.png" */
    public function getLocalImagePath(object $item): string;

    public function getImageUrl(object $item): ?string;

    /** Whether the public site shows the local image instead of the URL */
    public function usesLocalImages(): bool;
}
