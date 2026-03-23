<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponseHelper;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Hobby;
use App\Services\UserService;

class UserController extends Controller
{
    use ApiResponseHelper;

    public function __construct(private UserService $userService)
    {}
    /**
     * Menampilkan semua user
     *
     * @authenticated
     * 
     * @response 200 
     * {
     * "message": "Users fetched successfully",
     * "data": [
      *      {
      *          "id": 1,
      *          "name": "test",
      *          "email": "test@example.com",
      *          "email_verified_at": null,
      *          "created_at": "2025-05-19T16:02:49.000000Z",
      *          "updated_at": "2025-05-19T16:02:49.000000Z",
        *         "hobbies": [
      *              {
      *                  "id": 1,
       *                 "user_id": 1,
      *                  "name": "ngoding",
       *                 "created_at": "2025-05-19T17:54:22.000000Z",
       *                 "updated_at": "2025-05-19T17:54:22.000000Z"
       *             }
       *         ]
       *     }
      *  ]
      * }
     */
    public function index()
    {
        $users = $this->userService->getAllUserWithHobbies();
        return $this->successResponse($users, 'Users fetched successfully');
    }

    /**
     * Tambah user baru
     * 
     * @authenticated
     * @bodyParam name string required Nama user. Contoh: test
     * @bodyParam email string required Email user. Contoh: test@example.com
     * @bodyParam password string required Password user. Contoh: password
     * @bodyParam hobbies string Opsional, pisahkan dengan koma. Contoh: membaca
     * 
     * @response 201 {
     *   "message": "User created successfully",
     *   "data": {
     *       "name": "test2",
     *       "email": "test3@example.com",
     *       "updated_at": "2025-05-19T20:24:44.000000Z",
     *       "created_at": "2025-05-19T20:24:44.000000Z",
     *       "id": 11,
     *       "hobbies": [
     *           {
     *               "id": 20,
     *               "user_id": 11,
    *              "name": "Membaca",
     *               "created_at": "2025-05-19T20:24:44.000000Z",
     *               "updated_at": "2025-05-19T20:24:44.000000Z"
     *           }
     *       ]
     *   }
    *}
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->storeUser($request->validated());
        return $this->successResponse($user->load('hobbies'), "User created successfully", 201);
    }

    /**
     * Tampilkan detail user
     * 
     * @urlParam id int required ID user. Contoh: 1
     * @authenticated
     * 
     * @response 200 {
    *   "message": "User fetched successfully",
    *   "data": {
    *     "id": 1,
    *     "name": "test2",
    *     "email": "test3@example.com",
    *     "hobbies": [
    *       {
    *         "id": 1,
    *         "name": "Membaca",
    *         "user_id": 1,
    *         "created_at": "2024-01-01T00:00:00.000000Z",
    *         "updated_at": "2024-01-01T00:00:00.000000Z"
    *       }
    *     ]
    *   }
    * }
    * 
    * @response 404 {
    *   "message": "User not found",
    *   "errors": null
    * }
    **/
    public function show($id)
    {
        $user = User::with('hobbies')->find($id);

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse($user, 'User fetched successfully');
    }

    /**
     * Update data user
     * 
     * @urlParam id int required ID user. Contoh: 1
     * @authenticated
     * @bodyParam name string required Nama user. Contoh: test
     * @bodyParam email string required Email user. Contoh: test@example.com
     * @bodyParam password string optional Biarkan kosong jika tidak ingin diubah.
     * @bodyParam hobbies string Opsional. Contoh: membaca
     * 
     * @response 200 {
     *       "message": "User updated successfully",
     *       "data": {
     *           "id": 11,
     *           "name": "test2",
     *           "email": "test3@example.com",
     *           "email_verified_at": null,
     *           "created_at": "2025-05-19T20:24:44.000000Z",
     *           "updated_at": "2025-05-19T20:24:44.000000Z",
     *           "hobbies": []
     *       }
     *   }
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->updateUser($request->validated(), $user);
        return $this->successResponse($user->load('hobbies'), 'User updated successfully');
    }

    /**
     * Hapus user
     * 
     * @urlParam id int required ID user. Contoh: 1
     * @authenticated
     * 
     * @response 204 {
     *       "message": "204",
     *       "data": "User deleted successfully"
     *   }
     */
    public function destroy(User $user)
    {
        $this->userService->destroyUser($user);
        return $this->successResponse('User deleted successfully', 204);
    }
}
