<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidConfiguration;
use Crosseno\Compiler\Exception\InvalidSourceRecord;

final readonly class SqliteImporter extends AbstractFileImporter
{
    public function __construct(private string $table = 'entries', private RecordMapper $mapper = new RecordMapper())
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $table) !== 1) {
            throw new InvalidConfiguration('SQLite table name is invalid.');
        }
    }

    public function import(SourceInput $source, ImportLimits $limits): iterable
    {
        $this->assertSize($source, $limits);
        try {
            $uriPath = str_replace(['%', '?', '#'], ['%25', '%3F', '%23'], $source->path);
            $pdo = new \PDO('sqlite:file:' . $uriPath . '?mode=ro&immutable=1', null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec('PRAGMA query_only = ON');
            $statement = $pdo->query(\sprintf('SELECT * FROM "%s" ORDER BY rowid ASC', $this->table));
            if (!$statement instanceof \PDOStatement || $statement->columnCount() > $limits->maximumFields) {
                throw new InvalidSourceRecord('SQLite source table is invalid or too wide.');
            }
            $number = 0;
            while (($row = $statement->fetch()) !== false) {
                ++$number;
                $this->assertRecord($number, $limits);
                if (!\is_array($row)) {
                    throw new InvalidSourceRecord('SQLite source returned an invalid row.');
                }
                yield $this->mapper->map($row, $source->sourceId, $number, $limits);
            }
        } catch (\PDOException $exception) {
            throw new InvalidSourceRecord('SQLite source could not be read safely.', previous: $exception);
        }
    }
}
