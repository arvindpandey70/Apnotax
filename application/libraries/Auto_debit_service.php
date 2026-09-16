<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auto_debit_service
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Wallet_model', 'wallet');
        $this->CI->load->model('Service_model', 'service');
        $this->CI->load->model('Master_model', 'master');
        $this->CI->load->model('Customer_model', 'customer');
        $this->CI->load->model('Invoice_model', 'invoice');
        $this->CI->load->helper('common');
    }

    /**
     * Entrypoint for Cron Job: Process all due recurring auto-debits across all users.
     */
    public function process_all_due_auto_debits()
    {
        log_message('info', '[Auto_debit_service] Starting batch auto-debit process for all users.');
        $processed_count = 0;
        $success_count = 0;
        $pending_count = 0;

        $today = date('Y-m-d');

        // 1. Process Due Monthly/Service Packages (service_packages table)
        $this->CI->db->select('id, user_id');
        $this->CI->db->from('service_packages');
        $this->CI->db->where('expiry_date <=', $today);
        $this->CI->db->where('expiry_date IS NOT NULL', NULL, FALSE);
        $this->CI->db->where('expiry_date !=', '');
        $this->CI->db->where('request', 0); // Active, not pending deletion
        $this->CI->db->order_by('expiry_date', 'ASC');
        $query_sp = $this->CI->db->get();

        if ($query_sp && $query_sp->num_rows() > 0) {
            $service_pkgs = $query_sp->result_array();
            foreach ($service_pkgs as $sp) {
                $processed_count++;
                $res = $this->attempt_service_package_auto_debit($sp['id']);
                if ($res['status'] === true) {
                    $success_count++;
                } else {
                    $pending_count++;
                }
            }
        }

        // 2. Process Due Monthly Account Work Packages (customer_packages table)
        $this->CI->db->select('id, user_id');
        $this->CI->db->from('customer_packages');
        $this->CI->db->where('status', 1);
        $this->CI->db->where('expiry_date <=', $today);
        $this->CI->db->where('expiry_date IS NOT NULL', NULL, FALSE);
        $this->CI->db->where('expiry_date !=', '');
        $this->CI->db->where('request', 0);
        $this->CI->db->order_by('expiry_date', 'ASC');
        $query_cp = $this->CI->db->get();

        if ($query_cp && $query_cp->num_rows() > 0) {
            $cust_pkgs = $query_cp->result_array();
            foreach ($cust_pkgs as $cp) {
                $processed_count++;
                $res = $this->attempt_customer_package_auto_debit($cp['id']);
                if ($res['status'] === true) {
                    $success_count++;
                } else {
                    $pending_count++;
                }
            }
        }

        // 3. Process Due Turnover Accountancy Debits (accountancy table)
        $turnover_res = $this->process_all_turnover_auto_debits();
        $processed_count += $turnover_res['processed'];
        $success_count   += $turnover_res['success'];
        $pending_count   += $turnover_res['pending'];

        log_message('info', "[Auto_debit_service] Batch complete. Total Processed: {$processed_count}, Success: {$success_count}, Pending: {$pending_count}");
        return array(
            'status'    => true,
            'processed' => $processed_count,
            'success'   => $success_count,
            'pending'   => $pending_count
        );
    }

    /**
     * Entrypoint for Wallet Recharge: Process pending auto-debits for a single user in chronological order.
     */
    public function process_user_pending_auto_debits($user_id)
    {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return array('status' => false, 'message' => 'Invalid user ID');

        log_message('info', "[Auto_debit_service] Processing pending auto-debits after wallet recharge for User ID: {$user_id}");
        $today = date('Y-m-d');
        $processed = 0;
        $success = 0;

        // 1. Pending Monthly Service Packages for user
        $this->CI->db->select('id');
        $this->CI->db->from('service_packages');
        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->where('expiry_date <=', $today);
        $this->CI->db->where('request', 0);
        $this->CI->db->order_by('expiry_date', 'ASC');
        $query_sp = $this->CI->db->get();
        if ($query_sp && $query_sp->num_rows() > 0) {
            foreach ($query_sp->result_array() as $sp) {
                $processed++;
                $res = $this->attempt_service_package_auto_debit($sp['id']);
                if ($res['status'] === true) $success++;
            }
        }

        // 2. Pending Monthly Customer Packages for user
        $this->CI->db->select('id');
        $this->CI->db->from('customer_packages');
        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->where('status', 1);
        $this->CI->db->where('expiry_date <=', $today);
        $this->CI->db->where('request', 0);
        $this->CI->db->order_by('expiry_date', 'ASC');
        $query_cp = $this->CI->db->get();
        if ($query_cp && $query_cp->num_rows() > 0) {
            foreach ($query_cp->result_array() as $cp) {
                $processed++;
                $res = $this->attempt_customer_package_auto_debit($cp['id']);
                if ($res['status'] === true) $success++;
            }
        }

        // 3. Pending Turnover Accountancy Debits for user
        $turn_res = $this->process_user_turnover_auto_debits($user_id);
        $processed += $turn_res['processed'];
        $success   += $turn_res['success'];

        log_message('info', "[Auto_debit_service] User {$user_id} pending auto-debits processed: {$processed}, Successful: {$success}");
        return array('status' => true, 'processed' => $processed, 'success' => $success);
    }

    /**
     * Process auto-debit for a single service_packages record.
     */
    public function attempt_service_package_auto_debit($package_id)
    {
        $package_id = (int)$package_id;
        $pkg = $this->CI->db->get_where('service_packages', ['id' => $package_id])->unbuffered_row('array');

        if (empty($pkg)) {
            return array('status' => false, 'message' => 'Service package not found.');
        }

        $user_id = (int)$pkg['user_id'];
        $firm_id = (int)$pkg['firm_id'];
        $year    = $pkg['year'];
        $package_type = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Yearly';
        $today   = date('Y-m-d');
        $datetime = date('Y-m-d H:i:s');

        // Check if package is due
        $exp = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
        if (!$exp || $exp > strtotime($today)) {
            return array('status' => false, 'message' => 'Package is not due for renewal.');
        }

        $bill_amount = (float)($pkg['bill_amount'] ?? 0);
        if ($bill_amount <= 0) {
            return array('status' => false, 'message' => 'Invalid bill amount.');
        }

        // Resolve services & calculate GST
        $customer = $this->CI->customer->getcustomers(['t1.user_id' => $user_id], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

        $services_str = $pkg['service_ids'] ?? '';
        $s_ids = array_filter(array_map('trim', explode(',', $services_str)));
        if (empty($s_ids)) {
            return array('status' => false, 'message' => 'No services in package.');
        }

        $services = $this->CI->master->getservices("status='1' AND id IN ('" . implode("','", $s_ids) . "')");
        if (empty($services)) {
            return array('status' => false, 'message' => 'No active services in package.');
        }

        $opt_data = !empty($pkg['service_option_ids']) ? (json_decode($pkg['service_option_ids'], true) ?: array()) : array();
        $total_base_rate = 0;
        $service_rates = array();
        foreach ($services as $svc) {
            $rate = (float)$svc['rate'];
            if (!empty($opt_data[$svc['id']])) {
                $opt = $this->CI->master->getserviceoptions(['id' => $opt_data[$svc['id']], 'status' => 1], 'single');
                if (!empty($opt['rate'])) $rate = (float)$opt['rate'];
            }
            $service_rates[$svc['id']] = $rate;
            $total_base_rate += $rate;
        }

        $total_gst = $gst_enabled ? round(($bill_amount * 18) / 100, 2) : 0;
        $total_required_amount = round($bill_amount + $total_gst, 2);

        // Calculate billing period identifier for duplicate protection
        $billing_period = date('Ym', strtotime($pkg['expiry_date']));

        // Check idempotency: Ensure this specific billing period for this service package hasn't already been charged
        $dup_check = $this->CI->db->query(
            "SELECT id FROM purchases WHERE user_id = ? AND firm_id = ? AND service_id IN ('" . implode("','", $s_ids) . "') AND period_value = ?",
            array($user_id, $firm_id, $billing_period)
        );
        if ($dup_check && $dup_check->num_rows() > 0) {
            // Already charged for this period, update expiry date forward to prevent stuck loop
            $new_expiry = $this->calculate_next_billing_date($pkg['expiry_date'], $package_type);
            $this->CI->db->update('service_packages', [
                'payment_status'    => 1,
                'auto_debit_status' => 'Confirmed',
                'purchase_date'     => $today,
                'expiry_date'       => $new_expiry,
                'updated_on'        => $datetime
            ], ['id' => $package_id]);
            log_message('info', "[Auto_debit_service] Duplicate charge prevented for Service Package ID: {$package_id}, period: {$billing_period}");
            return array('status' => true, 'message' => 'Billing period already processed.');
        }

        // Fetch balances
        $wallet_balance = $this->CI->wallet->getwalletbalance($user_id);
        $credit_balance = $this->CI->wallet->get_available_credit($user_id);

        // Determine Payment Mode & Amounts
        $wallet_deduction = 0.00;
        $credit_deduction = 0.00;
        $is_success = false;

        if ($wallet_balance >= $total_required_amount) {
            // Priority 1: Full Wallet
            $wallet_deduction = $total_required_amount;
            $is_success = true;
        } elseif ($credit_balance >= $total_required_amount) {
            // Priority 2: Full Credit Limit
            $credit_deduction = $total_required_amount;
            $is_success = true;
        } elseif (($wallet_balance + $credit_balance) >= $total_required_amount && $wallet_balance > 0) {
            // Priority 3: Split Wallet + Credit Limit
            $wallet_deduction = $wallet_balance;
            $credit_deduction = round($total_required_amount - $wallet_balance, 2);
            $is_success = true;
        } else {
            // Priority 4: Insufficient Funds -> Mark PENDING
            $this->CI->db->update('service_packages', [
                'auto_debit_status' => 'Pending',
                'updated_on'        => $datetime
            ], ['id' => $package_id]);
            log_message('info', "[Auto_debit_service] Insufficient funds for Service Package ID: {$package_id}. Wallet: ₹{$wallet_balance}, Credit: ₹{$credit_balance}, Required: ₹{$total_required_amount}. Marked PENDING.");
            return array('status' => false, 'message' => 'Insufficient funds. Marked PENDING.');
        }

        // Execute Transaction Safely
        $this->CI->db->trans_start();

        if ($wallet_deduction > 0 && $credit_deduction > 0) {
            // SPLIT PAYMENT: Create purchase records for both components
            foreach ($services as $svc) {
                $svc_ratio = ($total_base_rate > 0) ? ($service_rates[$svc['id']] / $total_base_rate) : (1 / count($services));
                $svc_base_amt = round($bill_amount * $svc_ratio, 2);
                $svc_gst_amt  = $gst_enabled ? round($total_gst * $svc_ratio, 2) : 0;
                $svc_total_amt = round($svc_base_amt + $svc_gst_amt, 2);

                $wallet_ratio = $wallet_deduction / $total_required_amount;
                $credit_ratio = $credit_deduction / $total_required_amount;

                // 1. Wallet purchase records
                $w_sub = round($svc_base_amt * $wallet_ratio, 2);
                $w_gst = round($svc_gst_amt * $wallet_ratio, 2);
                $w_amt = round($w_sub + $w_gst, 2);

                $this->CI->db->insert('purchases', [
                    'date'         => $today,
                    'year'         => $year,
                    'type'         => $package_type,
                    'period_value' => $billing_period,
                    'user_id'      => $user_id,
                    'service_id'   => $svc['id'],
                    'firm_id'      => $firm_id,
                    'service'      => $svc['name'] . ' (Auto-Debit Wallet)',
                    'rate'         => $w_sub,
                    'subtotal'     => $w_sub,
                    'gst_amount'   => $w_gst,
                    'gst_enabled'  => $gst_enabled ? 1 : 0,
                    'amount'       => $w_amt,
                    'status'       => 0,
                    'added_on'     => $datetime,
                    'updated_on'   => $datetime,
                ]);

                // 2. Credit limit purchase records
                $c_sub = round($svc_base_amt * $credit_ratio, 2);
                $c_gst = round($svc_gst_amt * $credit_ratio, 2);
                $c_amt = round($c_sub + $c_gst, 2);

                $this->CI->db->insert('purchases', [
                    'date'         => $today,
                    'year'         => $year,
                    'type'         => 'Credit limit',
                    'period_value' => $billing_period,
                    'user_id'      => $user_id,
                    'service_id'   => $svc['id'],
                    'firm_id'      => $firm_id,
                    'service'      => $svc['name'] . ' (Auto-Debit Credit Limit)',
                    'rate'         => $c_sub,
                    'subtotal'     => $c_sub,
                    'gst_amount'   => $c_gst,
                    'gst_enabled'  => $gst_enabled ? 1 : 0,
                    'amount'       => $c_amt,
                    'status'       => 0,
                    'added_on'     => $datetime,
                    'updated_on'   => $datetime,
                ]);
            }
        } else {
            // FULL WALLET OR FULL CREDIT LIMIT
            $insert_type = ($credit_deduction > 0) ? 'Credit limit' : $package_type;
            foreach ($services as $svc) {
                $svc_ratio = ($total_base_rate > 0) ? ($service_rates[$svc['id']] / $total_base_rate) : (1 / count($services));
                $svc_base_amt = round($bill_amount * $svc_ratio, 2);
                $svc_gst_amt  = $gst_enabled ? round($total_gst * $svc_ratio, 2) : 0;
                $svc_total_amt = round($svc_base_amt + $svc_gst_amt, 2);

                $this->CI->db->insert('purchases', [
                    'date'         => $today,
                    'year'         => $year,
                    'type'         => $insert_type,
                    'period_value' => $billing_period,
                    'user_id'      => $user_id,
                    'service_id'   => $svc['id'],
                    'firm_id'      => $firm_id,
                    'service'      => $svc['name'] . ' (Auto-Debit)',
                    'rate'         => $svc_base_amt,
                    'subtotal'     => $svc_base_amt,
                    'gst_amount'   => $svc_gst_amt,
                    'gst_enabled'  => $gst_enabled ? 1 : 0,
                    'amount'       => $svc_total_amt,
                    'status'       => 0,
                    'added_on'     => $datetime,
                    'updated_on'   => $datetime,
                ]);
            }
        }

        // Calculate next expiry date
        $new_expiry = $this->calculate_next_billing_date($pkg['expiry_date'], $package_type);

        // Update service_packages status and next billing date
        $this->CI->db->update('service_packages', [
            'payment_status'    => 1,
            'auto_debit_status' => 'Confirmed',
            'purchase_date'     => $today,
            'expiry_date'       => $new_expiry,
            'updated_on'        => $datetime
        ], ['id' => $package_id]);

        // Create Invoice
        $firm_info = $this->CI->customer->getfirms(['t1.id' => $firm_id], 'single');
        $svc_names = array_column($services, 'name');
        try {
            $this->CI->invoice->create_custom_invoice([
                'user_id'        => $user_id,
                'firm_id'        => $firm_id,
                'year'           => $year,
                'invoice_date'   => $today,
                'billing_name'   => !empty($customer['name'])   ? $customer['name']   : '',
                'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                'service_name'   => implode(', ', $svc_names) . ' (Auto-Debit Renewal)',
                'type'           => $package_type,
                'period_value'   => $billing_period,
                'subtotal'       => round($bill_amount, 2),
                'gst_rate'       => $gst_enabled ? 18.0 : 0.0,
                'gst_amount'     => round($total_gst, 2),
                'total_amount'   => $total_required_amount,
            ]);
        } catch (Exception $e) {
            log_message('error', '[Auto_debit_service] Invoice error: ' . $e->getMessage());
        }

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            log_message('error', "[Auto_debit_service] Transaction failed for Service Package ID: {$package_id}");
            return array('status' => false, 'message' => 'Transaction failed.');
        }

        log_message('info', "[Auto_debit_service] SUCCESS auto-debit for Service Package ID: {$package_id}. Wallet: ₹{$wallet_deduction}, Credit: ₹{$credit_deduction}. Next expiry: {$new_expiry}");
        return array('status' => true, 'message' => 'Auto-debit successful.', 'next_expiry' => $new_expiry);
    }

    /**
     * Process auto-debit for a single customer_packages record (Account Work Monthly package).
     */
    public function attempt_customer_package_auto_debit($package_id)
    {
        $package_id = (int)$package_id;
        $pkg = $this->CI->db->get_where('customer_packages', ['id' => $package_id])->unbuffered_row('array');

        if (empty($pkg)) {
            return array('status' => false, 'message' => 'Customer package not found.');
        }

        $user_id = (int)$pkg['user_id'];
        $firm_id = (int)$pkg['firm_id'];
        $year    = $pkg['year'];
        $pkg_type = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Monthly';
        $today   = date('Y-m-d');
        $datetime = date('Y-m-d H:i:s');

        // Only handle Monthly customer packages in this function (Turnover packages handled separately)
        if ($pkg_type !== 'Monthly') {
            return array('status' => false, 'message' => 'Not a Monthly customer package.');
        }

        // Check if package is due
        $exp = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
        if (!$exp || $exp > strtotime($today)) {
            return array('status' => false, 'message' => 'Package is not due.');
        }

        // Calculate rate
        $m_rate = (float)($pkg['bill_amount'] ?? 0);
        if ($m_rate <= 0 && !empty($pkg['amount'])) $m_rate = (float)$pkg['amount'];
        if ($m_rate <= 0) {
            $account_work_service = $this->CI->master->getservices(['id' => 1, 'status' => 1], 'single');
            $m_rate = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
        }

        $customer = $this->CI->customer->getcustomers(['t1.user_id' => $user_id], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $m_gst_amount = $gst_enabled ? round(($m_rate * 18) / 100, 2) : 0;
        $m_total_amount = round($m_rate + $m_gst_amount, 2);

        $billing_period = date('Ym', strtotime($pkg['expiry_date']));

        // Check idempotency: Ensure this specific billing period for Account Work hasn't been charged yet
        $dup_check = $this->CI->db->query(
            "SELECT id FROM purchases WHERE user_id = ? AND firm_id = ? AND service_id = 1 AND period_value = ?",
            array($user_id, $firm_id, $billing_period)
        );
        if ($dup_check && $dup_check->num_rows() > 0) {
            $new_expiry = $this->calculate_next_billing_date($pkg['expiry_date'], 'Monthly');
            $this->CI->db->update('customer_packages', [
                'payment_status'    => 1,
                'auto_debit_status' => 'Confirmed',
                'purchase_date'     => $today,
                'expiry_date'       => $new_expiry,
                'updated_on'        => $datetime
            ], ['id' => $package_id]);
            log_message('info', "[Auto_debit_service] Duplicate charge prevented for Customer Package ID: {$package_id}, period: {$billing_period}");
            return array('status' => true, 'message' => 'Billing period already processed.');
        }

        // Fetch balances
        $wallet_balance = $this->CI->wallet->getwalletbalance($user_id);
        $credit_balance = $this->CI->wallet->get_available_credit($user_id);

        $wallet_deduction = 0.00;
        $credit_deduction = 0.00;

        if ($wallet_balance >= $m_total_amount) {
            // Priority 1: Full Wallet
            $wallet_deduction = $m_total_amount;
        } elseif ($credit_balance >= $m_total_amount) {
            // Priority 2: Full Credit Limit
            $credit_deduction = $m_total_amount;
        } elseif (($wallet_balance + $credit_balance) >= $m_total_amount && $wallet_balance > 0) {
            // Priority 3: Split
            $wallet_deduction = $wallet_balance;
            $credit_deduction = round($m_total_amount - $wallet_balance, 2);
        } else {
            // Priority 4: Insufficient Funds -> Mark PENDING
            $this->CI->db->update('customer_packages', [
                'auto_debit_status' => 'Pending',
                'updated_on'        => $datetime
            ], ['id' => $package_id]);
            log_message('info', "[Auto_debit_service] Insufficient funds for Account Work Package ID: {$package_id}. Wallet: ₹{$wallet_balance}, Credit: ₹{$credit_balance}, Required: ₹{$m_total_amount}. Marked PENDING.");
            return array('status' => false, 'message' => 'Insufficient funds. Marked PENDING.');
        }

        $this->CI->db->trans_start();

        $m_date_str = date('F Y', strtotime($pkg['expiry_date']));

        if ($wallet_deduction > 0 && $credit_deduction > 0) {
            // Split insertion
            $wallet_ratio = $wallet_deduction / $m_total_amount;
            $credit_ratio = $credit_deduction / $m_total_amount;

            // Row 1: Wallet portion
            $w_sub = round($m_rate * $wallet_ratio, 2);
            $w_gst = round($m_gst_amount * $wallet_ratio, 2);
            $this->CI->db->insert('purchases', [
                'date'         => $today,
                'year'         => $year,
                'type'         => 'Monthly',
                'period_value' => $billing_period,
                'user_id'      => $user_id,
                'service_id'   => 1,
                'firm_id'      => $firm_id,
                'service'      => 'Account Work Monthly (' . $m_date_str . ' Auto-Debit Wallet)',
                'rate'         => $w_sub,
                'subtotal'     => $w_sub,
                'gst_amount'   => $w_gst,
                'gst_enabled'  => $gst_enabled ? 1 : 0,
                'amount'       => round($w_sub + $w_gst, 2),
                'status'       => 0,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
            ]);

            // Row 2: Credit limit portion
            $c_sub = round($m_rate * $credit_ratio, 2);
            $c_gst = round($m_gst_amount * $credit_ratio, 2);
            $this->CI->db->insert('purchases', [
                'date'         => $today,
                'year'         => $year,
                'type'         => 'Credit limit',
                'period_value' => $billing_period,
                'user_id'      => $user_id,
                'service_id'   => 1,
                'firm_id'      => $firm_id,
                'service'      => 'Account Work Monthly (' . $m_date_str . ' Auto-Debit Credit Limit)',
                'rate'         => $c_sub,
                'subtotal'     => $c_sub,
                'gst_amount'   => $c_gst,
                'gst_enabled'  => $gst_enabled ? 1 : 0,
                'amount'       => round($c_sub + $c_gst, 2),
                'status'       => 0,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
            ]);
        } else {
            $insert_type = ($credit_deduction > 0) ? 'Credit limit' : 'Monthly';
            $this->CI->db->insert('purchases', [
                'date'         => $today,
                'year'         => $year,
                'type'         => $insert_type,
                'period_value' => $billing_period,
                'user_id'      => $user_id,
                'service_id'   => 1,
                'firm_id'      => $firm_id,
                'service'      => 'Account Work Monthly (' . $m_date_str . ' Auto-Debit)',
                'rate'         => $m_rate,
                'subtotal'     => $m_rate,
                'gst_amount'   => $m_gst_amount,
                'gst_enabled'  => $gst_enabled ? 1 : 0,
                'amount'       => $m_total_amount,
                'status'       => 0,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
            ]);
        }

        // Calculate next expiry date
        $new_expiry = $this->calculate_next_billing_date($pkg['expiry_date'], 'Monthly');

        // Update customer_packages record
        $this->CI->db->update('customer_packages', [
            'payment_status'    => 1,
            'auto_debit_status' => 'Confirmed',
            'purchase_date'     => $today,
            'expiry_date'       => $new_expiry,
            'updated_on'        => $datetime
        ], ['id' => $package_id]);

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            log_message('error', "[Auto_debit_service] Transaction failed for Customer Package ID: {$package_id}");
            return array('status' => false, 'message' => 'Transaction failed.');
        }

        log_message('info', "[Auto_debit_service] SUCCESS auto-debit for Account Work Package ID: {$package_id}. Wallet: ₹{$wallet_deduction}, Credit: ₹{$credit_deduction}. Next expiry: {$new_expiry}");
        return array('status' => true, 'message' => 'Auto-debit successful.', 'next_expiry' => $new_expiry);
    }

    /**
     * Batch process turnover-based auto-debits for all users with pending accountancy entries.
     */
    public function process_all_turnover_auto_debits()
    {
        $today = date('Y-m-d');
        $this->CI->db->select('user_id, firm_id, date');
        $this->CI->db->from('accountancy');
        $this->CI->db->where("(auto_debit_status IS NULL OR auto_debit_status != 'Confirmed')", NULL, FALSE);
        $this->CI->db->where("due_date IS NOT NULL", NULL, FALSE);
        $this->CI->db->where("due_date !=", '');
        $this->CI->db->where("due_date <=", $today);
        $this->CI->db->where("turnover >", 0);
        $this->CI->db->group_by(array("user_id", "firm_id", "date"));

        $query = $this->CI->db->get();
        if (!$query || $query->num_rows() == 0) {
            return array('processed' => 0, 'success' => 0, 'pending' => 0);
        }

        $pending_debts = $query->result_array();
        $processed = 0;
        $success = 0;
        $pending = 0;

        $processed_keys = array();
        foreach ($pending_debts as $debt) {
            $user_id = (int)$debt['user_id'];
            $firm_id = (int)$debt['firm_id'];
            $key = $user_id . '_' . $firm_id . '_' . $debt['date'];

            if (isset($processed_keys[$key])) continue;
            $processed_keys[$key] = true;

            $processed++;
            $res = $this->attempt_turnover_auto_debit_entry($user_id, $firm_id, $debt['date']);
            if ($res['status'] === true) {
                $success++;
            } else {
                $pending++;
            }
        }

        return array('processed' => $processed, 'success' => $success, 'pending' => $pending);
    }

    /**
     * Process turnover-based auto-debits for a single user.
     */
    public function process_user_turnover_auto_debits($user_id)
    {
        $today = date('Y-m-d');
        $this->CI->db->select('user_id, firm_id, date');
        $this->CI->db->from('accountancy');
        $this->CI->db->where('user_id', (int)$user_id);
        $this->CI->db->where("(auto_debit_status IS NULL OR auto_debit_status != 'Confirmed')", NULL, FALSE);
        $this->CI->db->where("due_date IS NOT NULL", NULL, FALSE);
        $this->CI->db->where("due_date !=", '');
        $this->CI->db->where("due_date <=", $today);
        $this->CI->db->where("turnover >", 0);
        $this->CI->db->group_by(array("firm_id", "date"));

        $query = $this->CI->db->get();
        if (!$query || $query->num_rows() == 0) {
            return array('processed' => 0, 'success' => 0, 'pending' => 0);
        }

        $pending_debts = $query->result_array();
        $processed = 0;
        $success = 0;
        $pending = 0;

        foreach ($pending_debts as $debt) {
            $processed++;
            $res = $this->attempt_turnover_auto_debit_entry($user_id, (int)$debt['firm_id'], $debt['date']);
            if ($res['status'] === true) {
                $success++;
            } else {
                $pending++;
            }
        }

        return array('processed' => $processed, 'success' => $success, 'pending' => $pending);
    }

    /**
     * Attempt turnover auto-debit for a single accountancy date entry.
     */
    public function attempt_turnover_auto_debit_entry($user_id, $firm_id, $acc_date)
    {
        $today = date('Y-m-d');
        $datetime = date('Y-m-d H:i:s');

        // Fetch accountancy record
        $acct_row = $this->CI->db->get_where('accountancy', [
            'user_id' => $user_id,
            'firm_id' => $firm_id,
            'date'    => $acc_date
        ])->unbuffered_row('array');

        if (empty($acct_row)) {
            return array('status' => false, 'message' => 'Accountancy record not found.');
        }

        if (!empty($acct_row['auto_debit_status']) && $acct_row['auto_debit_status'] === 'Confirmed') {
            return array('status' => true, 'message' => 'Already confirmed.');
        }

        // Calculate financial year & turnover fees using existing logic
        $date_obj = new DateTime($acc_date);
        $month_num = (int)$date_obj->format('n');
        $y = (int)$date_obj->format('Y');
        $year = ($month_num >= 4) ? ($y . ($y + 1)) : (($y - 1) . $y);

        $yearval = getyearmonthvalues($year);
        $from = $yearval['year1'] . "-04-01";
        $to   = $yearval['year2'] . "-03-31";

        // Fetch active customer package for turnover type
        $cpackage = $this->CI->db->get_where('customer_packages', ['user_id' => $user_id, 'status' => 1])->unbuffered_row('array');
        if (empty($cpackage)) {
            return array('status' => false, 'message' => 'No active customer package.');
        }

        $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';
        if ($pkg_type === 'Monthly') {
            return array('status' => false, 'message' => 'Monthly package handled separately.');
        }

        $user_id_escaped = $this->CI->db->escape($user_id);
        $firm_id_escaped = $this->CI->db->escape($firm_id);
        $from_escaped    = $this->CI->db->escape($from);
        $to_escaped      = $this->CI->db->escape($to);
        $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";
        $accountancy = $this->CI->service->getturnoverswithpayment($where2);

        if (empty($accountancy)) {
            return array('status' => false, 'message' => 'No turnover data.');
        }

        $turnovers = array_column($accountancy, 'turnover');
        $total_turnover = array_sum($turnovers);
        $name = ($cpackage['package_id'] == 1) ? 'Accountancy Prime' : 'Accountancy Premium';
        $package = $this->CI->master->getpackages(['name' => $name, 'turnover>' => $total_turnover], 'single');

        // Calculate fee for this specific entry
        $fees = 0;
        if ($name === 'Accountancy Prime') {
            $gto = $total_turnover / 100000;
            if ($gto <= 0) $fees = 0;
            elseif ($gto <= 25) $fees = (12000 / 25) * $gto;
            elseif ($gto <= 50) $fees = (20000 / 50) * $gto;
            elseif ($gto <= 75) $fees = (25000 / 75) * $gto;
            elseif ($gto <= 100) $fees = (30000 / 100) * $gto;
            else $fees = 30000 + (($gto - 100) * (10000 / 100));
            $fees = round($fees, 2);
        } elseif ($name === 'Accountancy Premium') {
            $gto = $total_turnover / 100000;
            if ($gto <= 0) $fees = 0;
            elseif ($gto <= 25) $fees = (15000 / 25) * $gto;
            elseif ($gto <= 50) $fees = (24000 / 50) * $gto;
            elseif ($gto <= 75) $fees = (30000 / 75) * $gto;
            elseif ($gto <= 100) $fees = (36000 / 100) * $gto;
            else $fees = 36000 + (($gto - 100) * (15000 / 100));
            $fees = round($fees, 2);
        } else {
            if (!empty($package['turnover']) && !empty($package['rate'])) {
                $fees = ($total_turnover / $package['turnover']) * $package['rate'];
            }
        }

        $activeMonthsCount = 0;
        foreach ($accountancy as $acct) {
            if (isset($acct['turnover']) && $acct['turnover'] > 0) $activeMonthsCount++;
        }
        $monthlyAccountsFee = $activeMonthsCount > 0 ? ($fees / $activeMonthsCount) : 0;
        $acc_fees = round($monthlyAccountsFee, 2);

        $other_fee = $acct_row['other_fee'] ?? 0;
        $total_required = round($acc_fees + $other_fee, 2);

        if ($total_required <= 0) {
            $this->CI->db->update('accountancy', ['auto_debit_status' => 'Confirmed'], ['id' => $acct_row['id']]);
            return array('status' => true, 'message' => 'Zero fee entry auto-confirmed.');
        }

        // Idempotency check: Ensure acc_payment row doesn't already exist for this user, firm, month, and year
        $m_val = (int)date('m', strtotime($acc_date));
        $y_val = (int)date('Y', strtotime($acc_date));
        $existing_acc_payment = $this->CI->db->query(
            "SELECT id FROM acc_payment WHERE user_id = ? AND firm_id = ? AND MONTH(acc_date) = ? AND YEAR(acc_date) = ?",
            array($user_id, $firm_id, $m_val, $y_val)
        );
        if ($existing_acc_payment && $existing_acc_payment->num_rows() > 0) {
            $this->CI->db->update('accountancy', ['auto_debit_status' => 'Confirmed'], ['id' => $acct_row['id']]);
            return array('status' => true, 'message' => 'Turnover payment already recorded.');
        }

        // Fetch balances
        $wallet_balance = $this->CI->wallet->getwalletbalance($user_id);
        $credit_balance = $this->CI->wallet->get_available_credit($user_id);

        $wallet_deduction = 0.00;
        $credit_deduction = 0.00;

        if ($wallet_balance >= $total_required) {
            // Priority 1: Wallet
            $wallet_deduction = $total_required;
        } elseif ($credit_balance >= $total_required) {
            // Priority 2: Credit Limit
            $credit_deduction = $total_required;
        } elseif (($wallet_balance + $credit_balance) >= $total_required && $wallet_balance > 0) {
            // Priority 3: Split
            $wallet_deduction = $wallet_balance;
            $credit_deduction = round($total_required - $wallet_balance, 2);
        } else {
            // Priority 4: Insufficient Funds -> PENDING
            $this->CI->db->update('accountancy', ['auto_debit_status' => 'Pending'], ['id' => $acct_row['id']]);
            log_message('info', "[Auto_debit_service] Insufficient funds for Turnover Accountancy ID: {$acct_row['id']}. Wallet: ₹{$wallet_balance}, Credit: ₹{$credit_balance}, Required: ₹{$total_required}. Marked PENDING.");
            return array('status' => false, 'message' => 'Insufficient funds. Marked PENDING.');
        }

        $this->CI->db->trans_start();

        if ($wallet_deduction > 0 && $credit_deduction > 0) {
            // Split insertion in acc_payment
            $this->CI->db->insert('acc_payment', [
                'date'         => $today,
                'user_id'      => $user_id,
                'firm_id'      => $firm_id,
                'year'         => $year,
                'acc_date'     => $acc_date,
                'amount'       => $wallet_deduction,
                'payment_mode' => 'Wallet',
                'status'       => 1,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
                'acc_fee'      => round($acc_fees * ($wallet_deduction / $total_required), 2)
            ]);

            $this->CI->db->insert('acc_payment', [
                'date'         => $today,
                'user_id'      => $user_id,
                'firm_id'      => $firm_id,
                'year'         => $year,
                'acc_date'     => $acc_date,
                'amount'       => $credit_deduction,
                'payment_mode' => 'Credit Limit',
                'status'       => 1,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
                'acc_fee'      => round($acc_fees * ($credit_deduction / $total_required), 2)
            ]);
        } else {
            $pmode = ($credit_deduction > 0) ? 'Credit Limit' : 'Wallet';
            $this->CI->db->insert('acc_payment', [
                'date'         => $today,
                'user_id'      => $user_id,
                'firm_id'      => $firm_id,
                'year'         => $year,
                'acc_date'     => $acc_date,
                'amount'       => $total_required,
                'payment_mode' => $pmode,
                'status'       => 1,
                'added_on'     => $datetime,
                'updated_on'   => $datetime,
                'acc_fee'      => $acc_fees
            ]);
        }

        $this->CI->db->update('accountancy', ['auto_debit_status' => 'Confirmed'], ['id' => $acct_row['id']]);

        $this->CI->db->trans_complete();

        if ($this->CI->db->trans_status() === false) {
            log_message('error', "[Auto_debit_service] Transaction failed for Turnover Accountancy ID: {$acct_row['id']}");
            return array('status' => false, 'message' => 'Transaction failed.');
        }

        log_message('info', "[Auto_debit_service] SUCCESS turnover auto-debit for Accountancy ID: {$acct_row['id']}. Wallet: ₹{$wallet_deduction}, Credit: ₹{$credit_deduction}.");
        return array('status' => true, 'message' => 'Turnover auto-debit successful.');
    }

    /**
     * Helper to compute next billing / expiry date handling month-end dates, 28/29 Feb, 30/31 day months, and leap years.
     */
    public function calculate_next_billing_date($current_expiry, $package_type = 'Monthly')
    {
        $current_ts = strtotime($current_expiry);
        if (!$current_ts) {
            $current_ts = time();
        }

        $orig_day = (int)date('d', $current_ts);

        switch ($package_type) {
            case 'Quarterly':
                $target_month = strtotime('+3 months', strtotime(date('Y-m-01', $current_ts)));
                break;
            case 'Yearly':
                $target_month = strtotime('+1 year', strtotime(date('Y-m-01', $current_ts)));
                break;
            case 'Monthly':
            default:
                $target_month = strtotime('+1 month', strtotime(date('Y-m-01', $current_ts)));
                break;
        }

        $ny = (int)date('Y', $target_month);
        $nm = (int)date('m', $target_month);

        $days_in_nm = cal_days_in_month(CAL_GREGORIAN, $nm, $ny);
        $target_d = min($orig_day, $days_in_nm);

        return sprintf('%04d-%02d-%02d', $ny, $nm, $target_d);
    }
}
