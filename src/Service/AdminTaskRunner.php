<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs slow console commands (scraping external sites) in the background so
 * the admin can start them without hitting request timeouts.
 *
 * Each task writes to var/log/tasks/<task>.log: a start marker, the command
 * output, and an exit marker once it finishes. The log is the only state.
 */
class AdminTaskRunner
{
    private const START_MARKER = '__START:';
    private const EXIT_MARKER = '__EXIT:';
    /** A task without an exit marker after this long is considered dead (e.g. container restart). */
    private const MAX_RUNTIME = 3600;

    private string $project_dir;

    public function __construct(KernelInterface $kernel)
    {
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * Whitelisted tasks: only these commands can ever be started.
     * @return array<string, array{label: string, description: string, command: string[]}>
     */
    public static function getTasks(): array
    {
        return [
            'missing_pokemon' => [
                'label' => 'Update missing Pokémon',
                'description' => 'Looks up new missing cards on Pokéllector and removes the ones you now own.',
                'command' => ['update:missing:pokemon'],
            ],
            'minifigure_images' => [
                'label' => 'Fill in minifigure images',
                'description' => 'Sets BrickLink\'s catalog image for minifigures without an image URL. Temporary IDs are skipped.',
                'command' => ['update:database:minifigures', 'fill-images'],
            ],
            'minifigure_downloads' => [
                'label' => 'Download minifigure images',
                'description' => 'Downloads the images of minifigures that are not on the server yet. Runs automatically after a minifigures import.',
                'command' => ['update:database:minifigures', 'download-images'],
            ],
        ];
    }

    /**
     * @return bool false when the task is already running
     */
    public function start(string $task): bool
    {
        $definition = self::getTasks()[$task] ?? throw new \InvalidArgumentException(sprintf('Unknown task "%s"', $task));
        $log = $this->getLogPath($task);
        if (!is_dir(\dirname($log)))
        {
            mkdir(\dirname($log), 0775, true);
        }

        // Lock so two quick clicks cannot start the task twice.
        $lock = fopen($log.'.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB))
        {
            return false;
        }
        try
        {
            if ($this->getStatus($task)['state'] === 'running')
            {
                return false;
            }
            file_put_contents($log, self::START_MARKER.time()."\n");

            $php = (new PhpExecutableFinder())->find(false) ?: 'php';
            $command = implode(' ', array_map('escapeshellarg', [$php, $this->project_dir.'/bin/console', ...$definition['command'], '--no-interaction']));
            $script = sprintf('%s >> %s 2>&1; echo "%s$?" >> %s', $command, escapeshellarg($log), self::EXIT_MARKER, escapeshellarg($log));
            // nohup + & detaches the command so it outlives this request.
            Process::fromShellCommandline(sprintf('nohup sh -c %s > /dev/null 2>&1 &', escapeshellarg($script)), $this->project_dir)->run();
        }
        finally
        {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return true;
    }

    /**
     * @return array{state: 'idle'|'running'|'success'|'failed', started_at: ?int, finished_at: ?int, output: string}
     */
    public function getStatus(string $task): array
    {
        $log = $this->getLogPath($task);
        if (!is_file($log))
        {
            return ['state' => 'idle', 'started_at' => null, 'finished_at' => null, 'output' => ''];
        }

        $started_at = null;
        $exit_code = null;
        $output = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES) as $line)
        {
            if (str_starts_with($line, self::START_MARKER))
            {
                $started_at = (int) substr($line, \strlen(self::START_MARKER));
            }
            elseif (str_starts_with($line, self::EXIT_MARKER))
            {
                $exit_code = (int) substr($line, \strlen(self::EXIT_MARKER));
            }
            elseif (trim($line) !== '')
            {
                $output[] = rtrim($line);
            }
        }

        $finished_at = $exit_code !== null ? filemtime($log) : null;
        if ($exit_code === null)
        {
            $state = time() - filemtime($log) > self::MAX_RUNTIME ? 'failed' : 'running';
        }
        else
        {
            $state = $exit_code === 0 ? 'success' : 'failed';
        }

        return [
            'state' => $state,
            'started_at' => $started_at,
            'finished_at' => $finished_at,
            'output' => implode("\n", \array_slice($output, -20)),
        ];
    }

    private function getLogPath(string $task): string
    {
        return $this->project_dir.'/var/log/tasks/'.$task.'.log';
    }
}
