<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Casdoor_Rewrites class.
 */
class RewritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/class-casdoor-rewrites.php';
    }

    protected function tearDown(): void
    {
        $_GET = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_init_does_not_flush_rewrite_rules()
    {
        Functions\expect('flush_rewrite_rules')->never();
        Functions\when('add_filter')->justReturn(true);
        Functions\when('add_action')->justReturn(true);

        \Casdoor_Rewrites::init();
        $this->assertTrue(true);
    }

    public function test_create_rewrite_rules_adds_new_rule()
    {
        global $wp_rewrite;
        $wp_rewrite = new class {
            public function preg_index($n)
            {
                return '$matches[' . $n . ']';
            }
        };

        $rules = \Casdoor_Rewrites::create_rewrite_rules(['existing' => 'rule']);

        $this->assertSame(['auth/(.+)' => 'index.php?auth=$matches[1]', 'existing' => 'rule'], $rules);
    }

    public function test_add_query_vars_adds_only_auth()
    {
        // "code" and "message" are too generic, other plugins use them too.
        $this->assertSame(['p', 'auth'], \Casdoor_Rewrites::add_query_vars(['p']));
    }

    public function test_message()
    {
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_key')->alias('strtolower');

        $this->assertSame('', \Casdoor_Rewrites::message());

        $_GET['casdoor_message'] = 'casdoor_invalid_state';
        $this->assertSame('casdoor_invalid_state', \Casdoor_Rewrites::message());
    }
}
