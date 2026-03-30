<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'admin@example.com')->first();

echo "User ID: " . $user->id . "\n";
echo "Email: " . $user->email . "\n";
echo "Random Code: " . $user->random_code . "\n";
echo "Password Hash: " . substr($user->password, 0, 30) . "...\n";

// 测试密码验证
$password = 'admin123';
$combinedPassword = $password . $user->random_code;
echo "\nCombined Password: " . $combinedPassword . "\n";
echo "Hash::check result: " . (Hash::check($combinedPassword, $user->password) ? 'true' : 'false') . "\n";
echo "verifyPassword result: " . ($user->verifyPassword($password) ? 'true' : 'false') . "\n";

// 测试直接验证
$testHash = Hash::make($password . $user->random_code);
echo "\nNew Hash for same password: " . substr($testHash, 0, 30) . "...\n";
echo "New Hash verify: " . (Hash::check($password . $user->random_code, $testHash) ? 'true' : 'false') . "\n";
