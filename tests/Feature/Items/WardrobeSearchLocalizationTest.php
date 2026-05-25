<?php

namespace Tests\Feature\Items;

use JsonException;
use Tests\TestCase;

class WardrobeSearchLocalizationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_wardrobe_search_ui_copy_is_localized(): void
    {
        $keys = [
            'Clear search',
            'Item name or description',
            'No wardrobe items match your search or filters',
            'Search items',
            'Search wardrobe',
            'Try adjusting your search or clearing one or more filters.',
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
