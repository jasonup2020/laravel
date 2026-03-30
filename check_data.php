<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== 用户数据 ===\n";
$users = App\Models\User::get(['id', 'email', 'name']);
foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Name: {$user->name}\n";
    echo "---\n";
}

echo "\n=== 租户数据 ===\n";
$tenants = App\Models\Tenant::get(['id', 'name', 'code']);
foreach ($tenants as $tenant) {
    echo "ID: {$tenant->id}, Name: {$tenant->name}, Code: {$tenant->code}\n";
}
