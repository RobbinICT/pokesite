<?php

namespace App\Service;

use App\Entity\Pokemon;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PokemonManager
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entity_manager;

    public const SERIE_SV_SCARLET_VIOLET = 'Scarlet & Violet';
    public const SERIE_SV_PALDEA_EVOLVED = 'Paldea Evolved';
    public const SERIE_SV_OBSIDIAN_FLAMES = 'Obsidian Flames';
    public const SERIE_SV_PARADOX_RIFT = 'Paradox Rift';
    public const SERIE_SV_TEMPORAL_FORCES = 'Temporal Forces';
    public const SERIE_SV_TWILIGHT_MASQUERADE = 'Twilight Masquerade';
    public const SERIE_SV_SHROUDED_FABLE = 'Shrouded Fable';
    public const SERIE_SV_STELLAR_CROWN = 'Stellar Crown';

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entity_manger)
    {
        $this->logger = $logger;
        $this->entity_manager = $entity_manger;
    }

    public static function getScarletVioletMainSeries(): array
    {
        return [
            PokemonManager::SERIE_SV_SCARLET_VIOLET,
            PokemonManager::SERIE_SV_PALDEA_EVOLVED,
            PokemonManager::SERIE_SV_OBSIDIAN_FLAMES,
            PokemonManager::SERIE_SV_PARADOX_RIFT,
            PokemonManager::SERIE_SV_TEMPORAL_FORCES,
            PokemonManager::SERIE_SV_TWILIGHT_MASQUERADE,
            PokemonManager::SERIE_SV_SHROUDED_FABLE,
            PokemonManager::SERIE_SV_STELLAR_CROWN,
        ];
    }

    public function checkForMissingPokemonInSerie(string $serie_name): array
    {
        $my_numbers = array_column($this->entity_manager->getRepository(Pokemon::class)->getPokemonSerieNumbersBySerie($serie_name), 'serie_nr');
        $limit = self::getLimitOfSerie($serie_name);
        $all_numbers = range(1, $limit);
        $missing_numbers = array_diff($all_numbers, $my_numbers);
        return array_splice($missing_numbers, 0);
    }

    public static function getLimitOfSerie(string $serie_name)
    {
        switch ($serie_name)
        {
            case PokemonManager::SERIE_SV_SCARLET_VIOLET:
                return 165;
            case PokemonManager::SERIE_SV_PALDEA_EVOLVED:
                return 170;
            case PokemonManager::SERIE_SV_OBSIDIAN_FLAMES:
                return 185;
            case PokemonManager::SERIE_SV_PARADOX_RIFT:
                return 158;
            case PokemonManager::SERIE_SV_TEMPORAL_FORCES:
                return 139;
            case PokemonManager::SERIE_SV_TWILIGHT_MASQUERADE:
                return 140;
            case PokemonManager::SERIE_SV_SHROUDED_FABLE:
                return 53;
            case PokemonManager::SERIE_SV_STELLAR_CROWN:
                return 128;
        }
    }
}