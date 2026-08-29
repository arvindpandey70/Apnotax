<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Creditlimit extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        checklogin();
    }

    public function index()
    {
        $data = ['title' => 'Credit Limit'];
        $data['breadcrumb'] = array("active" => "Credit Limit");
        $data['datatable'] = true;
        
        if ($this->session->role == 'admin') {
            $data['customers'] = $this->db->select('t1.id as customer_id, t1.name, t1.mobile, t1.email, t1.credit_limit, t1.user_id')
                                          ->from('customers as t1')
                                          ->get()->result_array();
                                          
            $this->load->model('Wallet_model', 'wallet');
            // Calculate used credit for all customers
            if (!empty($data['customers'])) {
                foreach ($data['customers'] as $key => $cust) {
                    $data['customers'][$key]['used_credit'] = $this->wallet->get_used_credit($cust['user_id']);
                }
            }
            
            $this->template->load('creditlimit', 'admin_index', $data);
            return;
        }
        
        // Fetch current user details
        $user = getuser();
        $this->load->model('Wallet_model', 'wallet');
        
        // Fetch customer record for this user
        $customer = $this->db->get_where('customers', ['user_id' => $user['id']])->unbuffered_row('array');
        
        $credit_limit = 0.00;
        if (!empty($customer) && isset($customer['credit_limit'])) {
            $credit_limit = (float)$customer['credit_limit'];
        }
        
        // Calculate used credit and available limit
        $used_credit = $this->wallet->get_used_credit($user['id']);
        $available_limit = $this->wallet->get_available_credit($user['id']);
        
        $data['credit_limit'] = $credit_limit;
        $data['used_credit'] = $used_credit;
        $data['available_limit'] = $available_limit;
        
        $this->template->load('creditlimit', 'index', $data);
    }

    public function update_limit()
    {
        if ($this->session->role != 'admin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized']);
            return;
        }

        $customer_id = $this->input->post('customer_id');
        $credit_limit = $this->input->post('credit_limit');

        if (!empty($customer_id)) {
            $this->db->where('id', $customer_id);
            $this->db->update('customers', ['credit_limit' => $credit_limit]);
            echo json_encode(['status' => true, 'message' => 'Credit Limit updated successfully!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid Customer']);
        }
    }
}
