<?php

namespace App\Service;

class ValidateManager
{
    public function validateUrl(array $to_validate): array
    {
        $wrong = [];
        foreach ($to_validate as $pokemon) {
            if ($pokemon->getUrl() !== 'TODO' && $pokemon->getUrl() !== '')
            {
                if (!str_contains($pokemon->getUrl(), strtok($pokemon->getCleanName(), ' ')))
                {
                    $wrong[$pokemon->getUrl()] = $pokemon->getCleanName();
                }
            }
        }
        return $this->unsetKnownWrongUrls($wrong);
    }

    public function validateGeneration(array $to_validate): array
    {
        $wrong = [];
        foreach ($to_validate as $pokemon)
        {
            $gen = $pokemon->getGen();
            $valid_gen = $this->getGenByDexNumber($pokemon->getDexNr());
            if ($valid_gen != $gen)
            {
                $key = "({$pokemon->getSerie()} - {$pokemon->getSerieNr()}): {$pokemon->getName()} [{$pokemon->getGen()}]";
                $wrong[] = $key;
            }
        }

        return $wrong;
    }

    public function validateNationalDex()
    {

    }

    private function unsetKnownWrongUrls(array $wrong): array
    {
        unset($wrong["https://den-cards.pokellector.com/374/Machoke.MEW.127.49268.png"]); // Pinsir
        unset($wrong["https://den-cards.pokellector.com/45/Luvdisk.GE.77.png"]); // Luvdisc
        unset($wrong["https://den-cards.pokellector.com/45/Drifloon.GE.61.png"]); // Buizel
        unset($wrong["https://den-cards.pokellector.com/363/Tatsugiri.SV1EN.60.46942.png"]); // Cetitan
        return $wrong;
    }

    private function getGenByDexNumber(int $national_dex_number)
    {
        switch ($national_dex_number)
        {
            case ($national_dex_number >= 1 && $national_dex_number <= 151):
                return 1;
            case ($national_dex_number >= 152 && $national_dex_number <= 251):
                return 2;
            case ($national_dex_number >= 252 && $national_dex_number <= 386):
                return 3;
            case ($national_dex_number >= 387 && $national_dex_number <= 493):
                return 4;
            case ($national_dex_number >= 494 && $national_dex_number <= 649):
                return 5;
            case ($national_dex_number >= 650 && $national_dex_number <= 721):
                return 6;
            case ($national_dex_number >= 722 && $national_dex_number <= 809):
                return 7;
            case ($national_dex_number >= 810 && $national_dex_number <= 905):
                return 8;
            case ($national_dex_number >= 906 && $national_dex_number <= 1025):
                return 9;
            default:
                return 0;
        }
    }
}