<?php

namespace App\Entity\TCGDex;

use App\DTO\TCGDex\SetDTO;
use App\Repository\TCGDex\SetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SetRepository::class)]
#[ORM\Table(name: 'tcg_dex_set')]
class Set
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $identifier;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logo;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $symbol;

    #[ORM\Column(type: 'integer')]
    private int $total;

    #[ORM\Column(type: 'integer')]
    private int $official;

    public function __construct(string $identifier, string $name, ?string $logo, ?string $symbol, int $total, int $official)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->logo = $logo;
        $this->symbol = $symbol;
        $this->total = $total;
        $this->official = $official;
    }

    public static function constructFromDTO(SetDTO $dto): Set
    {
        $card_count_dto = $dto->card_count_dto;
        return new self(
            $dto->id,
            $dto->name,
            $dto->logo,
            $dto->symbol,
            $card_count_dto->total,
            $card_count_dto->official,
        );
    }

    public function updateFromDTO(SetDTO $set_dto): Set
    {
        $this->identifier = $set_dto->id;
        $this->name = $set_dto->name;
        $this->logo = $set_dto->logo;
        $this->symbol = $set_dto->symbol;

        $card_count_dto = $set_dto->card_count_dto;
        $this->total = $card_count_dto->total;
        $this->official = $card_count_dto->official;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getOfficial(): int
    {
        return $this->official;
    }
}