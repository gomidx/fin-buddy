<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function test_fillable_contains_required_fields(): void
    {
        $user = new User();

        $this->assertContains('name', $user->getFillable());
        $this->assertContains('email', $user->getFillable());
        $this->assertContains('password', $user->getFillable());
        $this->assertContains('currency', $user->getFillable());
    }

    public function test_hidden_contains_sensitive_fields(): void
    {
        $user = new User();

        $this->assertContains('password', $user->getHidden());
        $this->assertContains('remember_token', $user->getHidden());
    }

    public function test_password_is_cast_as_hashed(): void
    {
        $user = new User();
        $casts = $user->getCasts();

        $this->assertArrayHasKey('password', $casts);
        $this->assertEquals('hashed', $casts['password']);
    }
}
