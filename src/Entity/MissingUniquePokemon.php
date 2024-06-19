<?php

namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: 'missing_unique_pokemon')]
class MissingUniquePokemon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public string $title;

    #[ORM\Column]
    public string $url;

    #[ORM\Column]
    public string $tcg_dex_url;

    public function __construct(int $id, string $title)
    {
        $this->id = $id;
        $this->title = $title;
        $this->setUrl($id);
        $this->setTcgdexUrl($id);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(int $id): void
    {
        $this->url = "https://www.pokemon.com/static-assets/content-assets/cms2/img/pokedex/full/$id.png";
    }

    public function getTcgDexUrl(): string
    {
        return $this->tcg_dex_url;
    }

    public function setTcgDexUrl(int $id): void
    {
        $this->tcg_dex_url = "https://www.serebii.net/card/dex/$id.shtml";
    }
}