<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Modules\Identity\Application\Actions\CreateUser;
use App\Modules\Identity\Application\Actions\DeleteUser;
use App\Modules\Identity\Application\Actions\UpdateUser;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use App\Modules\Identity\Presentation\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Presentation\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Presentation\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Resource controller — the 7 RESTful methods, nothing else.
 *
 * Anything beyond CRUD is a separate single-action controller (see
 * AuthenticateUserController, EnableMfaController, etc.).
 */
final class UserController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = UserModel::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 25));

        return UserResource::collection($users);
    }

    public function show(Request $request, string $id): UserResource
    {
        return new UserResource(UserModel::findOrFail($id));
    }

    public function create(): JsonResponse
    {
        // Returns the form schema (roles, permissions, branches) for SPA "create user" UI
        return new JsonResponse([
            'roles'    => \Spatie\Permission\Models\Role::all()->pluck('name'),
            'branches' => [], // populated from identity.branches
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $action): JsonResponse
    {
        $user = $action->execute($request->validated(), $request->user());

        return new JsonResponse(new UserResource($user), 201);
    }

    public function edit(Request $request, string $id): JsonResponse
    {
        return new JsonResponse([
            'user'  => new UserResource(UserModel::findOrFail($id)),
            'roles' => \Spatie\Permission\Models\Role::all()->pluck('name'),
        ]);
    }

    public function update(UpdateUserRequest $request, UpdateUser $action, string $id): UserResource
    {
        $user = $action->execute($id, $request->validated(), $request->user());

        return new UserResource($user);
    }

    public function destroy(Request $request, DeleteUser $action, string $id): JsonResponse
    {
        $action->execute($id, $request->user());

        return new JsonResponse(null, 204);
    }
}
