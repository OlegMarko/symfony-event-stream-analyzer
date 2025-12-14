<?php

namespace App\Tests\MessageHandler;

use App\Entity\EventLog;
use App\Message\EventMessage;
use App\MessageHandler\EventMessageHandler;
use ClickHouseDB\Client;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventMessageHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $esClient;
    private Client $clickHouseClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->clickHouseClient = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->esClient = new MockHttpClient(function (string $method, string $url, array $options) {
            return new MockResponse('', ['http_code' => 201]);
        });
    }

    public function testInvokeSavesAndIndexesSuccessfully(): void
    {
        $message = new EventMessage('new_order', ['user_id' => 123, 'amount' => 99.99]);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EventLog::class));

        $this->entityManager->expects($this->once())->method('flush');

        $this->clickHouseClient->expects($this->once())
            ->method('insert')
            ->with(
                'events_log_raw',
                $this->isArray()
            );

        $this->logger->expects($this->exactly(3))->method('info');

        $this->logger->expects($this->never())->method('error');

        $handler = new EventMessageHandler(
            $this->entityManager,
            $this->logger,
            $this->clickHouseClient,
            $this->esClient
        );

        $handler($message);
    }

    public function testInvokeLogsErrorOnClickHouseFailure(): void
    {
        $message = new EventMessage('payment_failed', ['reason' => 'timeout']);

        $this->clickHouseClient->expects($this->once())
            ->method('insert')
            ->willThrowException(new \Exception('ClickHouse Connection Error'));

        $this->logger->expects($this->once())->method('error');

        $handler = new EventMessageHandler(
            $this->entityManager,
            $this->logger,
            $this->clickHouseClient,
            $this->esClient
        );

        $handler($message);
    }
}