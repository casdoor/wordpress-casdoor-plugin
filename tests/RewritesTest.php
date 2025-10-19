<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Rewrites class.
 */
class RewritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('add_filter')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_rewrites_class_exists()
    {
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/admin-options.php';
        
        $this->assertTrue(class_exists('Rewrites'));
    }

    public function test_create_rewrite_rules_adds_new_rule()
    {
        Functions\when('add_shortcode')->justReturn(true);
        
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/admin-options.php';
        require_once dirname(__DIR__) . '/includes/Rewrites.php';
        
        // Mock global $wp_rewrite with a proper mock object
        global $wp_rewrite;
        $wp_rewrite = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['preg_index'])
            ->getMock();
        $wp_rewrite->method('preg_index')->willReturnArgument(0);
        
        $rewrites = new \Rewrites();
        $existingRules = ['existing/rule' => 'index.php?existing=1'];
        
        $newRules = $rewrites->create_rewrite_rules($existingRules);
        
        $this->assertIsArray($newRules);
        $this->assertArrayHasKey('existing/rule', $newRules);
        $this->assertCount(2, $newRules); // Should have original + new rule
    }

    public function test_add_query_vars_adds_auth_vars()
    {
        Functions\when('add_shortcode')->justReturn(true);
        
        require_once dirname(__DIR__) . '/includes/functions.php';
        require_once dirname(__DIR__) . '/includes/admin-options.php';
        require_once dirname(__DIR__) . '/includes/Rewrites.php';
        
        $rewrites = new \Rewrites();
        $existingVars = ['existing_var'];
        
        $newVars = $rewrites->add_query_vars($existingVars);
        
        $this->assertIsArray($newVars);
        $this->assertContains('auth', $newVars);
        $this->assertContains('code', $newVars);
        $this->assertContains('message', $newVars);
        $this->assertContains('existing_var', $newVars);
    }
}
