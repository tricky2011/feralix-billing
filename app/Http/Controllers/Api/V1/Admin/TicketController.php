<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Helpdesk\TicketService;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService) {}

    public function index(IndexTicketRequest $request)
    {
        $tickets = $this->ticketService->paginate($request->validated());

        return TicketResource::collection($tickets)->additional([
            'message' => 'Tickets retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated());

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => new TicketResource($ticket),
        ], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json([
            'message' => 'Ticket retrieved successfully.',
            'data' => new TicketResource($this->ticketService->find($ticket)),
        ]);
    }
}
