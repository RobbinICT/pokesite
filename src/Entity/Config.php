<?php

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Site settings, editable in the admin under Settings.
 */
#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ORM\Table(name: 'config')]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    private bool $is_use_local_cards = true;

    #[ORM\Column]
    private bool $is_use_local_minifigures = true;

    public function __construct()
    {
        $this->is_use_local_cards = true;
        $this->is_use_local_minifigures = true;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUseLocalCards(): bool
    {
        return $this->is_use_local_cards;
    }

    public function setUseLocalCards(bool $is_use_local_cards): void
    {
        $this->is_use_local_cards = $is_use_local_cards;
    }

    public function getUseLocalMinifigures(): bool
    {
        return $this->is_use_local_minifigures;
    }

    public function setUseLocalMinifigures(bool $is_use_local_minifigures): void
    {
        $this->is_use_local_minifigures = $is_use_local_minifigures;
    }
}
