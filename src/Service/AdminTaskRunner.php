<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs slow console commands (scraping external sites, downloading images) in
 * the background so the admin can start them without hitting request timeouts.
 *
 * Each run writes its own log in var/log/tasks/<task>/: a start marker, the
 * command output, progress markers and an exit marker once it finishes. The
 * newest HISTORY_SIZE logs are kept; they are the only state.
 *
 * Commands report progress by printing PROGRESS_MARKER lines, see progress().
 */
class AdminTaskRunner
{
    private const START_MARKER = '__START:';
    private const EXIT_MARKER = '__EXIT:';
    private const PID_MARKER = '__PID:';
    private const CANCEL_MARKER = '__CANCELLED:';
    public const PROGRESS_MARKER = '__PROGRESS:';
    /** A task without an exit marker after this long is considered dead (e.g. container restart). */
    private const MAX_RUNTIME = 3600;
    private const HISTORY_SIZE = 5;
    /** Output lines kept for the log view */
    private const OUTPUT_LINES = 200;

    private const ICON_REFRESH = '<path d="M20 11a8 8 0 0 0-14.9-4M4 4v4h4M4 13a8 8 0 0 0 14.9 4M20 20v-4h-4"/>';
    private const ICON_DOWNLOAD = '<path d="M12 3v12M7 10l5 5 5-5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/>';
    private const ICON_LINK = '<path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/>';

    private string $project_dir;

    public function __construct(KernelInterface $kernel)
    {
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * Whitelisted tasks: only these commands can ever be started.
     * collectable: CollectableType key of the Tools section the task is shown in.
     * fixes: health issues ("missing_images", "no_url") the task resolves.
     * @return array<string, array{label: string, description: string, collectable: string, icon: string, fixes: string[], command: string[]}>
     */
    public static function getTasks(): array
    {
        return [
            'missing_pokemon' => [
                'label' => 'Update missing Pokémon',
                'description' => 'Looks up new missing cards on Pokéllector and removes the ones you now own.',
                'collectable' => 'pokemon',
                'icon' => self::ICON_REFRESH,
                'fixes' => [],
                'command' => ['update:missing:pokemon'],
            ],
            'card_images' => [
                'label' => 'Download card images',
                'description' => 'Finds image URLs on Pokéllector for cards without one, then downloads the images that are not on the server yet. Runs after every cards import.',
                'collectable' => 'pokemon',
                'icon' => self::ICON_DOWNLOAD,
                'fixes' => ['missing_images', 'no_url'],
                'command' => ['images:add:urls-download'],
            ],
            'minifigure_images' => [
                'label' => 'Fill in image URLs',
                'description' => 'Sets BrickLink\'s catalog image for minifigures without an image URL. Temporary IDs are skipped.',
                'collectable' => 'lego',
                'icon' => self::ICON_LINK,
                'fixes' => ['no_url'],
                'command' => ['update:database:minifigures', 'fill-images'],
            ],
            'minifigure_downloads' => [
                'label' => 'Download minifigure images',
                'description' => 'Downloads the images that are not on the server yet. Runs after every minifigures import.',
                'collectable' => 'lego',
                'icon' => self::ICON_DOWNLOAD,
                'fixes' => ['missing_images'],
                'command' => ['update:database:minifigures', 'download-images'],
            ],
        ];
    }

    /**
     * Progress line for a command's output, e.g. "__PROGRESS:34/94 Downloading images".
     * Harmless when the command runs in a terminal; the Tools page turns it into a progress bar.
     */
    public static function progress(int $current, int $total, string $label): string
    {
        return sprintf('%s%d/%d %s', self::PROGRESS_MARKER, $current, $total, $label);
    }

    /**
     * @return bool false when the task is already running
     */
    public function start(string $task): bool
    {
        $definition = self::getTasks()[$task] ?? throw new \InvalidArgumentException(sprintf('Unknown task "%s"', $task));
        $dir = $this->getLogDir($task);
        if (!is_dir($dir))
        {
            mkdir($dir, 0775, true);
        }

        // Lock so two quick clicks cannot start the task twice.
        $lock = fopen($dir.'/.lock', 'c');
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
            $started_at = time();
            $log = sprintf('%s/%d-%s.log', $dir, $started_at, bin2hex(random_bytes(3)));
            file_put_contents($log, self::START_MARKER.$started_at."\n");
            foreach (\array_slice($this->getLogs($task), self::HISTORY_SIZE) as $old)
            {
                @unlink($old);
            }

            $php = (new PhpExecutableFinder())->find(false) ?: 'php';
            $command = implode(' ', array_map('escapeshellarg', [$php, $this->project_dir.'/bin/console', ...$definition['command'], '--no-interaction']));
            // The command runs as a background job of the shell, so its PID can be logged for cancel().
            $script = sprintf(
                '%1$s >> %2$s 2>&1 & echo "%3$s$!" >> %2$s; wait $!; echo "%4$s$?" >> %2$s',
                $command, escapeshellarg($log), self::PID_MARKER, self::EXIT_MARKER
            );
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
     * Stops a running task. A run whose process is already gone (e.g. after a
     * container restart) is only marked as cancelled, so it stops showing as running.
     *
     * @return bool false when the task was not running
     */
    public function cancel(string $task): bool
    {
        $definition = self::getTasks()[$task] ?? throw new \InvalidArgumentException(sprintf('Unknown task "%s"', $task));
        $log = $this->getLogs($task)[0] ?? null;
        if ($log === null || $this->parseLog($log)['state'] !== 'running')
        {
            return false;
        }

        file_put_contents($log, self::CANCEL_MARKER.time()."\n", FILE_APPEND);
        $pid = $this->findPid($log, $definition['command']);
        if ($pid !== null)
        {
            posix_kill($pid, 15); // SIGTERM
            for ($i = 0; $i < 20 && $this->isConsoleProcess($pid); $i++)
            {
                usleep(100_000);
            }
            if ($this->isConsoleProcess($pid))
            {
                posix_kill($pid, 9); // SIGKILL
            }
        }

        return true;
    }

    /**
     * PID of the run's console command: from the log, or for runs started
     * before PIDs were logged, by looking for its command line in /proc.
     * @param string[] $command
     */
    private function findPid(string $log, array $command): ?int
    {
        foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $line)
        {
            if (str_starts_with($line, self::PID_MARKER))
            {
                $pid = (int) substr($line, \strlen(self::PID_MARKER));
                return $this->isConsoleProcess($pid) ? $pid : null;
            }
        }

        $needle = implode("\0", ['bin/console', ...$command])."\0";
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file)
        {
            $cmdline = @file_get_contents($file);
            if ($cmdline && str_contains($cmdline, $needle))
            {
                return (int) basename(\dirname($file));
            }
        }
        return null;
    }

