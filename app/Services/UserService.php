<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService {
    public function getAllUserWithHobbies()
    {
        return User::with('hobbies')->get();
    }

    public function storeUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'])
            ]);

            if (!empty($data['hobbies'])) {
                $hobbies = $this->splitHobbies($data['hobbies']);

                foreach ($hobbies as $hobby) {
                    $user->hobbies()->create(['name' => $hobby]);
                }
            }

            return $user;
        });
    }

    public function updateUser(array $data, User $user)
    {
        DB::transaction(function () use ($data, $user) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
                $user->save();
            }

            $user->hobbies()->delete();

            if (!empty($data['hobbies'])) {
                $hobbies = $this->splitHobbies($data['hobbies']);

                foreach ($hobbies as $hobby) {
                    $user->hobbies()->create(['name' => $hobby]);
                }
            }
        });
    }

    public function destroyUser(User $user) 
    {
        DB::transaction(function () use ($user) {
            $user->hobbies()->delete();
            $user->delete();
        });
    }

    public function splitHobbies(String $hobbies) 
    {
        return array_filter(array_map('trim', explode(',', $hobbies)));
    }
}