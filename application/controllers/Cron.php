<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        // Allow CLI only for security, or via secret key for HTTP invocation
        if (!is_cli()) {
            if ($this->input->get('key') !== 'cron-secret-key-apnatax') {
                exit('No direct script access allowed');
            }
        }
        
        $this->load->database();
        $this->load->model('service_model', 'service');
        $this->load->model('master_model', 'master');
        $this->load->model('customer_model', 'customer');
        $this->load->model('wallet_model', 'wallet');
        $this->load->library('auto_debit_service');
    }

    public function process_auto_debits()
    {
        echo "Starting Auto Debit Cron Job...\n";
        $this->load->library('auto_debit_service');
        $res = $this->auto_debit_service->process_all_due_auto_debits();
        echo "Auto Debit Cron Job Completed. Processed: {$res['processed']}, Success: {$res['success']}, Pending: {$res['pending']}\n";
    }
}
