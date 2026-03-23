<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">
            {{ $user->exists ? 'Edit User' : 'Tambah User' }}
        </h1>

        <form method="POST" action="{{ $user->exists ? route('users.update', $user->id) : route('users.store') }}">
            @csrf
            @if($user->exists) @method('PUT') @endif

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-1">Nama</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-500 @error('name') border-red-500 @enderror"">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-500 @error('email') border-red-500 @enderror">
                @error('email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-1">
                    Password {{ $user->exists ? '(kosongkan jika tidak diubah)' : '' }}
                </label>
                <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-500 @error('password') border-red-500 @enderror">
                @error('password')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-1">Hobi (pisahkan dengan koma)</label>
                <input type="text" name="hobbies" value="{{ old('hobbies', implode(',', $hobbies ?? [])) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:border-blue-500">
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    Simpan
                </button>
                <a href="{{ route('users.index') }}"
                   class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                    Batal
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
