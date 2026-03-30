<?php
namespace App\Support;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Redis;

class JwtManager {

public static function issue($user,$tenantId){

$payload=[
'uid'=>$user->id,
'tid'=>$tenantId,
'exp'=>now()->addMinutes(180)->timestamp
];

$token=JWT::encode($payload,config('app.key'),'HS256');

Redis::set("login:$user->id",$token);

return $token;
}

public static function check($token){

$data=JWT::decode($token,new Key(config('app.key'),'HS256'));

if(Redis::get("login:$data->uid")!=$token){
abort(401,'device kicked');
}

return $data;
}
}