    /** Whether the PID is (still) a live bin/console process, so a reused PID is never killed. */
    private function isConsoleProcess(int $pid): bool
    {
        $cmdline = $pid > 0 ? @file_get_contents('/proc/'.$pid.'/cmdline') : false;
        return \is_string($cmdline) && str_contains($cmdline, 'bin/console');
    }

    /**
     * The latest run plus a short history (newest first, the latest run included).
     *
     * @return array{
     *     state: 'idle'|'running'|'success'|'failed'|'cancelled',
     *     started_at: ?int, finished_at: ?int, duration: ?int,
     *     progress: ?array{current: int, total: int, label: string},
     *     output: string,
     *     history: list<array{state: string, started_at: ?int, finished_at: ?int, duration: ?int}>
     * }
     */
    public function getStatus(string $task): array
    {
        $logs = $this->getLogs($task);
        if ($logs === [])
        {
            return ['state' => 'idle', 'started_at' => null, 'finished_at' => null, 'duration' => null, 'progress' => null, 'output' => '', 'history' => []];
        }

        $runs = array_map(fn (string $log) => $this->parseLog($log), \array_slice($logs, 0, self::HISTORY_SIZE));
        $latest = $runs[0];
        $latest['history'] = array_map(
            fn (array $run) => array_intersect_key($run, array_flip(['state', 'started_at', 'finished_at', 'duration'])),
            $runs
        );

        return $latest;
    }

    /**
     * @return array{state: string, started_at: ?int, finished_at: ?int, duration: ?int, progress: ?array, output: string}
     */
    private function parseLog(string $log): array
    {
        $started_at = null;
        $exit_code = null;
        $cancelled_at = null;
        $progress = null;
        $output = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $line)
        {
            if (str_starts_with($line, self::START_MARKER))
            {
                $started_at = (int) substr($line, \strlen(self::START_MARKER));
            }
            elseif (str_starts_with($line, self::EXIT_MARKER))
            {
                $exit_code = (int) substr($line, \strlen(self::EXIT_MARKER));
            }
            elseif (str_starts_with($line, self::CANCEL_MARKER))
            {
                $cancelled_at = (int) substr($line, \strlen(self::CANCEL_MARKER));
            }
            elseif (str_starts_with($line, self::PID_MARKER))
            {
                continue;
            }
            elseif (preg_match('/^'.self::PROGRESS_MARKER.'(\d+)\/(\d+) ?(.*)$/', $line, $match))
            {
                $progress = ['current' => (int) $match[1], 'total' => (int) $match[2], 'label' => $match[3]];
            }
            elseif (trim($line) !== '')
            {
                $output[] = rtrim($line);
            }
        }

        $finished_at = $exit_code !== null ? filemtime($log) : $cancelled_at;
        if ($cancelled_at !== null)
        {
            $state = 'cancelled';
        }
        elseif ($exit_code === null)
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
            'duration' => $started_at !== null && $finished_at !== null ? max(0, $finished_at - $started_at) : null,
            // Progress only matters while running.
            'progress' => $state === 'running' ? $progress : null,
            'output' => implode("\n", \array_slice($output, -self::OUTPUT_LINES)),
        ];
    }

    /** @return string[] log files, newest first */
    private function getLogs(string $task): array
    {
        $this->migrateLegacyLog($task);
        $logs = glob($this->getLogDir($task).'/*.log') ?: [];
        rsort($logs);
        return $logs;
    }

    /**
     * Before the run history each task had a single var/log/tasks/<task>.log;
     * move it into the task's folder so its last run stays visible.
     */
    private function migrateLegacyLog(string $task): void
    {
        $legacy = \dirname($this->getLogDir($task)).'/'.$task.'.log';
        if (!is_file($legacy))
        {
            return;
        }
        $handle = @fopen($legacy, 'r');
        $first_line = $handle ? (string) fgets($handle) : '';
        if ($handle)
        {
            fclose($handle);
        }
        $started_at = str_starts_with($first_line, self::START_MARKER) ? (int) substr($first_line, \strlen(self::START_MARKER)) : filemtime($legacy);
        $mtime = filemtime($legacy);
        if (!is_dir($this->getLogDir($task)))
        {
            @mkdir($this->getLogDir($task), 0775, true);
        }
        $target = sprintf('%s/%d-legacy.log', $this->getLogDir($task), $started_at);
        if (@rename($legacy, $target))
        {
            // Keep the finish time, which is read from the file's mtime.
            @touch($target, $mtime);
        }
        @unlink($legacy.'.lock');
    }

    private function getLogDir(string $task): string
    {
        return $this->project_dir.'/var/log/tasks/'.$task;
    }
}
