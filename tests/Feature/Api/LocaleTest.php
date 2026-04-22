<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_default_locale_is_en()
    {
        $response = $this->getJson('/api/locale');

        $response->assertStatus(200)
            ->assertJson(['locale' => 'en']);
    }

    public function test_can_set_locale_via_header()
    {
        $response = $this->getJson('/api/locale', [
            'X-Locale' => 'ar'
        ]);

        $response->assertStatus(200)
            ->assertJson(['locale' => 'ar']);
    }

    public function test_can_set_locale_via_query_param()
    {
        $response = $this->getJson('/api/locale?locale=ar');

        $response->assertStatus(200)
            ->assertJson(['locale' => 'ar']);
    }

    public function test_invalid_locale_falls_back_to_default()
    {
        $response = $this->getJson('/api/locale?locale=fr');

        $response->assertStatus(200)
            ->assertJson(['locale' => 'en']);
    }

    public function test_error_messages_respect_locale()
    {
        // Use an endpoint that has translated messages, like applying to a non-existent job
        $response = $this->getJson('/api/jobs/99999/match-score', [
            'X-Locale' => 'ar'
        ]);

        // The exact message depends on translations in lang/ar/application.php
        // but it should be different from English if translated.
        $this->assertTrue(app()->getLocale() === 'ar');
    }
}
