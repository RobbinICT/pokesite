<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Downloadable copies of the SQLite database, the whole collection in one file.
 * To restore: stop the stack and put the backup in place of var/data.db.
 */
class DatabaseBackup
{
    /** Older than this, the Tools page nudges you to download a new backup. */
    public const STALE_AFTER_DAYS = 30;

    private Connection $connection;
    private string $marker_file;

    public function __construct(Connection $connection, KernelInterface $kernel)
    {
        $this->connection = $connection;
        // Touched on every download, so the Tools page can show when the last backup was made.
        $this->marker_file = $kernel->getProjectDir().'/var/last-backup';
    }

    /**
     * @return array{size: int|null, last_backup: int|null, stale: bool}
     */
    public function getInfo(): array
    {
        $path = $this->getDatabasePath();
        $last_backup = is_file($this->marker_file) ? filemtime($this->marker_file) : null;

        return [
            'size' => $path !== null && is_file($path) ? filesize($path) : null,
            'last_backup' => $last_backup ?: null,
            'stale' => !$last_backup || $last_backup < time() - self::STALE_AFTER_DAYS * 86400,
        ];
    }

    /**
     * Writes a consistent copy of the database to a temporary file and returns its path.
     * VACUUM INTO is safe while the site or a task is writing, unlike copying the file.
     * The caller deletes the file when done.
     */
    public function create(): string
    {
        $path = sprintf('%s/pokesite-backup-%s.sqlite', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        $this->connection->executeStatement('VACUUM INTO ?', [$path]);
        touch($this->marker_file);

        return $path;
    }

    private function getDatabasePath(): ?string
    {
        return $this->connection->getParams()['path'] ?? null;
    }
}
