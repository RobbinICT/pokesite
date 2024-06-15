<?php

namespace App\Entity;

use App\Repository\PokemonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonRepository::class)]
#[ORM\Table(name: 'pokedex')]
class Pokedex
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public int $dex_nr;

    #[ORM\Column]
    public string $name;

    public function __construct(int $dex_nr, string $name)
    {
        $this->dex_nr = $dex_nr;
        $this->name = $name;
    }
}