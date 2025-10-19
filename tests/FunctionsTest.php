<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for helper functions.
 */
class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_function_returns_array()
    {
        // Mock add_shortcode function
        Functions\when('add_shortcode')->justReturn(true);
        
        require_once dirname(__DIR__) . '/includes/functions.php';
        
        $defaults = defaults();
        
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('client_id', $defaults);
        $this->assertArrayHasKey('client_secret', $defaults);
        $this->assertArrayHasKey('backend', $defaults);
        $this->assertArrayHasKey('redirect_to_dashboard', $defaults);
        $this->assertArrayHasKey('login_only', $defaults);
    }

    public function test_casdoor_get_option_with_mocked_wordpress()
    {
        require_once dirname(__DIR__) . '/includes/admin-options.php';
        require_once dirname(__DIR__) . '/includes/functions.php';
        
        // Mock get_option to return test data
        Functions\expect('get_option')
            ->once()
            ->with('casdoor_options', [])
            ->andReturn([
                'client_id' => 'test_client_id',
                'client_secret' => 'test_secret',
                'backend' => 'http://localhost:8000'
            ]);
        
        $client_id = casdoor_get_option('client_id');
        
        $this->assertEquals('test_client_id', $client_id);
    }

    public function test_get_casdoor_login_url()
    {
        require_once dirname(__DIR__) . '/includes/admin-options.php';
        require_once dirname(__DIR__) . '/includes/functions.php';
        
        // Mock WordPress functions
        Functions\expect('get_option')
            ->with('casdoor_options', [])
            ->andReturn([
                'client_id' => 'test_client',
                'client_secret' => 'test_secret',
                'backend' => 'http://localhost:8000'
            ]);
        
        Functions\expect('site_url')
            ->once()
            ->with('?auth=casdoor')
            ->andReturn('http://example.com/?auth=casdoor');
        
        $url = get_casdoor_login_url();
        
        $this->assertIsString($url);
        $this->assertStringContainsString('http://localhost:8000/login/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=test_client', $url);
    }
}
