<?php

namespace App\Command;

use App\Service\AdminTaskRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Progress lines for commands that run as admin tasks (see AdminTaskRunner).
 */
trait ReportsProgress
{
    /**
     * @return callable(int $current, int $total, string $label): void writes at
     *         most one line per percent, so long runs keep a small log
     */
    private function progressReporter(OutputInterface $output): callable
    {
        $last = null;
        return function (int $current, int $total, string $label) use ($output, &$last): void {
            $step = $total > 0 ? intdiv($current * 100, $total) : 100;
            $key = $label.'|'.$step;
            if ($key === $last && $current !== $total)
            {
                return;
            }
            $last = $key;
            $output->writeln(AdminTaskRunner::progress($current, $total, $label));
        };
    }
}
