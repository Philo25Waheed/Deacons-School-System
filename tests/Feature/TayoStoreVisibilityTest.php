<?php

namespace Tests\Feature;

use Tests\TestCase;

class TayoStoreVisibilityTest extends TestCase
{
    public function test_tayo_store_visibility_toggle_and_helpers(): void
    {
        require_once __DIR__.'/../../config/database.php';
        require_once __DIR__.'/../../includes/helpers.php';

        $db = getDB();

        // 1. Initially or by default, ensure can enable store
        $this->assertTrue(set_store_enabled(true, $db));
        $this->assertTrue(is_store_enabled($db));

        // 2. Disable store (Admin hides store)
        $this->assertTrue(set_store_enabled(false, $db));
        $this->assertFalse(is_store_enabled($db));

        // 3. Re-enable store (Admin shows store)
        $this->assertTrue(set_store_enabled(true, $db));
        $this->assertTrue(is_store_enabled($db));
    }
}
