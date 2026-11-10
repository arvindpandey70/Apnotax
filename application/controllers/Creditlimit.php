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

        if ($this->session->role == 'admin') {
            $data['datatable'] = true;
            $query = $this->db->select('t1.id as customer_id, t1.name, t1.mobile, t1.email, t1.credit_limit, t1.credit_percent, t1.user_id')
                               ->from('customers as t1')
                               ->get();
            $data['customers'] = ($query && is_object($query)) ? $query->result_array() : [];

            // Batch-aggregate credit usage across all customers in fixed queries (avoids N+1 slowdown)
            if (!empty($data['customers'])) {
                $purchases_map = [];
                $q_pur = $this->db->select('user_id, COALESCE(SUM(amount), 0) as amt', false)
                                  ->from('purchases')
                                  ->where('type', 'Credit limit')
                                  ->where('status >=', 0)
                                  ->group_by('user_id')
                                  ->get();
                if ($q_pur && is_object($q_pur)) {
                    foreach ($q_pur->result_array() as $row) {
                        $purchases_map[(int)$row['user_id']] = (float)$row['amt'];
                    }
                }

                $acc_map = [];
                $q_acc = $this->db->select('user_id, COALESCE(SUM(amount), 0) as amt', false)
                                  ->from('acc_payment')
                                  ->where(['payment_mode' => 'Credit Limit', 'status' => 1])
                                  ->group_by('user_id')
                                  ->get();
                if ($q_acc && is_object($q_acc)) {
                    foreach ($q_acc->result_array() as $row) {
                        $acc_map[(int)$row['user_id']] = (float)$row['amt'];
                    }
                }

                $ledger_pay_map = [];
                $ledger_rec_map = [];
                $q_led = $this->db->select('user_id, type, COALESCE(SUM(amount), 0) as amt', false)
                                  ->from('credit_limit_ledger')
                                  ->group_by(['user_id', 'type'])
                                  ->get();
                if ($q_led && is_object($q_led)) {
                    foreach ($q_led->result_array() as $row) {
                        $uid = (int)$row['user_id'];
                        if ($row['type'] === 'receipt') {
                            $ledger_rec_map[$uid] = (float)$row['amt'];
                        } elseif ($row['type'] === 'payment') {
                            $ledger_pay_map[$uid] = (float)$row['amt'];
                        }
                    }
                }

                $global_percent = 0.00;
                $q_glob = $this->db->get_where('credit_limit_percentage', ['status' => 1]);
                if ($q_glob && is_object($q_glob)) {
                    $glob = $q_glob->unbuffered_row('array');
                    if (!empty($glob) && isset($glob['percent'])) {
                        $global_percent = (float)$glob['percent'];
                    }
                }

                foreach ($data['customers'] as $key => $cust) {
                    $uid = (int)$cust['user_id'];
                    $used = ($purchases_map[$uid] ?? 0.0)
                          + ($acc_map[$uid] ?? 0.0)
                          + ($ledger_pay_map[$uid] ?? 0.0)
                          - ($ledger_rec_map[$uid] ?? 0.0);
                    $used_credit = max(0.00, round($used, 2));
                    $pct = (isset($cust['credit_percent']) && $cust['credit_percent'] !== '' && $cust['credit_percent'] !== null)
                        ? (float)$cust['credit_percent']
                        : $global_percent;
                    $tax = round(($used_credit * $pct) / 100, 2);

                    $data['customers'][$key]['used_credit'] = $used_credit;
                    $data['customers'][$key]['tax_amount'] = $tax;
                    $data['customers'][$key]['total_used'] = round($used_credit + $tax, 2);
                    $data['customers'][$key]['effective_percent'] = $pct;
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

    public function percentage()
    {
        if ($this->session->role != 'admin') {
            redirect('creditlimit');
            return;
        }

        $data = [
            'title' => 'Credit Limit Percentage',
            'breadcrumb' => ['active' => 'Credit Limit Percentage']
        ];
        $data['percentages'] = $this->db
            ->order_by('id', 'DESC')
            ->get('credit_limit_percentage')
            ->result_array();

        $this->template->load('creditlimit', 'percentage', $data);
    }

    public function addpercentage()
    {
        if ($this->session->role != 'admin') {
            redirect('creditlimit');
            return;
        }

        if (!$this->input->post()) {
            redirect('creditlimit/percentage');
            return;
        }

        $percent = trim((string)$this->input->post('percent'));
        $id = (int)$this->input->post('id');
        if (!is_numeric($percent) || (float)$percent <= 0) {
            $this->session->set_flashdata('err_msg', 'Enter a percentage greater than zero.');
            redirect('creditlimit/percentage');
            return;
        }

        if ($id > 0 && !$this->db->get_where('credit_limit_percentage', ['id' => $id])->row_array()) {
            $this->session->set_flashdata('err_msg', 'Percentage record not found.');
            redirect('creditlimit/percentage');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_start();
        $this->db->where('status', 1)->update('credit_limit_percentage', [
            'status' => 0,
            'updated_on' => $now
        ]);

        if ($id > 0) {
            $this->db->where('id', $id)->update('credit_limit_percentage', [
                'percent' => (float)$percent,
                'status' => 1,
                'updated_on' => $now
            ]);
        } else {
            $this->db->insert('credit_limit_percentage', [
                'percent' => (float)$percent,
                'status' => 1,
                'added_on' => $now,
                'updated_on' => $now
            ]);
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_message('error', 'Failed to save the credit limit percentage.');
            $this->session->set_flashdata('err_msg', 'Unable to save the percentage. Please try again.');
        } else {
            $this->session->set_flashdata('msg', 'Percentage saved successfully.');
        }

        redirect('creditlimit/percentage');
    }

    public function getpercentage()
    {
        if ($this->session->role != 'admin') {
            show_error('Unauthorized', 403);
            return;
        }

        $id = (int)$this->input->post('id');
        $percentage = $id > 0
            ? $this->db->get_where('credit_limit_percentage', ['id' => $id])->row_array()
            : null;

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($percentage));
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

    private function save_customer_credit_limit()
    {
        $customer_id = (int)$this->input->post('customer_id');
        $credit_limit = trim((string)$this->input->post('credit_limit'));
        $credit_percent = trim((string)$this->input->post('credit_percent'));

        if ($customer_id <= 0 || !is_numeric($credit_limit) || (float)$credit_limit < 0) {
            return ['status' => false, 'message' => 'Enter a valid customer and credit limit.'];
        }

        if ($credit_percent !== '' && (!is_numeric($credit_percent) || (float)$credit_percent < 0)) {
            return ['status' => false, 'message' => 'Enter a valid credit percentage or leave it blank.'];
        }

        $customer = $this->db->get_where('customers', ['id' => $customer_id])->row_array();
        if (empty($customer)) {
            return ['status' => false, 'message' => 'Customer not found.'];
        }

        $updated = $this->db->where('id', $customer_id)->update('customers', [
            'credit_limit' => (float)$credit_limit,
            'credit_percent' => $credit_percent !== '' ? (float)$credit_percent : null
        ]);
        if (!$updated) {
            $error = $this->db->error();
            log_message('error', 'Failed to update customer credit limit: ' . $error['message']);
            return ['status' => false, 'message' => 'Unable to update credit limit. Please try again.'];
        }

        $user_id = (int)$customer['user_id'];
        if ($user_id > 0) {
            $this->load->library('auto_debit_service');
            $this->auto_debit_service->process_user_pending_auto_debits($user_id);
        }

        return ['status' => true, 'message' => 'Credit limit updated successfully.'];
    }

    public function update_limit()
    {
        if ($this->session->role != 'admin') {
            $this->output->set_status_header(403);
            $result = ['status' => false, 'message' => 'Unauthorized.'];
        } else {
            $result = $this->save_customer_credit_limit();
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($result));
    }

    public function update()
    {
        if ($this->session->role != 'admin') {
            redirect('creditlimit');
            return;
        }

        $result = $this->save_customer_credit_limit();
        $this->session->set_flashdata($result['status'] ? 'msg' : 'err_msg', $result['message']);
        redirect('creditlimit');
    }
}