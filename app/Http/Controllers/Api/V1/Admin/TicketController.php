<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\IndexTicketRequest;
use App\Http\Requests\Ticket\StoreTicketReplyRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketStatusRequest;
use App\Http\Resources\TicketReplyResource;
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

        return $this->paginatedResponse(
            $tickets,
            TicketResource::class,
            'Tickets retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated());

        return $this->createdResponse('Ticket created successfully.', new TicketResource($ticket));
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return $this->successResponse(
            'Ticket retrieved successfully.',
            new TicketResource($this->ticketService->find($ticket)),
        );
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->ticketService->updateStatus($ticket, $request->validated());

        return $this->successResponse(
            'Ticket status updated successfully.',
            new TicketResource($ticket),
        );
    }

    public function storeReply(StoreTicketReplyRequest $request, Ticket $ticket): JsonResponse
    {
        $reply = $this->ticketService->reply($ticket, $request->validated());

        return $this->createdResponse(
            'Ticket reply created successfully.',
            new TicketReplyResource($reply),
        );
    }
}
