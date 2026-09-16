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
            $data['customers'] = $this->db->select('t1.id as customer_id, t1.name, t1.mobile, t1.email, t1.credit_limit, t1.credit_percent, t1.user_id')
                                          ->from('customers as t1')
                                          ->get()->result_array();
                                          
            $this->load->model('Wallet_model', 'wallet');
            // Calculate used credit, tax, total used, and available credit for all customers
            if (!empty($data['customers'])) {
                foreach ($data['customers'] as $key => $cust) {
                    $data['customers'][$key]['used_credit'] = $this->wallet->get_used_credit($cust['user_id']);
                    $data['customers'][$key]['tax_amount'] = $this->wallet->get_used_credit_tax($cust['user_id']);
                    $data['customers'][$key]['total_used'] = $this->wallet->get_total_used_credit_with_tax($cust['user_id']);
                    $data['customers'][$key]['effective_percent'] = $this->wallet->get_credit_percent($cust['user_id']);
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

        // Calculate used credit, percentage tax, total used with tax, and available limit
        $used_credit = $this->wallet->get_used_credit($user['id']);
        $credit_percent = $this->wallet->get_credit_percent($user['id']);
        $tax_amount = $this->wallet->get_used_credit_tax($user['id']);
        $total_used_with_tax = $this->wallet->get_total_used_credit_with_tax($user['id']);
        $available_limit = $this->wallet->get_available_credit($user['id']);
        
        $data['credit_limit'] = $credit_limit;
        $data['credit_percent'] = $credit_percent;
        $data['used_credit'] = $used_credit;
        $data['tax_amount'] = $tax_amount;
        $data['total_used_with_tax'] = $total_used_with_tax;
        $data['available_limit'] = $available_limit;
        
        $this->template->load('creditlimit', 'index', $data);
    }

    public function percentage()
    {
        if ($this->session->role != 'admin') {
            redirect('home');
            return;
        }

        $data = ['title' => 'Credit Limit Percentage'];
        $data['breadcrumb'] = array("active" => "Credit Limit Percentage");
        $data['datatable'] = true;

        $data['percentages'] = $this->db->order_by('id', 'DESC')->get('credit_limit_percentage')->result_array();

        $this->template->load('creditlimit', 'percentage', $data);
    }

    public function addpercentage()
    {
        if ($this->session->role != 'admin') {
            redirect('home');
            return;
        }

        if ($this->input->post('percent') !== NULL) {
            $percent = trim($this->input->post('percent'));
            $id = $this->input->post('id');

            if ($percent === '') {
                $this->session->set_flashdata("err_msg", "Percentage value is required!");
                redirect($_SERVER['HTTP_REFERER']);
                return;
            }

            $datetime = date('Y-m-d H:i:s');

            if (!empty($id)) {
                $this->db->where('id', $id);
                $this->db->update('credit_limit_percentage', [
                    'percent' => $percent,
                    'updated_on' => $datetime
                ]);
                $this->session->set_flashdata("msg", "Credit Limit Percentage updated successfully!");
            } else {
                $this->db->update('credit_limit_percentage', ['status' => 0, 'updated_on' => $datetime], ['status' => 1]);

                $this->db->insert('credit_limit_percentage', [
                    'percent' => $percent,
                    'status' => 1,
                    'added_on' => $datetime,
                    'updated_on' => $datetime
                ]);
                $this->session->set_flashdata("msg", "Credit Limit Percentage saved successfully!");
            }
        }

        redirect($_SERVER['HTTP_REFERER']);
    }

    public function getpercentage()
    {
        if ($this->session->role != 'admin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized']);
            return;
        }
        $id = $this->input->post('id');
        $row = $this->db->get_where('credit_limit_percentage', ['id' => $id])->unbuffered_row('array');
        echo json_encode($row);
    }

    public function update_limit()
    {
        if ($this->session->role != 'admin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized']);
            return;
        }

        $customer_id = $this->input->post('customer_id');
        $credit_limit = $this->input->post('credit_limit');
        $credit_percent = $this->input->post('credit_percent');

        if (!empty($customer_id)) {
            $update_data = ['credit_limit' => $credit_limit];
            if ($credit_percent !== NULL) {
                $update_data['credit_percent'] = $credit_percent;
            }
            $this->db->where('id', $customer_id);
            $this->db->update('customers', $update_data);
            echo json_encode(['status' => true, 'message' => 'Credit Limit updated successfully!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid Customer']);
        }
    }
}
