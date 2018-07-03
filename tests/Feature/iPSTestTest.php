<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleWare;
use Illuminate\Foundation\Facades\Bus;
use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use \Mockery;

class iPSTestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public $infusionsoftHelperMock;

    public function tearDown() {
        \Mockery::close();
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testSiteRootTest() {
        $response = $this->get('/');
        $response->assertRedirect('/home');
    }

    public function testSiteHomeTest()
    {
        $response = $this->get('/home');
        $response->assertRedirect('/login');
    }
    
    // Invalid Email
    public function testModuleReminderTagInvalidEmailShouldFail() {
        $response = $this->json('GET', '/api/module_reminder_assigner/foo');

        $response
            ->assertStatus(500)
            ->assertJson([
                'success'=>false,
                'message'=> __('ipsapi.invalid_email')
            ]);
    }
    
    // Valid Email, Invalid User
    public function testModuleReminderTagValidEmailOfInvalidCustomerShouldFail() {
        $response = $this->json('GET', '/api/module_reminder_assigner/foo@bar.com');

        $response
            ->assertStatus(500)
            ->assertJson([
                'success'=>false,
                'message'=>__('ipsapi.invalid_user_email')
            ]);
    }
}
