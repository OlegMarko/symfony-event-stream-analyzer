<?php

namespace App\Controller;

use App\Message\EventMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/api/event/dispatch', methods: ['POST'])]
    public function dispatch(Request $request, MessageBusInterface $bus): JsonResponse
    {
        $data = $request->toArray();

        $eventType = $data['type'] ?? 'unknown_event';
        $eventPayload = $data['payload'] ?? [];

        $message = new EventMessage($eventType, $eventPayload);

        $bus->dispatch($message);

        return new JsonResponse([
            'status' => 'accepted',
            'message' => 'Event dispatched to RabbitMQ',
            'id' => uniqid()
        ], 202);
    }
}