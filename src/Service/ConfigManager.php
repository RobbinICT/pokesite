<?php

namespace App\Service;

class ConfigManager
{
    public const ENV_VAR_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ENV_VAR_USE_LOCAL_CARDS = 'USE_LOCAL_CARDS';
    public const ENV_VAR_EXCLUDE_PARADOX_RIFT = 'EXCLUDE_PARADOX_RIFT';

    public static function getSuperAdminEnvironmentVariable()
    {
        return filter_var($_ENV['SUPER_ADMIN'], FILTER_VALIDATE_BOOLEAN) ?? false;
    }

    public static function getUseLocalCardsEnvironmentVariable()
    {
        return filter_var($_ENV['USE_LOCAL_CARDS'], FILTER_VALIDATE_BOOLEAN) ?? true;
    }

    public static function getExcludeParadoxRiftEnvironmentVariable()
    {
        return filter_var($_ENV['EXCLUDE_PARADOX_RIFT'], FILTER_VALIDATE_BOOLEAN) ?? false;
    }

    public static function getAlphabeticalOrderForUniqueMissingPokemonEnvironmentVariable()
    {
        return filter_var($_ENV['ALPHABETICAL_ORDER'], FILTER_VALIDATE_BOOLEAN) ?? false;
    }
}