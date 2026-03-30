<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 认证控制器
 * 
 * 处理用户认证相关操作，包括登录、注册、登出、令牌刷新等
 * 
 * @method JsonResponse login(['email'，'password']) 用户登录
 * @method JsonResponse register(['name', 'email', 'password', 'phone']) 用户注册
 * @method JsonResponse logout(['refresh_token']) 用户登出
 * @method JsonResponse refreshToken(['refresh_token']) 刷新访问令牌
 * @method JsonResponse me() 获取当前用户信息
 * @method JsonResponse changePassword(['old_password', 'new_password']) 修改密码
 */
class AuthController extends BaseController
{
    /**
     * 认证服务实例
     *
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * 构造函数
     *
     * @param AuthService $authService 认证服务
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * 用户登录
     *
     * 通过邮箱和密码进行用户认证，返回访问令牌和刷新令牌
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @bodyParam email string required 用户邮箱地址 Example: admin@example.com
     * @bodyParam password string required 用户密码，最少6个字符 Example: password123
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "token_type": "Bearer",
     *     "expires_in": 3600
     *   }
     * }
     * 
     * @response 422 {
     *   "code": 422,
     *   "message": "验证失败",
     *   "data": {
     *     "errors": {
     *       "email": ["邮箱 不能为空"],
     *       "password": ["密码 不能为空"]
     *     }
     *   }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|max:255',
        ], [], [
            'email' => __('validation.attributes.email'),
            'password' => __('validation.attributes.password'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $result = $this->authService->login($email, $password);

        return $this->success($result, __('messages.login_success'));
    }

    /**
     * 用户注册
     *
     * 创建新用户账户并返回访问令牌
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @bodyParam name string required 用户名称，只能包含字母和数字 Example: testuser
     * @bodyParam email string required 用户邮箱地址，必须唯一 Example: test@example.com
     * @bodyParam password string required 用户密码，最少6个字符 Example: password123
     * @bodyParam phone string 可选 用户手机号，必须唯一 Example: 13800138000
     * 
     * @response {
     *   "code": 201,
     *   "message": "成功",
     *   "data": {
     *     "user": {
     *       "id": 1,
     *       "name": "testuser",
     *       "email": "test@example.com"
     *     },
     *     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
     *   }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|alpha_num|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20|unique:users',
        ], [], [
            'name' => __('validation.attributes.name'),
            'email' => __('validation.attributes.email'),
            'password' => __('validation.attributes.password'),
            'phone' => __('validation.attributes.phone'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $data = $request->only(['name', 'email', 'password', 'phone']);
        $result = $this->authService->register($data);

        return $this->success($result, __('messages.success'), 201);
    }

    /**
     * 用户登出
     *
     * 注销当前用户的访问令牌
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @response {
     *   "code": 200,
     *   "message": "登出成功",
     *   "data": null
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->attributes->get('jwt_token');
            $user = $this->user();
            
            if ($user && $token) {
                $this->authService->logout($user, $token);
            }
        } catch (\Exception $e) {
            // 即使登出失败也返回成功，不影响用户体验
            \Log::warning('Logout failed: ' . $e->getMessage());
        }

        return $this->success(null, __('messages.logout_success'));
    }

    /**
     * 刷新访问令牌
     *
     * 使用刷新令牌获取新的访问令牌
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @bodyParam refresh_token string required 刷新令牌 Example: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "token_type": "Bearer",
     *     "expires_in": 3600
     *   }
     * }
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ], [], [
            'refresh_token' => __('validation.attributes.refresh_token'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $token = $request->input('refresh_token');
        $result = $this->authService->refreshToken($token);

        return $this->success($result, __('messages.success'));
    }

    /**
     * 获取当前用户信息
     *
     * 返回当前认证用户的详细信息
     *
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @response {
     *   "code": 200,
     *   "message": "成功",
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "email": "admin@example.com",
     *     "roles": ["admin"],
     *     "permissions": ["user.view", "user.create"]
     *   }
     * }
     */
    public function me(): JsonResponse
    {
        $user = $this->user();

        return $this->success($user);
    }

    /**
     * 修改密码
     *
     * 更新当前用户的密码
     *
     * @param Request $request HTTP请求对象
     * @return JsonResponse JSON响应
     * 
     * @authenticated
     * 
     * @bodyParam old_password string required 原密码，最少6个字符 Example: oldpassword
     * @bodyParam new_password string required 新密码，最少6个字符，不能与原密码相同 Example: newpassword123
     * 
     * @response {
     *   "code": 200,
     *   "message": "密码修改成功",
     *   "data": null
     * }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->user();
        
        if (!$user) {
            return $this->error(__('messages.unauthorized'), 401);
        }

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:6|max:255',
            'new_password' => 'required|string|min:6|max:255|different:old_password',
        ], [], [
            'old_password' => __('validation.attributes.old_password'),
            'new_password' => __('validation.attributes.new_password'),
        ]);

        if ($validator->fails()) {
            return $this->error(__('messages.validation_error'), 422, $validator->errors());
        }

        $oldPassword = $request->input('old_password');
        $newPassword = $request->input('new_password');
        
        $this->authService->changePassword($user, $oldPassword, $newPassword);

        return $this->success(null, __('messages.password_changed'));
    }
}
