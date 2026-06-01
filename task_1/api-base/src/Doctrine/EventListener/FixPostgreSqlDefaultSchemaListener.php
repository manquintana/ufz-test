<?php declare(strict_types=1);

namespace Ufz\ApiBase\Doctrine\EventListener;

use Doctrine\DBAL\Schema\PostgreSqlSchemaManager;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * When creating migrations with Postgres, Doctrine generates SQL command: `CREATE SCHEMA public` in down() migration.
 * Since this command results in error when doing rollbacks of migrations,
 * the purpose of this class is to avoid generation of this unnecessary CREATE command.
 * Bug report in doctrine: https://github.com/doctrine/dbal/issues/1110
 */
final class FixPostgreSqlDefaultSchemaListener
{
    /**
     * @param GenerateSchemaEventArgs $args
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schemaManager = $args
            ->getEntityManager()
            ->getConnection()
            ->getSchemaManager()
        ;

        if (!$schemaManager instanceof PostgreSqlSchemaManager) {
            return;
        }

        foreach ($schemaManager->getSchemaNames() as $namespace) {
            if (!$args->getSchema()->hasNamespace($namespace)) {
                $args->getSchema()->createNamespace($namespace);
            }
        }
    }
}
