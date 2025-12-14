<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SearchController extends AbstractController
{
    private const ES_URL = 'http://elasticsearch:9200/events_index/_search';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    #[Route('/api/search/events', name: 'api_search_events', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $queryText = $request->query->get('q');

        if (empty($queryText)) {
            return $this->json(['error' => 'Query parameter "q" is required.'], 400);
        }

        $searchBody = [
            'query' => [
                'multi_match' => [
                    'query' => $queryText,
                    'fields' => ['full_text', 'type'],
                ],
            ],
            'size' => 10,
        ];

        try {
            $response = $this->httpClient->request('GET', self::ES_URL, [
                'json' => $searchBody
            ]);

            $content = $response->toArray();

            $results = [];
            foreach ($content['hits']['hits'] as $hit) {
                $results[] = [
                    'id' => $hit['_id'],
                    'score' => $hit['_score'],
                    'type' => $hit['_source']['type'],
                    'indexed_at' => $hit['_source']['indexed_at'] ?? 'N/A',
                ];
            }

            return $this->json([
                'total' => $content['hits']['total']['value'] ?? 0,
                'events' => $results,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Elasticsearch Connection Failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}