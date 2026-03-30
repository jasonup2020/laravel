<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * SaaS CRUD自动生成命令
 * 
 * 自动生成完整的CRUD代码
 */
class MakeSaasCrud extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'make:saas-crud {name : 模块名称} {--force : 强制覆盖已存在的文件}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '自动生成完整的CRUD代码（Migration/Model/Request/Service/Controller/Resource/Route/Test/Filter/Seeder）';

    /**
     * 模块名称
     *
     * @var string
     */
    protected string $moduleName;

    /**
     * 模块名称（单数）
     *
     * @var string
     */
    protected string $singularName;

    /**
     * 模块名称（复数）
     *
     * @var string
     */
    protected string $pluralName;

    /**
     * 表名
     *
     * @var string
     */
    protected string $tableName;

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $this->moduleName = $this->argument('name');
        $this->singularName = Str::singular($this->moduleName);
        $this->pluralName = Str::plural($this->moduleName);
        $this->tableName = Str::snake($this->pluralName);

        $this->info("开始生成 {$this->moduleName} 模块的CRUD代码...");

        // 生成文件
        $this->generateMigration();
        $this->generateModel();
        $this->generateRequest();
        $this->generateService();
        $this->generateController();
        $this->generateResource();
        $this->generateTest();
        $this->generateSeeder();
        $this->updateRoutes();

        $this->newLine();
        $this->info("✅ {$this->moduleName} 模块CRUD代码生成完成！");

        return Command::SUCCESS;
    }

    /**
     * 生成迁移文件
     */
    protected function generateMigration(): void
    {
        $this->info('生成迁移文件...');

        $fileName = date('Y_m_d_His') . "_create_{$this->tableName}_table.php";
        $path = database_path("migrations/{$fileName}");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 迁移文件已存在，跳过');
            return;
        }

        $content = $this->getMigrationContent();
        File::put($path, $content);

        $this->line("  ✓ 迁移文件已创建: {$fileName}");
    }

    /**
     * 生成模型文件
     */
    protected function generateModel(): void
    {
        $this->info('生成模型文件...');

        $path = app_path("Models/{$this->singularName}.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 模型文件已存在，跳过');
            return;
        }

        $content = $this->getModelContent();
        File::put($path, $content);

        $this->line("  ✓ 模型文件已创建: {$this->singularName}.php");
    }

    /**
     * 生成请求验证文件
     */
    protected function generateRequest(): void
    {
        $this->info('生成请求验证文件...');

        $path = app_path("Http/Requests/{$this->singularName}Request.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 请求验证文件已存在，跳过');
            return;
        }

        $content = $this->getRequestContent();
        File::put($path, $content);

        $this->line("  ✓ 请求验证文件已创建: {$this->singularName}Request.php");
    }

    /**
     * 生成服务文件
     */
    protected function generateService(): void
    {
        $this->info('生成服务文件...');

        $path = app_path("Services/{$this->singularName}Service.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 服务文件已存在，跳过');
            return;
        }

        $content = $this->getServiceContent();
        File::put($path, $content);

        $this->line("  ✓ 服务文件已创建: {$this->singularName}Service.php");
    }

    /**
     * 生成控制器文件
     */
    protected function generateController(): void
    {
        $this->info('生成控制器文件...');

        $path = app_path("Http/Controllers/Api/V1/{$this->singularName}Controller.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 控制器文件已存在，跳过');
            return;
        }

        $content = $this->getControllerContent();
        File::put($path, $content);

        $this->line("  ✓ 控制器文件已创建: {$this->singularName}Controller.php");
    }

    /**
     * 生成资源文件
     */
    protected function generateResource(): void
    {
        $this->info('生成资源文件...');

        $path = app_path("Http/Resources/{$this->singularName}Resource.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 资源文件已存在，跳过');
            return;
        }

        $content = $this->getResourceContent();
        File::put($path, $content);

        $this->line("  ✓ 资源文件已创建: {$this->singularName}Resource.php");
    }

    /**
     * 生成测试文件
     */
    protected function generateTest(): void
    {
        $this->info('生成测试文件...');

        $path = tests_path("Feature/{$this->singularName}Test.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ 测试文件已存在，跳过');
            return;
        }

        $content = $this->getTestContent();
        File::put($path, $content);

        $this->line("  ✓ 测试文件已创建: {$this->singularName}Test.php");
    }

    /**
     * 生成Seeder文件
     */
    protected function generateSeeder(): void
    {
        $this->info('生成Seeder文件...');

        $path = database_path("seeders/{$this->singularName}Seeder.php");

        if (File::exists($path) && !$this->option('force')) {
            $this->warn('  ✗ Seeder文件已存在，跳过');
            return;
        }

        $content = $this->getSeederContent();
        File::put($path, $content);

        $this->line("  ✓ Seeder文件已创建: {$this->singularName}Seeder.php");
    }

    /**
     * 更新路由文件
     */
    protected function updateRoutes(): void
    {
        $this->info('更新路由文件...');

        $path = base_path('routes/api.php');
        $routeName = Str::kebab($this->pluralName);
        $controllerName = "App\\Http\\Controllers\\Api\\V1\\{$this->singularName}Controller";

        $routeContent = <<<PHP

// {$this->singularName} 路由
Route::apiResource('{$routeName}', {$controllerName}::class);
Route::post('{$routeName}/batch-delete', [{$controllerName}::class, 'batchDelete']);

PHP;

        $content = File::get($path);
        
        if (strpos($content, $routeName) === false) {
            File::append($path, $routeContent);
            $this->line("  ✓ 路由已添加");
        } else {
            $this->warn('  ✗ 路由已存在，跳过');
        }
    }

    /**
     * 获取迁移文件内容
     */
    protected function getMigrationContent(): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$this->tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID');
            \$table->string('name')->comment('名称');
            \$table->string('description')->nullable()->comment('描述');
            \$table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
            \$table->unsignedBigInteger('created_by')->nullable()->comment('创建者');
            \$table->unsignedBigInteger('updated_by')->nullable()->comment('更新者');
            \$table->timestamps();
            \$table->softDeletes();

            \$table->index('tenant_id');
            \$table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$this->tableName}');
    }
};
PHP;
    }

    /**
     * 获取模型文件内容
     */
    protected function getModelContent(): string
    {
        return <<<PHP
<?php

namespace App\Models;

/**
 * {$this->singularName}模型
 */
class {$this->singularName} extends BaseModel
{
    protected \$fillable = [
        'tenant_id',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected \$casts = [
        'tenant_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * 获取租户
     */
    public function tenant()
    {
        return \$this->belongsTo(Tenant::class, 'tenant_id');
    }
}
PHP;
    }

    /**
     * 获取请求验证文件内容
     */
    protected function getRequestContent(): string
    {
        return <<<PHP
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * {$this->singularName}请求验证
 */
class {$this->singularName}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        \$rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
        ];

        if (\$this->isMethod('PUT') || \$this->isMethod('PATCH')) {
            \$rules['name'] = 'sometimes|string|max:255';
        }

        return \$rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.required', ['attribute' => '名称']),
            'name.string' => __('validation.string', ['attribute' => '名称']),
            'name.max' => __('validation.max.string', ['attribute' => '名称', 'max' => 255]),
        ];
    }
}
PHP;
    }

    /**
     * 获取服务文件内容
     */
    protected function getServiceContent(): string
    {
        return <<<PHP
<?php

namespace App\Services;

use App\Models\\{$this->singularName};

/**
 * {$this->singularName}服务
 */
class {$this->singularName}Service extends BaseService
{
    protected string \$modelClass = {$this->singularName}::class;
}
PHP;
    }

    /**
     * 获取控制器文件内容
     */
    protected function getControllerContent(): string
    {
        return <<<PHP
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\\{$this->singularName}Request;
use App\Http\Resources\\{$this->singularName}Resource;
use App\Services\\{$this->singularName}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * {$this->singularName}控制器
 */
class {$this->singularName}Controller extends Controller
{
    protected {$this->singularName}Service \${$this->singularName}Service;

    public function __construct({$this->singularName}Service \${$this->singularName}Service)
    {
        \$this->{$this->singularName}Service = \${$this->singularName}Service;
    }

    public function index(Request \$request): JsonResponse
    {
        \$filters = \$this->getFilterParams(\$request, ['name', 'status']);
        \$sort = \$this->getSortParams(\$request);
        \$perPage = \$request->input('per_page', 15);

        \$items = \$this->{$this->singularName}Service->getList(
            \$filters,
            ['*'],
            [],
            \$perPage,
            \$sort['field'],
            \$sort['dir']
        );

        return \$this->resource(\$items, {$this->singularName}Resource::class);
    }

    public function store({$this->singularName}Request \$request): JsonResponse
    {
        \$data = \$request->validated();
        \$item = \$this->{$this->singularName}Service->create(\$data);

        return \$this->success(new {$this->singularName}Resource(\$item), __('messages.create_success'));
    }

    public function show(int \$id): JsonResponse
    {
        \$item = \$this->{$this->singularName}Service->getByIdOrFail(\$id);

        return \$this->success(new {$this->singularName}Resource(\$item));
    }

    public function update({$this->singularName}Request \$request, int \$id): JsonResponse
    {
        \$data = \$request->validated();
        \$item = \$this->{$this->singularName}Service->update(\$id, \$data);

        return \$this->success(new {$this->singularName}Resource(\$item), __('messages.update_success'));
    }

    public function destroy(int \$id): JsonResponse
    {
        \$this->{$this->singularName}Service->delete(\$id);

        return \$this->success(null, __('messages.delete_success'));
    }

    public function batchDelete(Request \$request): JsonResponse
    {
        \$ids = \$request->input('ids', []);
        \$count = \$this->{$this->singularName}Service->batchDelete(\$ids);

        return \$this->success(['count' => \$count], __('messages.delete_success'));
    }
}
PHP;
    }

    /**
     * 获取资源文件内容
     */
    protected function getResourceContent(): string
    {
        return <<<PHP
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * {$this->singularName}资源
 */
class {$this->singularName}Resource extends JsonResource
{
    public function toArray(\$request): array
    {
        return [
            'id' => \$this->id,
            'tenant_id' => \$this->tenant_id,
            'name' => \$this->name,
            'description' => \$this->description,
            'status' => \$this->status,
            'created_by' => \$this->created_by,
            'updated_by' => \$this->updated_by,
            'created_at' => \$this->created_at?->toIso8601String(),
            'updated_at' => \$this->updated_at?->toIso8601String(),
        ];
    }
}
PHP;
    }

    /**
     * 获取测试文件内容
     */
    protected function getTestContent(): string
    {
        $routeName = Str::kebab($this->pluralName);
        
        return <<<PHP
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\\{$this->singularName};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class {$this->singularName}Test extends TestCase
{
    use RefreshDatabase;

    protected User \$user;
    protected string \$token;

    protected function setUp(): void
    {
        parent::setUp();
        
        \$this->user = User::factory()->create();
        \$this->token = \$this->getToken(\$this->user);
    }

    public function test_get_list(): void
    {
        {$this->singularName}::factory()->count(3)->create();

        \$response = \$this->withToken(\$this->token)
            ->getJson('/api/{$routeName}');

        \$response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => [
                    'list',
                    'pagination'
                ]
            ]);
    }

    public function test_create(): void
    {
        \$data = [
            'name' => 'Test {$this->singularName}',
            'description' => 'Test Description',
        ];

        \$response = \$this->withToken(\$this->token)
            ->postJson('/api/{$routeName}', \$data);

        \$response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test {$this->singularName}');
    }

    public function test_show(): void
    {
        \$item = {$this->singularName}::factory()->create();

        \$response = \$this->withToken(\$this->token)
            ->getJson("/api/{$routeName}/{\$item->id}");

        \$response->assertStatus(200)
            ->assertJsonPath('data.id', \$item->id);
    }

    public function test_update(): void
    {
        \$item = {$this->singularName}::factory()->create();
        \$data = ['name' => 'Updated Name'];

        \$response = \$this->withToken(\$this->token)
            ->putJson("/api/{$routeName}/{\$item->id}", \$data);

        \$response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_delete(): void
    {
        \$item = {$this->singularName}::factory()->create();

        \$response = \$this->withToken(\$this->token)
            ->deleteJson("/api/{$routeName}/{\$item->id}");

        \$response->assertStatus(200);
        \$this->assertSoftDeleted('{$this->tableName}', ['id' => \$item->id]);
    }
}
PHP;
    }

    /**
     * 获取Seeder文件内容
     */
    protected function getSeederContent(): string
    {
        return <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\\{$this->singularName};

class {$this->singularName}Seeder extends Seeder
{
    public function run(): void
    {
        {$this->singularName}::factory()->count(10)->create();
    }
}
PHP;
    }
}
