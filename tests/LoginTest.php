<?php

namespace Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the login flow helpers.
 */
class LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('is_ssl')->justReturn(true);
        Functions\when('setcookie')->justReturn(true);
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();

        if (!defined('MINUTE_IN_SECONDS')) {
            define('MINUTE_IN_SECONDS', 60);
        }
        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }

        require_once dirname(__DIR__) . '/includes/functions.php';
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    private function token(array $payload): string
    {
        $encode = function (array $data) {
            return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        };
        return $encode(['alg' => 'RS256']) . '.' . $encode($payload) . '.signature';
    }

    public function test_create_state_stores_redirect()
    {
        Functions\expect('wp_generate_password')->once()->with(32, false, false)->andReturn('abcDEF123');
        Functions\expect('set_transient')->once()->with('casdoor_state_abcDEF123', 'http://example.com/page', 600);

        $this->assertSame('abcDEF123', casdoor_create_state('http://example.com/page'));
    }

    public function test_consume_state_returns_redirect_once()
    {
        $_COOKIE['casdoor_state'] = 'abcDEF123';
        Functions\expect('get_transient')->once()->with('casdoor_state_abcDEF123')->andReturn('http://example.com/page');
        Functions\expect('delete_transient')->once()->with('casdoor_state_abcDEF123');

        $this->assertSame('http://example.com/page', casdoor_consume_state('abcDEF123'));
    }

    public function test_consume_state_rejects_state_of_another_browser()
    {
        Functions\expect('get_transient')->never();

        // No cookie: the login was started in another browser
        $this->assertNull(casdoor_consume_state('abcDEF123'));

        $_COOKIE['casdoor_state'] = 'otherState';
        $this->assertNull(casdoor_consume_state('abcDEF123'));

        $_COOKIE['casdoor_state'] = '';
        $this->assertNull(casdoor_consume_state(''));
    }

    public function test_consume_state_rejects_expired_state()
    {
        $_COOKIE['casdoor_state'] = 'abcDEF123';
        Functions\expect('get_transient')->once()->andReturn(false);
        Functions\expect('delete_transient')->once();

        $this->assertNull(casdoor_consume_state('abcDEF123'));
    }

    public function test_token_subject()
    {
        $this->assertSame('built-in/admin', casdoor_token_subject($this->token(['owner' => 'built-in', 'name' => 'admin'])));
        $this->assertSame('', casdoor_token_subject('not-a-token'));
        $this->assertSame('', casdoor_token_subject($this->token(['sub' => 'x'])));
    }

    public function test_account_id()
    {
        $this->assertSame('uuid-1', casdoor_account_id((object) ['id' => 'uuid-1', 'owner' => 'org', 'name' => 'alice']));
        $this->assertSame('org/alice', casdoor_account_id((object) ['owner' => 'org', 'name' => 'alice']));
    }

    public function test_find_user_by_link()
    {
        $user = (object) ['ID' => 7];
        Functions\expect('get_users')->once()->with([
            'meta_key'   => 'casdoor_user_id',
            'meta_value' => 'uuid-1',
            'number'     => 1,
        ])->andReturn([$user]);

        $this->assertSame($user, casdoor_find_user((object) ['id' => 'uuid-1', 'owner' => 'org', 'name' => 'alice']));
    }

    public function test_find_user_does_not_match_login_name_alone()
    {
        $admin = (object) ['ID' => 1];
        Functions\when('get_users')->justReturn([]);
        Functions\when('get_user_by')->alias(function ($field) use ($admin) {
            return $field === 'login' ? $admin : false;
        });
        // The wordpress admin never logged in with casdoor
        Functions\when('get_user_meta')->justReturn('');

        $this->assertNull(casdoor_find_user((object) ['id' => 'uuid-2', 'owner' => 'other-org', 'name' => 'admin', 'email' => 'x@example.com', 'emailVerified' => false]));
    }

    public function test_find_user_by_login_name_of_previous_casdoor_login()
    {
        $user = (object) ['ID' => 3];
        Functions\when('get_users')->justReturn([]);
        Functions\when('get_user_by')->alias(function ($field) use ($user) {
            return $field === 'login' ? $user : false;
        });
        Functions\when('get_user_meta')->justReturn($this->token(['owner' => 'org', 'name' => 'alice']));

        $this->assertSame($user, casdoor_find_user((object) ['id' => 'uuid-1', 'owner' => 'org', 'name' => 'alice']));
        // The same name in another organization is another user
        $this->assertNull(casdoor_find_user((object) ['id' => 'uuid-3', 'owner' => 'other-org', 'name' => 'alice']));
    }

    public function test_find_user_by_verified_email_only()
    {
        $user = (object) ['ID' => 5];
        Functions\when('get_users')->justReturn([]);
        Functions\when('get_user_by')->alias(function ($field) use ($user) {
            return $field === 'email' ? $user : false;
        });

        $this->assertSame($user, casdoor_find_user((object) ['owner' => 'org', 'name' => 'bob', 'email' => 'bob@example.com', 'emailVerified' => true]));
        $this->assertNull(casdoor_find_user((object) ['owner' => 'org', 'name' => 'bob', 'email' => 'bob@example.com', 'emailVerified' => false]));
    }

    public function test_unique_login()
    {
        Functions\when('sanitize_user')->returnArg();
        Functions\when('username_exists')->alias(function ($login) {
            return in_array($login, ['admin', 'admin-2'], true) ? 1 : false;
        });

        $this->assertSame('alice', casdoor_unique_login('alice'));
        $this->assertSame('admin-3', casdoor_unique_login('admin'));
    }

    public function test_create_user_refuses_unverified_email_of_another_user()
    {
        Functions\when('get_option')->justReturn(['organization' => 'org']);
        Functions\expect('email_exists')->once()->with('admin@example.com')->andReturn(1);
        Functions\expect('wp_insert_user')->never();

        $result = casdoor_create_user((object) ['owner' => 'org', 'name' => 'eve', 'email' => 'admin@example.com', 'emailVerified' => false]);
        $this->assertSame('casdoor_email_conflict', $result->get_error_code());
    }

    public function test_create_user_makes_admins_of_the_organization_administrators()
    {
        Functions\when('get_option')->justReturn(['organization' => 'org']);
        Functions\when('email_exists')->justReturn(false);
        Functions\when('sanitize_user')->returnArg();
        Functions\when('username_exists')->justReturn(false);
        Functions\when('wp_generate_password')->justReturn('password');
        Functions\when('get_user_by')->justReturn((object) ['ID' => 9]);
        Functions\when('is_wp_error')->justReturn(false);

        $inserted = [];
        Functions\when('wp_insert_user')->alias(function ($data) use (&$inserted) {
            $inserted[] = $data;
            return 9;
        });

        casdoor_create_user((object) ['owner' => 'org', 'name' => 'root', 'email' => '', 'isAdmin' => true]);
        casdoor_create_user((object) ['owner' => 'other-org', 'name' => 'root2', 'email' => '', 'isAdmin' => true]);

        $this->assertSame('administrator', $inserted[0]['role']);
        $this->assertArrayNotHasKey('role', $inserted[1]);
    }
}
