<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\bulkservices\Admin\AdminDispatcher;
use WHMCS\Module\Addon\bulkservices\Client\ClientDispatcher;

// php composer.phar update
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function bulkservices_config()
{
    return [
        'name' => 'Bulk Services',
        'description' => 'This is a module for bulk processing services in WHMCS',
        'author' => 'Fajar Riza Fauzi',
        'language' => 'english',
        'version' => '1.0',

    ];
}

function bulkservices_activate()
{
    // Create custom tables and schema required by your module
    try {

        if (Capsule::schema()->hasTable('bulkservices_log') === false) {
			Capsule::schema()->create('bulkservices_log', function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->increments('id');
                $table->integer('userid')->nullable();
                $table->integer('hostingid')->nullable();
                $table->string('proses')->nullable();
                $table->string('plan')->nullable();
				$table->string('domain')->nullable();
				$table->string('status')->nullable();
                $table->text('response')->nullable();
				$table->timestamp('created_at')->nullable();
			});
        }
        
        if (Capsule::schema()->hasTable('bulkservices_report') === false) {
			Capsule::schema()->create('bulkservices_report', function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->increments('id');
                $table->string('docname')->nullable();
                $table->integer('userid')->nullable();
                $table->string('proses')->nullable();
                $table->string('failed')->nullable();
                $table->string('success')->nullable();
				$table->timestamp('created_at')->nullable();
			});
		}

        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'bulkservices module is installed successfully.',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            'status' => "error",
            'description' => 'Unable to create bulkservices: ' . $e->getMessage(),
        ];
    }
}


function bulkservices_deactivate()
{
    // Undo any database and schema modifications made by your module here
    try {
        return [
            // Supported values here include: success, error or info
            'status' => 'success',
            'description' => 'This is a demo module only. '
                . 'In a real module you might report a success here.',
        ];
    } catch (\Exception $e) {
        return [
            // Supported values here include: success, error or info
            "status" => "error",
            "description" => "Unable to drop mod_addonexample: {$e->getMessage()}",
        ];
    }
}


function bulkservices_output($vars)
{
    $modulelink = $vars['modulelink']; 
    $version = $vars['version'];
    $_lang = $vars['_lang'];

    $url = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->first();
    $system_url = $url->value;

    $dispatcher = new AdminDispatcher();
    $report = Capsule::table('bulkservices_report')->orderBy('id', 'desc')->get();

    $action1 = (isset($_REQUEST['action']) === true && $_REQUEST['action'] === 'bulk') ? true : false;
    $action2 = (isset($_REQUEST['action']) === true && $_REQUEST['action'] === 'exec') ? true : false;

    if ($action1 === true) {
		$params = [
            'proses' => $_POST['proses'],
            'url' => $system_url
		];
        $dispatch = $dispatcher->dispatch('bulkservices_process', $params);
        header('Content-Type: application/json');
        echo json_encode($dispatch);
        die();
    }

    if ($action2 === true) {
		$params = [
			'proses' => $_POST['proses']
		];
        $dispatch = $dispatcher->dispatch('bulkservices_exec', $params);
        header('Content-Type: text/html');
        echo $dispatch;
        die();
    }



    include 'templates/admin.php';
}



