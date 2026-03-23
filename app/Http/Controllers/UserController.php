<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {}

    public function index()
    {
        $users = $this->userService->getAllUserWithHobbies();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users._form', ['user' => new User(), 'hobbies' => []]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userService->storeUser($request->validated());
        return redirect()->route('users.index')->with('success', 'User created');
    }

    public function edit(User $user)
    {
        $hobbies = $user->hobbies->pluck('name')->toArray();
        return view('users._form', compact('user', 'hobbies'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userService->updateUser($request->validated(), $user);
        return redirect()->route('users.index')->with('success', 'User updated');
    }

    public function destroy(User $user)
    {
        $this->userService->destroyUser($user);
        return redirect()->route('users.index')->with('success', 'User deleted');
    }
}
