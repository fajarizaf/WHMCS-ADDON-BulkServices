<?php

namespace WHMCS\Module\Addon\bulkservices\Admin;
use WHMCS\Database\Capsule as DB;

use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class Controller {

    public function bulkservices_process($vars) {

        $status = false;
        $message = null;
        $proses = $vars['proses'];
        $services = null;
        $total = null;
        
        try {
            if($proses != '') {

                $allowed = array("xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                $filename = $_FILES["file"]["name"];

                // Verify file extension
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if(array_key_exists($ext, $allowed)) {

                        //proses parsed
                        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
                        $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
                        $data = $spreadsheet->getActiveSheet()->toArray();

                        $invoiceItem_field = $data[0][12];
                        $domain_field = $data[0][1];
                        $invoice_field = $data[0][6];
                        $client_field = $data[0][0];

                        //upload Spreedsheet
                        $terupload = $this->uploadDocSpreedsheet($filename, $_FILES['file']['tmp_name']);

                        if($terupload['status'] != false) {

                            if($client_field == 'Client #') {

                                if($invoice_field == 'Invoice #') {

                                    if($invoiceItem_field == 'Invoice Item') {
                                        
                                        if($domain_field == 'Domain') {

                                            $start = 1;
                                            $status = 'true';
                                            $message = 'Data xlsx is valid, the execution process is running';
                                            for ($i = $start, $size = count($data); $i < $size; $i++) {
                                                if(!empty($data[$i][1])) {
                                                    $ii = $i +1;
                                                    $item     = explode('-', $data[$i][12]);
                                                    $domain   = $data[$i][1];
                                                    $invoice  = $data[$i][6];
                                                    $clientid = $data[$i][0];
                                                    $plan     = $item[0];
                                                    $plot     = 'AB'.$ii;

                                                    $services[] = array (
                                                        'position' => $plot,
                                                        'doc' => $terupload['docname'], 
                                                        'plan' => $plan,
                                                        'domain' => $domain,
                                                        'invoice'=> $invoice,
                                                        'clientid'=> $clientid,
                                                        'proses' => $proses,
                                                        'url' => $vars['url']
                                                    );
                                                }
                                            }

                                        } else {
                                            $status = 'false';
                                            $message = 'Kolom format tidak sesuai, field Domain pada kolom B1 tidak ditemukan';
                                        }

                                        
                                    } else {
                                        $status = 'false';
                                        $message = 'Kolom format tidak sesuai, field Invoice Item pada kolom M1 tidak ditemukan';
                                    }

                                } else {
                                    $status = 'false';
                                    $message = 'Kolom format tidak sesuai, field Invoice pada kolom G6 tidak ditemukan';
                                }

                            } else {
                                $status = 'false';
                                $message = 'Kolom format tidak sesuai, field Client pada kolom G6 tidak ditemukan';
                            }

                        } else {
                            $status = 'false';
                            $message = 'failed upload file XLSX';
                        }

                } else {
                    $status = 'false';
                    $message = 'Please upload XLSX Files';
                }
                
            } else {
                $status = 'false';
                $message = 'Pilihan proses harus ditentukan';
            }
        } catch(\Exception $e) {
            $status = 'false';
            $message = 'Terjadi masalah, silahkan kontak administrator, error : '.$e->getMessage();
        }

        return [
			'status' => $status,
            'message' => $message,
            'total' => count($services),
            'services' => $services
		];

    }


    public function bulkservices_exec($vars) {

        $proses     = $vars['proses'];
        $plan       = $_POST['plan'];
        $domain     = $_POST['domain'];
        $invoice    = $_POST['invoice'];
        $doc        = $_POST['doc'];
        $position   = $_POST['position'];
        $clientid   = $_POST['clientid'];

        $packageid =  DB::table('tblproducts')->where('name', $plan)->first();

        // Proses Cancel Invoices
        if($proses == 'Cancel') {
            $invoices =  DB::table('tblinvoices')->where('id', $invoice)->first();
            if($invoices->status == 'Unpaid') {

                $update = $this->updateInvoices($invoice);

                if($update['result'] == 'success') {
                    $this->printStatus_3($invoice,'true','','Cancel', 'Success');
                    $this->moduleLog('',$proses,$plan,$domain,'success',serialize($update));
                } else {
                    $this->printStatus_3($invoice,'false','','Cancel',$update['message']);
                    $this->moduleLog('',$proses,$plan,$domain,'failed',serialize($update));
                }
            } else {
                $this->printStatus_3($invoice,'false','','Cancel', 'Invoice status not unpaid');
                $this->moduleLog('',$proses,$plan,$domain,'failed','Invoice status not unpaid');
            }
        }

        if($packageid->id != '') {
            $services = DB::table('tblhosting')->where('packageid', $packageid->id)->where('userid', $clientid)->where('domain', $domain)->get();

            foreach($services as $hosting) {
                if($hosting->id !='') {

                        // hanya memproses dengan layanan yang sudah out of date
                        if($hosting->nextduedate < date('Y-m-d') ) {

                            // Proses Cancel Layanan
                            if($proses == 'Cancel') {
                                if($invoices->status !== 'Unpaid') {
                                    // update layanan
                                    $cancel = $this->updateServicesCancel($hosting->id,'Cancelled',$hosting->nextduedate);
                                    if($cancel['result'] == 'success') {
                                        $this->printStatus($plan,$domain,'true',$hosting->nextduedate,$proses,$cancel['result']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'success',serialize($cancel));
                                        $this->updateSpreedsheet($position,$doc,$cancel['result']);
                                        $this->docReport($doc,$proses,0,1);
                                    } else {
                                        $this->printStatus($plan,$domain,'failed',$hosting->nextduedate,$proses,$cancel['result']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed',serialize($cancel));
                                        $this->updateSpreedsheet($position,$doc,$cancel['message']);
                                        $this->docReport($doc,$proses,1,0);
                                    }
                                }
                            }

                            // Proses Suspend
                            if($proses == 'Suspend') {
                                if($hosting->domainstatus !== 'Suspended') {
                                    $suspend = $this->Suspend($hosting->id);
                                    if($suspend['result'] == 'success') {

                                        // update layanan
                                        $this->updateServices($hosting->id,'Suspended');

                                        $this->printStatus($plan,$domain,'true',$hosting->nextduedate,$proses,$suspend['result']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'success',serialize($suspend));
                                        $this->updateSpreedsheet($position,$doc,$suspend['result']);
                                        $this->docReport($doc,$proses,0,1);
                                    } else {
                                        $this->printStatus($plan,$domain,'failed',$hosting->nextduedate,$proses,$suspend['message']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed',serialize($suspend));
                                        $this->updateSpreedsheet($position,$doc,$suspend['message']);
                                        $this->docReport($doc,$proses,1,0);
                                    }
                                } else {
                                    $this->printStatus($plan,$domain,'failed',$hosting->nextduedate,$proses,'Previous service has been suspended');
                                    $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed','Previous service has been suspended');
                                    $this->updateSpreedsheet($position,$doc,'Previous service has been suspended');
                                    $this->docReport($doc,$proses,1,0);
                                }
                            }

                            //Proses Terminate
                            if($proses == 'Terminate') {
                                if($hosting->domainstatus !== 'Terminated') {
                                    $terminated = $this->Terminated($hosting->id);
                                    
                                    if($terminated['result'] == 'success') {

                                        // update layanan
                                        $this->updateServices($hosting->id,'Terminated');

                                        $this->printStatus($plan,$domain,'true',$hosting->nextduedate,$proses,$terminated['result']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'success',serialize($terminated));
                                        $this->updateSpreedsheet($position,$doc,$terminated['result']);
                                        $this->docReport($doc,$proses,0,1);
                                    } else {
                                        $this->printStatus($plan,$domain,'failed',$hosting->nextduedate,$proses,$terminated['message']);
                                        $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed',serialize($terminated));
                                        $this->updateSpreedsheet($position,$doc,$terminated['message']);
                                        $this->docReport($doc,$proses,1,0);
                                    }
                                } else {
                                    $this->printStatus($plan,$domain,'failed',$hosting->nextduedate,$proses,'Previous service has been terminated');
                                    $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed','Previous service has been terminated');
                                    $this->updateSpreedsheet($position,$doc,'Previous service has been terminated');
                                    $this->docReport($doc,$proses,1,0);
                                }
                            }

                        } else {
                            $this->printStatus($plan,$domain,'false',$hosting->nextduedate,$proses,'Service period is still active, check nextduedate');
                            $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed','Service period is still active, check nextduedate');
                            $this->updateSpreedsheet($position,$doc,'Service period is still active, check nextduedate');
                            $this->docReport($doc,$proses,1,0);
                        }
                        
                } else {
                    $this->printStatus_2($plan,$domain,'false',$hosting->nextduedate,$proses,'Services not found');
                    $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed','Services not found');
                    $this->updateSpreedsheet($position,$doc,'Services not found');
                    $this->docReport($doc,$proses,1,0);
                }

            }
            
        } else {
            $this->printStatus_2($plan,$domain,'false',$hosting->nextduedate,$proses,'Plan not recognized');
            $this->moduleLog($hosting->id,$proses,$plan,$domain,'failed','Plan not recognized');
            $this->updateSpreedsheet($position,$doc,'Plan not recognized');
            $this->docReport($doc,$proses,1,0);
        }

    }

    public function printStatus($plan,$domain,$status,$duedate,$proses,$reason) {
        print '<p style="color:#ccc">- '.$proses.' '.$this->labelStatus($status).' plan : <b>'.$plan.'</b> for domain: <b>'.$domain.'</b> width nextduedate : <b>'.$duedate.'</b> response : '.$reason.'<p/>';
    }

    public function printStatus_2($plan,$domain,$status,$duedate,$proses,$reason) {
        print '<p style="color:#ccc">- '.$proses.' '.$this->labelStatus($status).' plan : <b>'.$plan.'</b> for domain: <b>'.$domain.'</b> and response : '.$reason.'<p/>';
    }
    
    public function printStatus_3($invoice,$status,$duedate,$proses,$reason) {
        print '<p style="color:#ccc">- '.$proses.' '.$this->labelStatus($status).' Invoice : <b>#'.$invoice.'</b> response : '.$reason.'</b><p/>';
    }

    public function labelStatus($status) {
        if($status == 'failed') {
            return '<span class="label label-danger">Failed</span>';
        }
        if($status == 'false') {
            return '<span class="label label-warning">Failed</span>';
        }
        if($status == 'true') {
            return '<span class="label label-success">Success</span>';
        }
    }


    public function moduleLog($hostingid,$proses,$plan,$domain,$status,$response) {
        try {
            DB::table('bulkservices_log')->insert([
                'userid' => $_SESSION['adminid'],
                'hostingid' => $hostingid,
                'proses' => $proses,
                'plan' => $plan,
                'domain' => $domain,
                'status' => $status,
                'response' => $response,
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return array('errcode' => 0);
        } catch (\Exception $e) {
            
        }
    }


    public function docReport($docname,$proses,$failed,$success) {
        try {

            $doc    =  DB::table('bulkservices_report')->where('docname', $docname)->first();
            $model  =  DB::table('bulkservices_report')->where('docname', $docname);
            $first  = $model->first();

            // if exist doc report
            if($doc->docname != '') {

                $model->update([
                    'failed' => $doc->failed + $failed,
                    'success' => $doc->success + $success
                ]);

            } else {
                DB::table('bulkservices_report')->insert([
                    'userid' => $_SESSION['adminid'],
                    'docname' => $docname,
                    'proses' => $proses,
                    'failed' => $failed,
                    'success' => $success,
                    'created_at' => date("Y-m-d H:i:s")
                ]);
            }
            return array('errcode' => 0);
        } catch (\Exception $e) {
            
        }
    }


    public function updateSpreedsheet($position,$doc,$value) {
        //load spreadsheet
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(dirname(__FILE__)."/../../../../../downloads/bulkservices/".$doc);

        //change it
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue($position, $value);

        //write it again to Filesystem with the same name (=replace)
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save(dirname(__FILE__)."/../../../../../downloads/bulkservices/".$doc);
    }

    public function uploadDocSpreedsheet($namaFile,$tmpFile) {

        $extFile = pathinfo($namaFile, PATHINFO_EXTENSION);
        $newFile = 'Bulkservices-Report-'.date('Y-m-d-h-i-s').'.'.$extFile;

        $dirUpload = dirname(__FILE__)."/../../../../../downloads/bulkservices/";
                        
        if (!file_exists($dirUpload)) {
            mkdir($dirUpload, 0777, true);
        }

        $terupload = move_uploaded_file($tmpFile, $dirUpload.$newFile);

        return [
			'status' => $terupload,
            'docname' => $newFile
		];
    }

    public function updateInvoices($id) {
        $command = 'UpdateInvoice';
        $postData = array(
            'invoiceid' => $id,
            'status' => 'Cancelled'
        );
        $adminUsername = 'mwn_exabytes'; 
        $results = localAPI($command, $postData, $adminUsername);
        return $results;
    }

    public function updateServices($id,$status) {
        $command = 'UpdateClientProduct';
        $postData = array(
            'serviceid' => $id,
            'status' => $status
        );
        $adminUsername = 'mwn_exabytes'; 
        $results = localAPI($command, $postData, $adminUsername);
        return $results;
    }

    public function updateServicesCancel($id,$status,$canceldate) {
        $command = 'UpdateClientProduct';
        $postData = array(
            'serviceid' => $id,
            'status' => $status,
            'notes' => 'automatic by system date :'. date('Y-m-d'),
            'customfields' => base64_encode(serialize(array(
                "Cancellation Date"=> $canceldate,
                "Cancellation Reason"=> "TNP - No Response",
                )))
        );
        $adminUsername = 'mwn_exabytes'; 
        $results = localAPI($command, $postData, $adminUsername);
        return $results;
    }

    public function Suspend($id) {
        $command = 'ModuleSuspend';
        $values = array(
            'serviceid' => $id,
            'suspendreason' => 'Bersih Bersih'
        );
        $adminuser = 'mwn_exabytes';
        $results = localAPI($command, $values, $adminuser);
        return $results;
    }

    public function Terminated($id) {
        $command = 'ModuleTerminate';
        $values = array(
            'serviceid' => $id
        );
        $adminuser = 'mwn_exabytes';
        $results = localAPI($command, $values, $adminuser);
        return $results;
    }







































    /**
     * Index action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */
    public function index($vars)
    {
        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $version = $vars['version']; // eg. 1.0
        $LANG = $vars['_lang']; // an array of the currently loaded language variables

        // Get module configuration parameters
        $configTextField = $vars['Text Field Name'];
        $configPasswordField = $vars['Password Field Name'];
        $configCheckboxField = $vars['Checkbox Field Name'];
        $configDropdownField = $vars['Dropdown Field Name'];
        $configRadioField = $vars['Radio Field Name'];
        $configTextareaField = $vars['Textarea Field Name'];

        return <<<EOF

<h2>Index</h2>

<p>This is the <em>index</em> action output of the sample addon module.</p>

<p>The currently installed version is: <strong>{$version}</strong></p>

<p>Values of the configuration field are as follows:</p>

<blockquote>
    Text Field: {$configTextField}<br>
    Password Field: {$configPasswordField}<br>
    Checkbox Field: {$configCheckboxField}<br>
    Dropdown Field: {$configDropdownField}<br>
    Radio Field: {$configRadioField}<br>
    Textarea Field: {$configTextareaField}
</blockquote>

<p>
    <a href="{$modulelink}&action=show" class="btn btn-success">
        <i class="fa fa-check"></i>
        Visit valid action link
    </a>
    <a href="{$modulelink}&action=invalid" class="btn btn-default">
        <i class="fa fa-times"></i>
        Visit invalid action link
    </a>
</p>

EOF;
    }

    /**
     * Show action.
     *
     * @param array $vars Module configuration parameters
     *
     * @return string
     */
    public function show($vars)
    {
        // Get common module parameters
        $modulelink = $vars['modulelink']; // eg. addonmodules.php?module=addonmodule
        $version = $vars['version']; // eg. 1.0
        $LANG = $vars['_lang']; // an array of the currently loaded language variables

        // Get module configuration parameters
        $configTextField = $vars['Text Field Name'];
        $configPasswordField = $vars['Password Field Name'];
        $configCheckboxField = $vars['Checkbox Field Name'];
        $configDropdownField = $vars['Dropdown Field Name'];
        $configRadioField = $vars['Radio Field Name'];
        $configTextareaField = $vars['Textarea Field Name'];

        return <<<EOF

<h2>Show</h2>

<p>This is the <em>show</em> action output of the sample addon module.</p>

<p>The currently installed version is: <strong>{$version}</strong></p>

<p>
    <a href="{$modulelink}" class="btn btn-info">
        <i class="fa fa-arrow-left"></i>
        Back to home
    </a>
</p>

EOF;
    }
}
