<?php

namespace App\Entity;

use App\Repository\MissingPokemonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MissingPokemonRepository::class)]
#[ORM\Table(name: 'missing_pokemon')]
class MissingPokemon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public string $title;

    #[ORM\Column]
    public string $serie;

    #[ORM\Column]
    public string $serie_nr;

    #[ORM\Column]
    public string $url;

    public function __construct(string $title, string $serie, string $serie_nr, string $url)
    {
        $this->title = $title;
        $this->serie = $serie;
        $this->serie_nr = $serie_nr;
        $this->url = $url;
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

    public function getSerie(): string
    {
        return $this->serie;
    }

    public function setSerie(string $serie): void
    {
        $this->serie = $serie;
    }

    public function getSerieNr(): string
    {
        return $this->serie_nr;
    }

    public function setSerieNr(string $serie_nr): void
    {
        $this->serie_nr = $serie_nr;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }
}