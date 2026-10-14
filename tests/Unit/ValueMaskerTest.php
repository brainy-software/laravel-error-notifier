<?php

namespace Brainy\ErrorNotifier\Tests\Unit;

use Brainy\ErrorNotifier\Tests\TestCase;
use Brainy\ErrorNotifier\ValueMasker;

class ValueMaskerTest extends TestCase
{
    public function test_it_masks_sensitive_keys_recursively(): void
    {
        $masker = new ValueMasker([
            'password',
            'token',
            'secret',
            'credit_card',
        ]);

        $payload = [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'password' => 'supersecret123',
            'nested' => [
                'user_token' => 'xyz-auth-token',
                'description' => 'Normal text',
                'deep' => [
                    'app_secret' => '999888777',
                    'normal_key' => 12345,
                ],
            ],
            'items' => [
                ['credit_card_number' => '4111222233334444'],
            ],
        ];

        $masked = $masker->mask($payload);

        $this->assertEquals('john_doe', $masked['username']);
        $this->assertEquals('john@example.com', $masked['email']);
        $this->assertEquals('********', $masked['password']);
        $this->assertEquals('********', $masked['nested']['user_token']);
        $this->assertEquals('Normal text', $masked['nested']['description']);
        $this->assertEquals('********', $masked['nested']['deep']['app_secret']);
        $this->assertEquals(12345, $masked['nested']['deep']['normal_key']);
        $this->assertEquals('********', $masked['items'][0]['credit_card_number']);
    }
}
