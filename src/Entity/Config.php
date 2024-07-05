<?php

namespace App\Entity;

use App\Repository\ConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigRepository::class)]
#[ORM\Table(name: 'config')]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    private bool $is_super_actions_enabled = false;

    #[ORM\Column]
    private bool $is_paradox_rift_exclude = false;

    #[ORM\Column]
    private bool $is_use_local_cards = true;

    #[ORM\Column]
    private bool $is_in_alphabetical_order = false;

    public function __construct()
    {
        $this->is_super_actions_enabled = false;
        $this->is_paradox_rift_exclude = false;
        $this->is_use_local_cards = true;
        $this->is_in_alphabetical_order = false;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIsSuperActionsEnabled(): bool
    {
        return $this->is_super_actions_enabled;
    }

    public function setIsSuperActionsEnabled(bool $is_super_actions_enabled): void
    {
        $this->is_super_actions_enabled = $is_super_actions_enabled;
    }

    public function getIsParadoxRiftExclude(): bool
    {
        return $this->is_paradox_rift_exclude;
    }

    public function setIsParadoxRiftExclude(bool $is_paradox_rift_exclude): void
    {
        $this->is_paradox_rift_exclude = $is_paradox_rift_exclude;
    }

    public function getUseLocalCards(): bool
    {
        return $this->is_use_local_cards;
    }

    public function setUseLocalCards(bool $is_use_local_cards): void
    {
        $this->is_use_local_cards = $is_use_local_cards;
    }

    public function getIsInAlphabeticalOrder(): bool
    {
        return $this->is_in_alphabetical_order;
    }

    public function setIsInAlphabeticalOrder(bool $is_in_alphabetical_order): void
    {
        $this->is_in_alphabetical_order = $is_in_alphabetical_order;
    }
}