<?php

namespace Tests\Feature\Items;

use App\Enums\ItemUploadType;
use App\Models\Item;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_main_upload_can_be_attached_to_an_item_and_resolved_from_both_sides(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->for($user)->create();
        $upload = Upload::factory()->for($user)->create();

        $item->mainUpload()->attach($upload);

        $itemUpload = $item->uploads()->sole();
        $itemMainUpload = $item->mainUpload()->sole();
        $uploadItem = $upload->items()->sole();

        $this->assertTrue($itemUpload->is($upload));
        $this->assertMainUploadPivot($itemUpload);
        $this->assertTrue($itemMainUpload->is($upload));
        $this->assertMainUploadPivot($itemMainUpload);
        $this->assertTrue($uploadItem->is($item));
        $this->assertMainUploadPivot($uploadItem);
    }

    private function assertMainUploadPivot(Model $model): void
    {
        $pivot = $model->getRelation('pivot');

        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertSame(ItemUploadType::Main->value, $pivot->getAttribute('type'));
    }
}
