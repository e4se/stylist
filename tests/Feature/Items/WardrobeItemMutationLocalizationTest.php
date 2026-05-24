<?php

namespace Tests\Feature\Items;

use JsonException;
use Tests\TestCase;

class WardrobeItemMutationLocalizationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_wardrobe_item_mutation_dialog_copy_is_localized(): void
    {
        $keys = [
            'Add item',
            'Add item details and an optional main image.',
            'Choose image',
            'Create item',
            'Create wardrobe item',
            'Creating...',
            'Description',
            'Edit wardrobe item',
            'Image ready',
            'Image will be attached after upload.',
            'Main image',
            'Main image upload progress',
            'Notes about fit, material, or styling',
            'Optional image up to 10 MB.',
            'Replace image',
            'Saving...',
            'Update item details or replace the main image.',
            'Uploading image',
            'e.g., Black linen jacket',
        ];

        foreach (['en', 'ru'] as $locale) {
            $translations = $this->translationsFor($locale);

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translations);
                $this->assertNotSame('', trim($translations[$key]));
            }
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function translationsFor(string $locale): array
    {
        $path = lang_path("{$locale}.json");
        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->fail("Unable to read translations from {$path}.");
        }

        $translations = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($translations);

        /** @var array<string, string> $translations */
        return $translations;
    }
}
