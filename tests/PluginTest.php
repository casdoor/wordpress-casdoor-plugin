<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the main class and the settings page.
 */
class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/class-casdoor-plugin.php';
        require_once dirname(__DIR__) . '/includes/class-casdoor-admin.php';
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_singleton_registers_hooks_once()
    {
        $instance1 = \Casdoor_Plugin::instance();
        $instance2 = \Casdoor_Plugin::instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_activate_keeps_existing_settings()
    {
        // add_option() does nothing when the option exists, so the settings of an earlier
        // installation are kept.
        Functions\expect('add_option')->once()->with('casdoor_options', casdoor_default_options());
        Functions\expect('flush_rewrite_rules')->once();

        \Casdoor_Plugin::activate();
        $this->addToAssertionCount(\Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function test_validate_sanitizes_the_settings()
    {
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('untrailingslashit')->alias(function ($url) {
            return rtrim($url, '/');
        });

        $output = \Casdoor_Admin::validate([
            'active'       => '1',
            'client_id'    => ' id ',
            'backend'      => ' https://door.example.com/ ',
            'organization' => 'acme',
            'unknown'      => 'dropped',
        ]);

        $this->assertSame(1, $output['active']);
        $this->assertSame(0, $output['auto_sso']);
        $this->assertSame('id', $output['client_id']);
        $this->assertSame('', $output['client_secret']);
        $this->assertSame('https://door.example.com', $output['backend']);
        $this->assertSame('acme', $output['organization']);
        $this->assertArrayNotHasKey('unknown', $output);

        $this->assertSame(0, \Casdoor_Admin::validate(null)['active']);
    }
}
