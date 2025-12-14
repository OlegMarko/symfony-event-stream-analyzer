<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SearchControllerTest extends WebTestCase
{
    public function testSearchReturnsJsonData(): void
    {
        $client = static::createClient();

        $mockResponse = new MockResponse(
            json_encode([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [
                        [
                            '_id' => 101,
                            '_score' => 1.0,
                            '_source' => [
                                'type' => 'new_user',
                                'indexed_at' => '2025-12-14 15:22:00',
                            ],
                        ],
                    ],
                ],
            ]),
            ['http_code' => 200, 'content_type' => 'application/json']
        );

        $mockHttpClient = new MockHttpClient($mockResponse);

        $client->getContainer()->set('http_client', $mockHttpClient);

        $client->request('GET', '/api/search/events?q=new_user');

        $this->assertResponseIsSuccessful();

        $expected = [
            'total' => 1,
            'events' => [
                [
                    'id' => 101,
                    'score' => 1.0,
                    'type' => 'new_user',
                    'indexed_at' => '2025-12-14 15:22:00',
                ],
            ],
        ];
        $this->assertJsonStringEqualsJsonString(
            json_encode($expected),
            $client->getResponse()->getContent()
        );
    }
}