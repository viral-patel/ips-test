<?php

namespace Tests\Unit;

use Tests\TestCase;
use PHPUnit\Framework\TestResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use DB;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IpsApiControllerTest extends TestCase
{
    // Initialize Mockery using Trait
    use MockeryPHPUnitIntegration;
    
    // Mock Object
    protected $infusionsoftHelperMock;

    /**
     * Cleanup Test Environment
     * Cleanup Data Entries in Test Database
     */
    public function tearDown() {
        \Mockery::close();
        // Make Sure to set DB_CONNECTION to test DB in phpunit.xml
        // otherwise this will truncate original database
        DB::table('users')->delete();
    }
    
    /**
     * Setup Test Environment
     */
    public function setUp() {
        parent::setUp();
        $this->setRunTestInSeparateProcess(true);
        $this->setPreserveGlobalState(false);
        $this->infusionsoftHelperMock = \Mockery::mock("overload:\App\Http\Helpers\InfusionsoftHelper");
    }

    /**
     * Run cleanup after each test
     * @after
     */
    public function afterEachTest() {
    }

    // Valid User :: Create User :: Not Subscribed Any Course
    public function testModuleReminderTagValidCustomerWithNoCourseShouldFail() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => 20, 'Email' => $user->email, 'Groups'=> null, "_Products" => '']);

        // Bind mock method to app
        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });
        // Call API with Mocked infusersoftHelper as injected dependency
        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        $response
        // Assertions
        ->assertStatus(500)
        ->assertJson([
            'success'=>false,
            'message'=>__('ipsapi.not_bought_any_course')
        ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Not Completed Any
    public function testModuleReminderTagValidCustomerWithNoCompletedModuleShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Get Expected Module for Reminder
        // $expectedReminderModule = \App\Module::where('name','IPA Module 1')->first();
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminder_start',['module_name'=> 'IPA Module 1']))->first()->toArray();
        $infusionsoftMockUserId = 20;
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminder_tag_added')
            ]);
    }
    
    // Valid User :: Create User :: Subscribe Courses :: Completed 1 Module of Course 1 :: Expect 2nd Module
    public function testModuleReminderTagValidCustomerWithOneCompletedModuleShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module 1')->first());
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminder_start',['module_name'=> 'IPA Module 2']))->first()->toArray();

        $infusionsoftMockUserId = 20;
        
        // Mock getContact method        
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminder_tag_added')
            ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Completed 7 Module of Course 1 :: Expect First Module of Next Course
    public function testModuleReminderTagValidCustomerWithCompletedModulesShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module '.$i)->first());
        }
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminders_completed'))->first()->toArray();

        // module_reminders_completed

        $infusionsoftMockUserId = 20;

        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminders_completed')
            ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Completed 7 Module of Course 1, 3 Modules of Course 2 :: Expect Forth Module of Course 2
    public function testModuleReminderTagValidCustomerWithFewCompletedModulesShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module '.$i)->first());
        }
        for($i=1; $i<4;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IEA Module '.$i)->first());
        }
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminder_start',['module_name'=> 'IEA Module 4']))->first()->toArray();

        $infusionsoftMockUserId = 20;
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa,iea']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminder_tag_added')
            ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Completed 7 Module of Course 1 & Course 2 :: Expect First Module of 3rd Course
    public function testModuleReminderTagValidCustomerWithTwoCompletedCoursesShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module '.$i)->first());
        }
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IEA Module '.$i)->first());
        }
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminder_start',['module_name'=> 'IAA Module 1']))->first()->toArray();

        $infusionsoftMockUserId = 20;
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa,iea,iaa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminder_tag_added')
            ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Completed All Modules of All Courses Except One Course :: Expect The Missing Module
    public function testModuleReminderTagValidCustomerWithAllModulesCompletedButOneShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module '.$i)->first());
        }
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IEA Module '.$i)->first());
        }
        for($i=1; $i<4;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IAA Module '.$i)->first());
        }
        for($i=5; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IAA Module '.$i)->first());
        }
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminder_start',['module_name'=> 'IAA Module 4']))->first()->toArray();

        $infusionsoftMockUserId = 20;
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa,iea,iaa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminder_tag_added')
            ]);
    }

    // Valid User :: Create User :: Subscribe Courses :: Completed All Modules of All Courses :: Expect "String"
    public function testModuleReminderTagValidCustomerWithAllCompletedModulesShouldSucceed() {
        // Create Mock User
        $user = factory(\App\User::class)->create();
        // Create Mock User Completed Modules
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IPA Module '.$i)->first());
        }
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IEA Module '.$i)->first());
        }
        for($i=1; $i<8;$i++) {
            $user->completed_modules()->attach(\App\Module::where('name', 'IAA Module '.$i)->first());
        }
        // Get Expected Module for Reminder
        $expectedReminderModule = \App\ModuleReminderTag::where('name', '=', __('ipsapi.module_reminders_completed'))->first()->toArray();

        $infusionsoftMockUserId = 20;
        // Mock getContact method
        $this->infusionsoftHelperMock
            ->shouldReceive('getContact')
            ->with($user->email)
            ->once()
            ->andReturns(['Id' => $infusionsoftMockUserId, 'Email' => $user->email, 'Groups'=> null, "_Products" => 'ipa,iea,iaa']);
        // Mock addTag method
        $this->infusionsoftHelperMock
            ->shouldReceive('addTag')
            ->with($infusionsoftMockUserId, $expectedReminderModule['id'])
            ->once()
            ->andReturns(true);

        // $this->app->instance(InfusionsoftHelper::class,$this->infusionsoftHelperMock);
        $this->app->bind("InfusionsoftHelper::class",function($app){
            return $app->make(InfusionsoftHelper::class);
        });

        $apiUrl = '/api/module_reminder_assigner/'. $user->email;
        $response = $this->get($apiUrl,[$this->infusionsoftHelperMock]);
        
        $response
            ->assertStatus(200)
            ->assertJson([
                'success'=>true,
                'message'=>__('ipsapi.module_reminders_completed')
            ]);
    }
}