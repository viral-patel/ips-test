<?php

namespace App\Http\Controllers;

use App\Http\Helpers\InfusionsoftHelper;
use Illuminate\Http\Request;
use Response;
use App\User;
use App\Module;
use App\ModuleReminderTag;
use Illuminate\Support\Facades\Validator;
use DB;
use Log;

class ApiController extends Controller
{
    /**
     * Checks if input parameter is a valid email
     * @param string $email
     */
    private function validateInputEmail($email) {
        $validator = Validator::make( array( 'email' => $email ), array( 'email' => 'required|email' ));
        if( $validator->fails() ) {
            throw new \Exception("Input is an invalid email.");
        }
    }

    private function getUserDetailsByEmail($email) {
        $infusionsoft = new InfusionsoftHelper();
        $userDetails = $infusionsoft->getContact($email);
        return $userDetails;
    }

    private function validateUserDetails($userDetails) {
        if( $userDetails === false ) {
            throw new \Exception("No user with given email.");
        }
    }

    private function checkIfUserBoughtProducts($userDetails) {
        if( empty($userDetails['_Products']) ) {
            throw new \Exception("User has not bought any course.");
        }
    }

    private function getCoursesByUserDetails($userDetails) {
        return explode(',',$userDetails['_Products']);
    }

    private function getUserDetailsByEmailFromDatabase($email) {
        return User::where('email', '=', $email)->get()[0];
    }
            
    private function getTagDetailsByTagName($tagName) {
        return ModuleReminderTag::where('name', '=', $tagName)->get()[0]->toArray();
    }

    /**
     * Add Module Reminder Tag to InfusionSoft
     * 
     */
    private function saveTagIdForUser($userId,$tagId) {
        $infusionsoft = new InfusionsoftHelper();
        $userAddTagResult = $infusionsoft->addTag($userId,$tagId);
        if( $userAddTagResult === false ) {
            throw new \Exception("Error saving tag id for user.");
        }
    }

    /**
     * Add a module reminder tag to InfusionSoft
     * Outputs json
     * {
     *      'success' : true/false,
     *      'message' : "success/error message"
     * }
     * 
     * @param string $email
     * @return void
     */
    public function moduleReminderAssigner($email) {

        try {
            //Variable Initialization
            $success = true;
            $message = 'Module reminder tag added.';
            $reminderModuleName = ''; 
            
            // Check for valid email
            $this->validateInputEmail($email);

            // Check if we have a user with input email
            $userDetails = $this->getUserDetailsByEmail($email);

            $this->validateUserDetails($userDetails);

            // Check user has bought any course
            $this->checkIfUserBoughtProducts($userDetails);

            // Get courses bought by user in order of buy date
            $userCourses = $this->getCoursesByUserDetails($userDetails);

            // Check DB for user's completed modules

            // Get User by Email
            $userData = $this->getUserDetailsByEmailFromDatabase($email);

            // Find Next Incomplete Module For Reminder
            foreach($userCourses as $currentCourse) {
                $firstModuleToTag = DB::select("SELECT course_key, name FROM modules WHERE course_key = '{$currentCourse}' and 
                                                id NOT IN (
                                                    SELECT module_id  FROM user_completed_modules WHERE user_id = {$userData->id}
                                                    )
                                                ORDER BY name ASC LIMIT 1");
                if( count($firstModuleToTag) > 0 ) {
                    $reminderModuleName = $firstModuleToTag[0]->name;
                    break;
                }
            }

            // Build Module Reminder Tag
            if(strlen($reminderModuleName) == 0 ) {
                $moduleReminderTag = "Module reminders completed";
            } else {
                $moduleReminderTag = "Start {$reminderModuleName} Reminders";
            }

            // Get TagId to Save
            $tagDetails = $this->getTagDetailsByTagName($moduleReminderTag);

            // Add Module Reminder Tag for User to InfusionSoft
            $this->saveTagIdForUser($userDetails['Id'],$tagDetails['id']);

            // Log Output Data
            $logString = $userDetails['Id'] ."," . $email . "," . $tagDetails['id'] . "," . $moduleReminderTag; 
            Log::info($logString);

            // Return Success
            $success = true;
            $message = "Module reminder tag added.";
        } catch( \Exception $e ) {
            $success = false;
            $message = $e->getMessage();
            // Log Error Data
            $logString = $email . "," . $message; 
            Log::error($logString);
        }
        $returnArr['success'] = $success;
        $returnArr['message'] = $message;

        return Response::json($returnArr);
    }

    /**
     * Change this to publich when required to generate dummy user and completed modules entries
     */
    private function exampleCustomer(){

        $infusionsoft = new InfusionsoftHelper();

        $uniqid = uniqid();

        $infusionsoft->createContact([
            'Email' => $uniqid.'@test.com',
            "_Products" => 'iea,ipa'
        ]);

        $user = User::create([
            'name' => 'Test ' . $uniqid,
            'email' => $uniqid.'@test.com',
            'password' => bcrypt($uniqid)
        ]);

        // attach IPA M1-3 & M5
        $user->completed_modules()->attach(Module::where('course_key', 'iea')->limit(4)->get());
        $user->completed_modules()->attach(Module::where('name', 'IEA Module 7')->first());

        return $user;
    }
}
