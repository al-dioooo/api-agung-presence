<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * List all users (employees). Accessible by authenticated users;
     * administrators see all, employees see their own team.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('name');

        if ($request->has('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return $this->success(
            'Users retrieved successfully.',
            UserResource::collection($query->get()),
        );
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return $this->success('User retrieved successfully.', new UserResource($user));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return $this->success('User updated successfully.', new UserResource($user->fresh()));
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (! $request->user()?->isAdministrator()) {
            return $this->error('Unauthorized.', 403);
        }

        if ($request->user()->id === $user->id) {
            return $this->error('You cannot delete your own account.', 422);
        }

        $user->delete();

        return $this->success('User deleted successfully.');
    }
}
