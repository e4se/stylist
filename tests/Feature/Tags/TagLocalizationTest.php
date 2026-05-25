<?php

namespace Tests\Feature\Tags;

use JsonException;
use Tests\TestCase;

class TagLocalizationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_wardrobe_tag_ui_copy_is_localized(): void
    {
        $keys = [
            ':count selected',
            'Clear filters',
            'Filter by tags',
            'Loading wardrobe items',
            'No tag groups yet',
            'No tags in this group yet',
            'No wardrobe items match these filters',
            'Tags',
            'Try removing one or more tag filters.',
            'Wardrobe',
        ];

        foreach (['en', 'ru'] as $locale) {
            $translations = $this->jsonTranslationsFor($locale);

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translations);
                $this->assertNotSame('', trim($translations[$key]));
            }
        }
    }

    public function test_tag_validation_attributes_are_localized(): void
    {
        $expectedAttributes = [
            'tag_group_id',
            'tag_group_name',
            'tag_ids',
            'tag_ids.*',
            'tag_name',
        ];

        foreach (['en', 'ru'] as $locale) {
            $attributes = trans('validation.attributes', [], $locale);

            $this->assertIsArray($attributes);

            foreach ($expectedAttributes as $attribute) {
                $this->assertArrayHasKey($attribute, $attributes);
                $this->assertIsString($attributes[$attribute]);
                $this->assertNotSame('', trim($attributes[$attribute]));
            }
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function jsonTranslationsFor(string $locale): array
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
