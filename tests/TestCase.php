<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tránh lỗi "Vite manifest not found" khi chạy test trên máy mới
        // (chưa chạy `npm run build`) — public/build nằm trong .gitignore.
        $this->withoutVite();
    }
}
