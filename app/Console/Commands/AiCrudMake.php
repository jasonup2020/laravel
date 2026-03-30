<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AiCrudMake extends Command {

protected $signature='ai:crud {name}';

public function handle(){
$name=$this->argument('name');
Artisan::call("make:model $name -mfscr");
}
}
