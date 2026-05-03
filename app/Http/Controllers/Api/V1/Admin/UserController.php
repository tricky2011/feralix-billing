<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\IndexUserRequest;
use App\Http\Requests\UserManagement\ResetUserPasswordRequest;
use App\Http\Requests\UserManagement\StoreUserRequest;
use App\Http\Requests\UserManagement\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Technician;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = User::query()
            ->with('accessibleRouters:id,router_code,router_name,router_role,is_active')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', (bool) $filters['is_active'])
            );

        $users = $query
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $users,
            UserResource::class,
            'Users retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $routerIds = $this->routerIds($payload);

        $user = DB::transaction(function () use ($payload, $routerIds): User {
            $user = User::query()->create([
                'name' => $payload['name'],
                'username' => $payload['username'],
                'email' => $payload['email'] ?? null,
                'password' => Hash::make($payload['password']),
                'role' => $payload['role'],
                'is_active' => $payload['is_active'] ?? true,
            ]);

            $this->syncRouters($user, $routerIds);

            // Auto-create technician record for technician role
            $roleValue = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
            if ($roleValue === 'technician') {
                $technician = Technician::create([
                    'technician_code' => 'TIM-' . $user->name,
                    'full_name' => $user->name,
                    'phone' => null,
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                ]);
                $user->update(['technician_id' => $technician->id]);
            }

            return $user;
        });

        $this->activityLogger->record(
            $request->user(),
            'created',
            'user-management',
            sprintf('Created user %s (%s).', $user->name, $user->username),
            $request,
        );

        return $this->createdResponse(
            'User created successfully.',
            new UserResource($user->load('accessibleRouters:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            'User retrieved successfully.',
            new UserResource($user->load('accessibleRouters:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $payload = $request->validated();
        $this->guardSelfDeactivation($request, $user, $payload);
        $routerIdsProvided = array_key_exists('router_ids', $payload);
        $routerIds = $this->routerIds($payload);

        DB::transaction(function () use ($user, $payload, $routerIdsProvided, $routerIds): void {
            $updates = [
                'name' => $payload['name'],
                'username' => $payload['username'],
                'email' => $payload['email'] ?? null,
                'role' => $payload['role'],
                'is_active' => $payload['is_active'] ?? $user->is_active,
            ];

            if (! empty($payload['password'])) {
                $updates['password'] = Hash::make($payload['password']);
            }

            $user->update($updates);

            if ($routerIdsProvided) {
                $this->syncRouters($user, $routerIds);
            }

            // Sync technician record based on role
            $roleValue = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
            if ($roleValue === 'technician') {
                if ($user->technician_id && $user->technician) {
                    $user->technician->update([
                        'technician_code' => 'TIM-' . $user->name,
                        'full_name' => $user->name,
                        'is_active' => (bool) ($payload['is_active'] ?? $user->is_active),
                    ]);
                } else {
                    $technician = Technician::create([
                        'technician_code' => 'TIM-' . $user->name,
                        'full_name' => $user->name,
                        'phone' => null,
                        'is_active' => (bool) ($payload['is_active'] ?? true),
                    ]);
                    $user->update(['technician_id' => $technician->id]);
                }
            } else {
                if ($user->technician_id) {
                    $user->update(['technician_id' => null]);
                }
            }
        });

        $this->activityLogger->record(
            $request->user(),
            'updated',
            'user-management',
            sprintf('Updated user %s (%s).', $user->name, $user->username),
            $request,
        );

        return $this->successResponse(
            'User updated successfully.',
            new UserResource($user->refresh()->load('accessibleRouters:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($request, $user, 'You cannot disable your own account.');

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        $this->activityLogger->record(
            $request->user(),
            'disabled',
            'user-management',
            sprintf('Disabled user %s (%s).', $user->name, $user->username),
            $request,
        );

        return $this->successResponse(
            'User disabled successfully.',
            new UserResource($user->refresh()->load('accessibleRouters:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function enable(Request $request, User $user): JsonResponse
    {
        $user->forceFill(['is_active' => true])->save();

        $this->activityLogger->record(
            $request->user(),
            'enabled',
            'user-management',
            sprintf('Enabled user %s (%s).', $user->name, $user->username),
            $request,
        );

        return $this->successResponse(
            'User enabled successfully.',
            new UserResource($user->refresh()->load('accessibleRouters:id,router_code,router_name,router_role,is_active')),
        );
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
        ])->save();
        $user->tokens()->delete();

        $this->activityLogger->record(
            $request->user(),
            'password_reset',
            'user-management',
            sprintf('Reset password for user %s (%s).', $user->name, $user->username),
            $request,
        );

        return $this->successResponse('User password reset successfully.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($request, $user, 'You cannot delete your own account.');

        $label = sprintf('%s (%s)', $user->name, $user->username);
        $user->delete();

        $this->activityLogger->record(
            $request->user(),
            'deleted',
            'user-management',
            sprintf('Deleted user %s.', $label),
            $request,
        );

        return $this->successResponse('User deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function routerIds(array $payload): array
    {
        return collect($payload['router_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Keep the requirement pivot and the existing router-scope pivot in sync.
     *
     * @param  list<int>  $routerIds
     */
    private function syncRouters(User $user, array $routerIds): void
    {
        $user->routers()->sync($routerIds);
        $user->accessibleRouters()->sync($routerIds);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function guardSelfDeactivation(Request $request, User $user, array $payload): void
    {
        if (($payload['is_active'] ?? true) === false) {
            $this->guardSelf($request, $user, 'You cannot disable your own account.');
        }
    }

    private function guardSelf(Request $request, User $user, string $message): void
    {
        if ((int) $request->user()?->id !== (int) $user->id) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => $message,
        ]);
    }
}
