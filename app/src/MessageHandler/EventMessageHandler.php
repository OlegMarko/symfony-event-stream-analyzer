<?php

namespace App\MessageHandler;

use App\Entity\EventLog;
use App\Message\EventMessage;
use ClickHouseDB\Client;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class EventMessageHandler
{
    private const ES_INDEX_URL = 'http://elasticsearch:9200/events_index/_doc/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface        $logger,
        private readonly Client                 $clickHouseClient,
        private readonly HttpClientInterface    $httpClient
    ) { }

    #[NoReturn]
    public function __invoke(EventMessage $message): void
    {
        $payloadJson = json_encode($message->getPayload(), JSON_THROW_ON_ERROR);

        $log = new EventLog();
        $log->setType($message->getType());
        $log->setPayload($message->getPayload());
        $log->setCreatedAt($message->getCreatedAt());

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->logger->info('Event saved to PostgreSQL.', ['id' => $log->getId(), 'type' => $log->getType()]);

        $esData = [
            'type' => $log->getType(),
            'indexed_at' => $log->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'full_text' => sprintf("Type: %s. Payload details: %s", $log->getType(), $payloadJson)
        ];

        $esUrl = self::ES_INDEX_URL . $log->getId();

        try {
            $this->httpClient->request('PUT', $esUrl, [
                'json' => $esData
            ]);
            $this->logger->info('Event indexed in Elasticsearch successfully.', ['es_id' => $log->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to index event in Elasticsearch.', ['error' => $e->getMessage()]);
        }


        $dataForClickHouse = [
            'id' => $log->getId() ? (string)$log->getId() : Uuid::v4()->toRfc4122(),
            'event_type' => $log->getType(),
            'payload_json' => $payloadJson,
            'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
        ];

        try {
            $this->clickHouseClient->insert('events_log_raw', [$dataForClickHouse]);
            $this->logger->info('Event saved to ClickHouse for analytical processing.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to save event to ClickHouse.', ['error' => $e->getMessage()]);
        }
    }
}