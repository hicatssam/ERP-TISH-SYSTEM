<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        $branch = Location::create(['name' => 'Image branch', 'code' => 'IMG-BR',
            'type' => 'branch', 'is_active' => true]);
        $user = User::factory()->create();
        $user->employee->locations()->attach($branch->id, ['is_primary' => true]);
        foreach (['products.view', 'products.create', 'products.update'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
    }

    public function test_oversized_image_error_is_visible_in_creation_page_and_modal(): void
    {
        foreach (['products.create', 'products.index'] as $route) {
            $this->from(route($route))->post(route('products.store'), array_merge($this->payload(), [
                'image' => UploadedFile::fake()->image('large.jpg')->size(2049),
            ]))->assertRedirect(route($route))->assertSessionHasErrors('image');
            $this->get(route($route))->assertOk()
                ->assertSee('حجم صورة المنتج يجب ألا يتجاوز 2 ميجابايت.')
                ->assertSee('data-product-errors', false)->assertSee('data-product-image-error', false);
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_image_error_is_visible_and_edit_preserves_old_data_and_image(): void
    {
        $product = $this->createProduct();
        $this->from(route('products.edit', $product))
            ->put(route('products.update', $product), array_merge($this->payload(), [
                'name' => 'Attempted edit',
                'image' => UploadedFile::fake()->create('not-image.pdf', 10, 'application/pdf'),
            ]))->assertRedirect(route('products.edit', $product))->assertSessionHasErrors('image');
        $this->get(route('products.edit', $product))->assertOk()
            ->assertSee('الملف المحدد يجب أن يكون صورة.')
            ->assertSee('Attempted edit')->assertSee('aria-invalid="true"', false);
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertSame('products/old.jpg', $product->fresh()->image);
        Storage::disk('public')->assertExists('products/old.jpg');
    }

    public function test_php_upload_failure_has_an_arabic_image_error(): void
    {
        $file = new UploadedFile(__FILE__, 'failed.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
        $this->from(route('products.create'))->post(route('products.store'),
            array_merge($this->payload(), ['image' => $file]))
            ->assertSessionHasErrors(['image' => 'تعذر رفع صورة المنتج. أعد اختيار صورة بحجم لا يتجاوز 2 ميجابايت وحاول مجدداً.']);
        $this->get(route('products.create'))->assertOk()->assertSee('تعذر رفع صورة المنتج.');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_storage_returning_false_does_not_silently_create_product(): void
    {
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->once()->andReturn(false);
        $this->from(route('products.create'))->post(route('products.store'), array_merge($this->payload(), [
            'image' => UploadedFile::fake()->image('cake.jpg'),
        ]))->assertSessionHasErrors('image');
        $this->get(route('products.create'))->assertOk()->assertSee('تعذر حفظ صورة المنتج على الخادم.');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_storage_exception_during_replacement_preserves_old_image_and_product(): void
    {
        $product = $this->createProduct();
        $disk = Storage::disk('public');
        Storage::shouldReceive('disk')->with('public')->andReturnSelf();
        Storage::shouldReceive('putFileAs')->once()
            ->andThrow(UnableToWriteFile::atLocation('products/new.jpg', 'Write failed'));
        $this->from(route('products.edit', $product))->put(route('products.update', $product),
            array_merge($this->payload(), ['name' => 'Attempted edit',
                'image' => UploadedFile::fake()->image('new.jpg')]))
            ->assertSessionHasErrors('image');
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertSame('products/old.jpg', $product->fresh()->image);
        $disk->assertExists('products/old.jpg');
    }

    public function test_successful_replacement_removes_old_file_only_after_saving_new_image(): void
    {
        $product = $this->createProduct();
        $this->put(route('products.update', $product), array_merge($this->payload(), [
            'image' => UploadedFile::fake()->image('new.png'),
        ]))->assertSessionHasNoErrors();
        $newImage = $product->fresh()->image;
        $this->assertNotSame('products/old.jpg', $newImage);
        Storage::disk('public')->assertExists($newImage);
        Storage::disk('public')->assertMissing('products/old.jpg');
    }

    public function test_successful_image_creation_and_explicit_removal_work(): void
    {
        $this->post(route('products.store'), array_merge($this->payload(), [
            'image' => UploadedFile::fake()->image('cake.png'),
        ]))->assertSessionHasNoErrors();
        $product = Product::query()->sole();
        $path = $product->image;
        Storage::disk('public')->assertExists($path);
        $this->put(route('products.update', $product), array_merge($this->payload(), [
            'remove_image' => 1,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_post_size_limit_returns_a_readable_product_error_for_html_and_json(): void
    {
        $this->withServerVariables(['CONTENT_LENGTH' => (string) PHP_INT_MAX])
            ->post(route('products.store'))->assertStatus(413)
            ->assertSee('تعذر رفع صورة المنتج')->assertSee('لم يتم حفظ المنتج');
        $this->postJson(route('products.store'))->assertStatus(413)
            ->assertJsonValidationErrors('image');
    }

    public function test_other_validation_errors_are_rendered_with_previous_input(): void
    {
        $this->from(route('products.create'))->post(route('products.store'),
            array_merge($this->payload(), ['base_selling_price' => -1]))
            ->assertSessionHasErrors('base_selling_price');
        $this->get(route('products.create'))->assertOk()
            ->assertSee('السعر الأساسي لا يمكن أن يكون أقل من صفر.')->assertSee('Cake image test');
    }

    private function payload(): array
    {
        $category = Category::firstOrCreate(['slug' => 'image-test'], [
            'name' => 'Image test', 'is_active' => true,
        ]);
        return ['name' => 'Cake image test', 'category_id' => $category->id,
            'unit_id' => Unit::where('code', 'piece')->firstOrFail()->id,
            'product_type' => 'standard', 'base_selling_price' => 10];
    }

    private function createProduct(): Product
    {
        $this->post(route('products.store'), $this->payload())->assertSessionHasNoErrors();
        $product = Product::query()->sole();
        Storage::disk('public')->put('products/old.jpg', 'old image');
        $product->update(['name' => 'Original', 'image' => 'products/old.jpg']);
        return $product;
    }
}
