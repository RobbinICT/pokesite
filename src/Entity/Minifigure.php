<?php

namespace App\Entity;

use App\Repository\MinifigureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MinifigureRepository::class)]
#[ORM\Table(name: 'minifigure')]
class Minifigure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column(unique: true)]
    public string $identifier;

    #[ORM\Column]
    public string $series;

    #[ORM\Column]
    public string $name;

    #[ORM\Column(nullable: true)]
    public ?string $url = null;

    public function __construct(string $identifier, string $series, string $name)
    {
        $this->identifier = $identifier;
        $this->series = $series;
        $this->name = $name;
    }

    public function getBricklinkUrl(): string
    {
        return 'https://www.bricklink.com/v2/catalog/catalogitem.page?M='.$this->identifier;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): void
    {
        $this->series = $series;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
