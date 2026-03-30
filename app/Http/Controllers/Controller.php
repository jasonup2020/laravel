<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * 基础控制器
 * 
 * 继承Laravel基础控制器
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
