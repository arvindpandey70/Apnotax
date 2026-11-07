<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Creditlimit extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        checklogin();
        $this->load->model('Wallet_model', 'wallet');
        $this->load->library('credit_service');
    }

    public function index($cust_param_id = null)
    {
        $data = ['title' => 'Credit Limit'];
        $data['breadcrumb'] = array("active" => "Credit Limit");
        $data['datatable'] = true;

        if ($this->session->role == 'admin') {
            $query = $this->db->select('t1.id as customer_id, t1.name, t1.mobile, t1.email, t1.credit_limit, t1.credit_percent, t1.user_id')
                               ->from('customers as t1')
                               ->get();
            $data['customers'] = ($query && is_object($query)) ? $query->result_array() : [];

            // Calculate used credit, tax, total used, and available credit for all customers
            if (!empty($data['customers'])) {
                foreach ($data['customers'] as $key => $cust) {
                    $data['customers'][$key]['used_credit'] = $this->wallet->get_used_credit($cust['user_id']);
                    $data['customers'][$key]['tax_amount'] = $this->wallet->get_used_credit_tax($cust['user_id']);
                    $data['customers'][$key]['total_used'] = $this->wallet->get_total_used_credit_with_tax($cust['user_id']);
                    $data['customers'][$key]['effective_percent'] = $this->wallet->get_credit_percent($cust['user_id']);
                }
            }

            // Preserved completely for Admin view as requested
            $this->template->load('creditlimit', 'admin_index', $data);
            return;
        }

        // Customer view
        $fy = $this->input->get('fy') ?: '2026-2027';
        $fy_parts = explode('-', $fy);
        $fy_start_year = (int)$fy_parts[0];
        if ($fy_start_year <= 2000) $fy_start_year = 2026;

        $user = getuser();
        $user_id = !empty($user['id']) ? (int)$user['id'] : (int)($this->session->user_id ?? 0);
        $res_cust = $this->db->get_where('customers', ['user_id' => $user_id]);
        $customer = ($res_cust && is_object($res_cust)) ? $res_cust->unbuffered_row('array') : [];

        $credit_limit = 0.00;
        if (!empty($customer) && isset($customer['credit_limit'])) {
            $credit_limit = (float)$customer['credit_limit'];
        }

        $ledger = $this->credit_service->get_fy_credit_ledger($user_id, $fy_start_year);

        $data['customer'] = $customer;
        $data['credit_limit'] = $credit_limit;
        $data['fy'] = $fy;
        $data['fy_start_year'] = $fy_start_year;
        $data['ledger'] = $ledger;

        $this->template->load('creditlimit', 'index', $data);
    }

    public function month_details($year_month = null)
    {
        $user = getuser();
        $user_id = !empty($user['id']) ? (int)$user['id'] : (int)($this->session->user_id ?? 0);
        if (!$user_id) {
            echo json_encode(['status' => false, 'message' => 'Unauthorized']);
            return;
        }

        if (empty($year_month)) {
            $year_month = $this->input->get('ym') ?: date('Y-m');
        }

        $res = $this->credit_service->get_month_daily_ledger($user_id, $year_month);

        header('Content-Type: application/json');
        echo json_encode($res);
    }

    public function export_csv()
    {
        $fy = $this->input->get('fy') ?: '2026-2027';
        $fy_parts = explode('-', $fy);
        $fy_start_year = (int)$fy_parts[0];
        if ($fy_start_year <= 2000) $fy_start_year = 2026;

        $user = getuser();
        $user_id = $user['id'] ?? null;

        if (!$user_id) {
            show_error("Unauthorized");
            return;
        }

        $filename = "credit_limit_ledger_" . $fy . "_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        $ledger = $this->credit_service->get_fy_credit_ledger($user_id, $fy_start_year);

        fputcsv($output, ['CREDIT LIMIT', number_format($ledger['credit_limit'], 2)]);
        fputcsv($output, ['AVAILABLE CREDIT', number_format($ledger['available_credit'], 2)]);
        fputcsv($output, ['TOTAL OUTSTANDING', number_format($ledger['total_outstanding'], 2)]);
        fputcsv($output, ['PANEL INTEREST RATE', $ledger['interest_rate'] . '% per 30 days']);
        fputcsv($output, ['FINANCIAL YEAR', 'FY ' . $ledger['fy_label']]);
        fputcsv($output, []);
        fputcsv($output, [
            'Month',
            'Opening Outstanding',
            'Package / Service Purchase',
            'Receipt',
            'Total',
            'Payment / Wallet Recovery',
            'Balance',
            'Panel Interest / Late Fee',
            'Closing Outstanding'
        ]);

        foreach ($ledger['rows'] as $r) {
            fputcsv($output, [
                $r['month_label'],
                $r['opening_outstanding'] > 0 ? ('-' . number_format($r['opening_outstanding'], 2, '.', '')) : '0.00',
                number_format($r['purchase_amount'], 2, '.', ''),
                number_format($r['receipt_amount'], 2, '.', ''),
                $r['total'] > 0 ? ('-' . number_format($r['total'], 2, '.', '')) : '0.00',
                number_format($r['wallet_recovery'], 2, '.', ''),
                $r['balance'] > 0 ? ('-' . number_format($r['balance'], 2, '.', '')) : '0.00',
                $r['panel_interest'] > 0 ? ('-' . number_format($r['panel_interest'], 2, '.', '')) : '0.00',
                $r['closing_outstanding'] > 0 ? ('-' . number_format($r['closing_outstanding'], 2, '.', '')) : '0.00'
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, [
            'TOTAL',
            '-',
            number_format($ledger['summary']['total_purchases'], 2, '.', ''),
            number_format($ledger['summary']['total_receipts'], 2, '.', ''),
            '-',
            number_format($ledger['summary']['total_recoveries'], 2, '.', ''),
            '-',
            number_format($ledger['summary']['total_interest'], 2, '.', ''),
            $ledger['summary']['closing_outstanding'] > 0 ? ('-' . number_format($ledger['summary']['closing_outstanding'], 2, '.', '')) : '0.00'
        ]);

        fclose($output);
    }

    public function update()
    {
        if ($this->session->role != 'admin') {
            redirect('creditlimit');
        }

        $customer_id = $this->input->post('customer_id');
        $credit_limit = $this->input->post('credit_limit');
        $credit_percent = $this->input->post('credit_percent');

        if ($customer_id) {
            $this->db->where('id', $customer_id);
            $this->db->update('customers', [
                'credit_limit' => $credit_limit,
                'credit_percent' => $credit_percent !== '' ? $credit_percent : null
            ]);
            $this->session->set_flashdata('msg', 'Credit limit updated successfully!');
        }

        redirect('creditlimit');
    }
}