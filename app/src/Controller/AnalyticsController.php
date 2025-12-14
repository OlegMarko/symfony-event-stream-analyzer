<?php

namespace App\Controller;

use ClickHouseDB\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly Client $clickHouseClient
    ) {}

    #[Route('/api/analytics/summary', name: 'api_analytics_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $query = "
            SELECT 
                toStartOfDay(created_at) AS day,
                event_type, 
                count() AS total
            FROM events_log_raw
            WHERE created_at >= now() - INTERVAL 7 DAY
            GROUP BY day, event_type
            ORDER BY day DESC, total DESC
        ";

        try {
            $data = $this->clickHouseClient->select($query)->rows();

            $structuredData = [];
            foreach ($data as $row) {
                $day = $row['day'];
                if (!isset($structuredData[$day])) {
                    $structuredData[$day] = [];
                }
                $structuredData[$day][$row['event_type']] = (int)$row['total'];
            }

            return $this->json([
                'source' => 'ClickHouse',
                'description' => 'Event counts by type over the last 7 days.',
                'results' => $structuredData,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'ClickHouse Query Failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}