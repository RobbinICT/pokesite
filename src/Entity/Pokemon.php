<?php

namespace App\Entity;

use App\Repository\PokemonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonRepository::class)]
#[ORM\Table(name: 'pokemon')]
class Pokemon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    #[ORM\Column]
    public int $dex_nr;

    #[ORM\Column]
    public string $name;

    #[ORM\Column]
    public int $gen;

    #[ORM\Column]
    public string $serie;

    #[ORM\Column]
    public int $serie_nr;

    #[ORM\Column]
    public string $list;

    #[ORM\Column]
    public ?string $url;

    public function __construct(int $dex_nr, string $name, int $gen, string $serie, int $serie_nr, string $list)
    {
        $this->dex_nr = $dex_nr;
        $this->name = $name;
        $this->gen = $gen;
        $this->serie = $serie;
        $this->serie_nr = $serie_nr;
        $this->list = $list;
    }

    public function getDexNr(): int
    {
        return $this->dex_nr;
    }

    public function setDexNr(int $dex_nr): void
    {
        $this->dex_nr = $dex_nr;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCleanName(): string
    {
        $pattern = '/[^a-zA-Z\s]/';
        return preg_replace($pattern, ' ', $this->getName());
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getGen(): int
    {
        return $this->gen;
    }

    public function setGen(int $gen): void
    {
        $this->gen = $gen;
    }

    public function getSerie(): string
    {
        return $this->serie;
    }

    public function setSerie(string $serie): void
    {
        $this->serie = $serie;
    }

    public function getSerieNrGallery(): string
    {
        if(str_contains($this->serie, 'Gallery'))
        {
            if (str_contains($this->serie, 'Crown Zenith'))
            {
                return 'GG'.$this->serie_nr;
            }
            elseif (str_contains($this->serie, 'Silver Tempest') || str_contains($this->serie, 'Lost Origin') || str_contains($this->serie, 'Astral Radiance'))
            {
                return 'TG'.$this->serie_nr;
            }
        }
        return $this->serie_nr;
    }

    public function getSerieNr(): int
    {
        return $this->serie_nr;
    }

    public function setSerieNr(int $serie_nr): void
    {
        $this->serie_nr = $serie_nr;
    }

    public function getList(): string
    {
        return $this->list;
    }

    public function setList(string $list): void
    {
        $this->list = $list;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }


    public function generatePokellectorUrl(): ?string
    {
        if (!str_contains($this->list, 'Jumbo') && !str_contains($this->serie, 'Trick or Trade 2023'))
        {
            $base_url = "https://www.pokellector.com/";
            return $base_url . $this->hyphenate($this->serie) . "-Expansion" . "/" . "Card-" . $this->getSerieNrGallery();
        }
        return null;
    }

    private function hyphenate(string $string): string
    {
        // Define a regular expression pattern to match special characters and spaces
        $pattern = '/[^a-zA-Z0-9\s]/';

        // Use preg_replace to remove special characters from the string and convert spaces to hyphens
        $clean_string = preg_replace($pattern, '', $string);
        $space_string = str_replace('  ', ' ', $clean_string);
        $replaced_string = str_replace(' ', '-', $space_string);

        return $this->convertSerieToPokellectorSerieUrl($string, $replaced_string);
    }

    private function convertSerieToPokellectorSerieUrl(string $serie, string $replace_string)
    {
        switch ($serie)
        {
            case 'Pokemon Go':
            case 'Sword & Shield':
            case 'Sword & Shield - Promos':
            case 'XY - Promos':
            case 'Sun & Moon':
            case 'Forbidden Light':
                return 'English-'.$replace_string;
            case 'Scarlet & Violet':
                return $replace_string.'-English';
            case 'Scarlet & Violet - Promos':
                return 'Scarlet-Violet-English-Promos';
            case 'Arceus':
            case 'Rising Rivals':
            case 'Supreme Victors':
                return 'Platinum-'.$replace_string;
            case 'Unleashed':
                return 'HS-'.$replace_string;
            default:
                return $replace_string;
        }
    }
}