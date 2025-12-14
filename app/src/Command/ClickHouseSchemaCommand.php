<?php

namespace App\Command;

use ClickHouseDB\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clickhouse:setup',
    description: 'Creates the necessary tables in ClickHouse for event logging.',
)]
class ClickHouseSchemaCommand extends Command
{
    public function __construct(
        private readonly Client $clickHouseClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tableName = 'events_log_raw';

        $sql = "
            CREATE TABLE IF NOT EXISTS {$tableName} (
                id String,
                event_type String,
                payload_json String,
                created_at DateTime,
                event_date Date MATERIALIZED toDate(created_at)
            ) ENGINE = MergeTree()
            PARTITION BY event_date
            ORDER BY (event_type, created_at)
        ";

        try {
            $this->clickHouseClient->write($sql);
            $io->success(sprintf('Table "%s" successfully created or already exists in ClickHouse.', $tableName));
        } catch (\Exception $e) {
            $io->error('Failed to create ClickHouse table: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
