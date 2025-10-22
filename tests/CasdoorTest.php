<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the main Casdoor class.
 */
class CasdoorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('add_action')->justReturn(true);
        Functions\when('plugin_dir_path')->justReturn('/fake/path/');
        Functions\when('trailingslashit')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_casdoor_class_exists()
    {
        $this->assertTrue(class_exists('Casdoor'));
    }

    public function test_casdoor_instance_creation()
    {
        require_once dirname(__DIR__) . '/Casdoor.php';
        
        $casdoor = new \Casdoor();
        $this->assertInstanceOf(\Casdoor::class, $casdoor);
    }

    public function test_casdoor_version()
    {
        require_once dirname(__DIR__) . '/Casdoor.php';

        $casdoor = new \Casdoor();

        // Assert plugin version matches canonical plugin header value used in tests
        $this->assertEquals(CASDOOR_PLUGIN_VERSION, $casdoor->version);
    }

    public function test_casdoor_singleton_instance()
    {
        require_once dirname(__DIR__) . '/Casdoor.php';
        
        $instance1 = \Casdoor::instance();
        $instance2 = \Casdoor::instance();
        
        $this->assertSame($instance1, $instance2);
    }

    public function test_casdoor_default_settings()
    {
        require_once dirname(__DIR__) . '/Casdoor.php';
        
        $casdoor = new \Casdoor();
        $reflection = new \ReflectionClass($casdoor);
        $property = $reflection->getProperty('default_settings');
        $property->setAccessible(true);
        $defaults = $property->getValue($casdoor);
        
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('active', $defaults);
        $this->assertArrayHasKey('client_id', $defaults);
        $this->assertArrayHasKey('client_secret', $defaults);
        $this->assertArrayHasKey('backend', $defaults);
        $this->assertArrayHasKey('organization', $defaults);
    }
}
