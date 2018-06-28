<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleWare;
use Illuminate\Foundation\Facade\Bus;

class iPSTestTest extends TestCase
{
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
        $response->assertStatus(200);
    }

    public function testModuleReminderTagInvalidEmail() {
        $response = $this->withHeaders([
                'X-Header'=>'Value',
            ])->json('GET', '/api/module_reminder_assigner/foo');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>false,
            ]);
    }

    public function testModuleReminderTagValidEmail() {
        $response = $this->withHeaders([
                'X-Header'=>'Value',
            ])->json('GET', '/api/module_reminder_assigner/foo@bar.com');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
            ]);
    }


}
