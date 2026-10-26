<?php

namespace App\Entity;

use App\Repository\MinifigureRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

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

    public const TEMPORARY_PREFIX = 'temp-';

    /**
     * Figures that are not on BrickLink yet get a placeholder identifier
     * like "temp-venom" until the real one is known.
     */
    public function isTemporary(): bool
    {
        return str_starts_with($this->identifier, self::TEMPORARY_PREFIX);
    }

    public function getBricklinkUrl(): ?string
    {
        if ($this->isTemporary())
        {
            return null;
        }
        return 'https://www.bricklink.com/v2/catalog/catalogitem.page?M='.$this->identifier;
    }

    /** "Lloyd - Legacy" → "lloyd-legacy", for temporary identifiers (same as the admin form's JS). */
    public static function slugify(string $name): string
    {
        return (new AsciiSlugger())->slug($name)->lower()->toString();
    }

    /** BrickLink's catalog image for this figure (null for temporary IDs). */
    public function getBricklinkImageUrl(): ?string
    {
        if ($this->isTemporary())
        {
            return null;
        }
        return 'https://img.bricklink.com/ItemImage/MN/0/'.$this->identifier.'.png';
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
