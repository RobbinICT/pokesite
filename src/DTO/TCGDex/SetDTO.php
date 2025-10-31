<?php

namespace App\DTO\TCGDex;

use Symfony\Component\Serializer\Attribute\SerializedName;

class SetDTO
{
    public string $id;
    public string $name;
    public ?string $logo = null;
    public ?string $symbol = null;
    #[SerializedName('card_count')]
    public SetCardCountDTO $card_count_dto;
}