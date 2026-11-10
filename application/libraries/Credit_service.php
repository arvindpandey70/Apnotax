<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Credit_service
{
    protected $CI;
    protected $synced_users = [];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Wallet_model', 'wallet');
    }

    /**
     * Validate and normalize a Y-m-d date, rejecting zero/pre-2000 placeholder dates.
     */
    protected function normalize_valid_date($raw_date, $fallback_datetime = null)
    {
        $raw = trim((string)$raw_date);
        if ($raw !== '' && $raw !== '0000-00-00' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            if ((int)$m[1] >= 2000 && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            }
        }

        if (!empty($fallback_datetime)) {
            $fb = trim((string)$fallback_datetime);
            if ($fb !== '' && $fb !== '0000-00-00' && $fb !== '0000-00-00 00:00:00') {
                $ts = strtotime($fb);
                if ($ts !== false && (int)date('Y', $ts) >= 2000) {
                    return date('Y-m-d', $ts);
                }
            }
        }

        return date('Y-m-d');
    }

    /**
     * Invalidate per-request sync cache when credit records change.
     */
    protected function invalidate_user_sync($user_id)
    {
        unset($this->synced_users[(int)$user_id]);
    }

    /**
     * Auto-create required database tables if they do not exist
     */
    public function ensure_tables_exist()
    {
        $prefix = $this->CI->db->dbprefix;
        
        $this->CI->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}credit_purchases` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `purchase_id` int(11) DEFAULT NULL,
            `service_id` int(11) DEFAULT 1,
            `service_name` varchar(255) NOT NULL,
            `purchase_date` date NOT NULL,
            `purchase_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
            `wallet_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
            `credit_used` decimal(12,2) NOT NULL DEFAULT '0.00',
            `interest_rate_percent` decimal(5,2) NOT NULL DEFAULT '4.00',
            `due_date` date DEFAULT NULL,
            `interest_start_date` date NOT NULL,
            `outstanding_principal` decimal(12,2) NOT NULL DEFAULT '0.00',
            `recovered_principal` decimal(12,2) NOT NULL DEFAULT '0.00',
            `remaining_principal` decimal(12,2) NOT NULL DEFAULT '0.00',
            `interest_accrued` decimal(12,2) NOT NULL DEFAULT '0.00',
            `interest_recovered` decimal(12,2) NOT NULL DEFAULT '0.00',
            `remaining_interest` decimal(12,2) NOT NULL DEFAULT '0.00',
            `recovery_status` enum('Unpaid','Partially Recovered','Fully Recovered') NOT NULL DEFAULT 'Unpaid',
            `last_calc_date` date DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `purchase_date` (`purchase_date`),
            KEY `recovery_status` (`recovery_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->CI->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}credit_recovery_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `credit_purchase_id` int(11) NOT NULL,
            `wallet_id` int(11) DEFAULT NULL,
            `recovery_date` date NOT NULL,
            `recovered_interest` decimal(12,2) NOT NULL DEFAULT '0.00',
            `recovered_principal` decimal(12,2) NOT NULL DEFAULT '0.00',
            `total_recovered` decimal(12,2) NOT NULL DEFAULT '0.00',
            `wallet_balance_before` decimal(12,2) NOT NULL DEFAULT '0.00',
            `wallet_balance_after` decimal(12,2) NOT NULL DEFAULT '0.00',
            `remarks` varchar(255) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `credit_purchase_id` (`credit_purchase_id`),
            KEY `recovery_date` (`recovery_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    /**
     * Safe query sum of column with check for table existence and null safety
     */
    public function get_sum($table, $sum_col, $where = [], $where_raw = null)
    {
        if (!$this->CI->db->table_exists($table)) {
            return 0.00;
        }

        $this->CI->db->select_sum($sum_col);
        if (!empty($where)) {
            $this->CI->db->where($where);
        }
        if (!empty($where_raw)) {
            $this->CI->db->where($where_raw, NULL, FALSE);
        }
        $q = $this->CI->db->get($table);
        if ($q && is_object($q)) {
            $row = $q->unbuffered_row();
            if (!empty($row) && isset($row->$sum_col) && $row->$sum_col !== null) {
                return (float)$row->$sum_col;
            }
        }
        return 0.00;
    }

    /**
     * Get customer's dynamic panel interest rate (percent per 30 days)
     */
    public function get_interest_rate($user_id)
    {
        $rate = $this->CI->wallet->get_credit_percent($user_id);
        if ($rate <= 0) {
            $rate = 4.00; // default 4% per 30 days
        }
        return (float)$rate;
    }

    /**
     * Get available credit limit for customer
     */
    public function get_available_credit($user_id)
    {
        $res_cust = $this->CI->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;

        // Sum of all currently remaining unpaid principals
        $this->sync_existing_credit_purchases($user_id);

        $this->CI->db->select_sum('remaining_principal');
        $this->CI->db->where('user_id', $user_id);
        $this->CI->db->where('recovery_status !=', 'Fully Recovered');
        $q = $this->CI->db->get('credit_purchases');
        $row = $q ? $q->unbuffered_row() : null;
        $used_principal = !empty($row) && isset($row->remaining_principal) ? (float)$row->remaining_principal : 0.00;

        return max(0.00, round($credit_limit - $used_principal, 2));
    }

    /**
     * Get total outstanding (unpaid principal + accrued interest) for customer
     */
    public function get_total_outstanding($user_id, $as_of_date = null)
    {
        $user_id = (int)$user_id;
        if (empty($as_of_date)) {
            $as_of_date = date('Y-m-d');
        }

        $this->sync_existing_credit_purchases($user_id);
        $q = $this->CI->db->get_where('credit_purchases', [
            'user_id' => $user_id,
            'recovery_status !=' => 'Fully Recovered'
        ]);
        $purchases = ($q && is_object($q)) ? $q->result_array() : [];

        $recoveries_by_purchase = [];
        if (!empty($purchases)) {
            $p_ids = array_column($purchases, 'id');
            $q_rec = $this->CI->db->where_in('credit_purchase_id', $p_ids)
                                  ->order_by('recovery_date', 'ASC')
                                  ->order_by('id', 'ASC')
                                  ->get('credit_recovery_logs');
            $all_recs = ($q_rec && is_object($q_rec)) ? $q_rec->result_array() : [];
            foreach ($all_recs as $rec) {
                $recoveries_by_purchase[(int)$rec['credit_purchase_id']][] = $rec;
            }
        }

        $total_principal = 0.00;
        $total_interest = 0.00;

        foreach ($purchases as $p) {
            $pid = (int)$p['id'];
            $calc = $this->calculate_purchase_interest($p, $as_of_date, $recoveries_by_purchase[$pid] ?? []);
            $total_principal += (float)$calc['remaining_principal'];
            $total_interest += (float)$calc['remaining_interest'];
        }

        return [
            'total_principal' => round($total_principal, 2),
            'total_interest'  => round($total_interest, 2),
            'total_outstanding' => round($total_principal + $total_interest, 2)
        ];
    }

    /**
     * Synchronize any existing credit purchases from purchases and acc_payment
     * into tf_credit_purchases so historical records are intact.
     */
    public function sync_existing_credit_purchases($user_id, $force = false)
    {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return;

        if (!$force && !empty($this->synced_users[$user_id])) {
            return;
        }

        // Load already-synced purchase IDs in a single query
        $q_existing = $this->CI->db->select('purchase_id, service_name')
                                   ->where('user_id', $user_id)
                                   ->where('purchase_id IS NOT NULL', null, false)
                                   ->get('credit_purchases');
        if ($q_existing === false) {
            $this->ensure_tables_exist();
            $q_existing = $this->CI->db->select('purchase_id, service_name')
                                       ->where('user_id', $user_id)
                                       ->where('purchase_id IS NOT NULL', null, false)
                                       ->get('credit_purchases');
        }

        $existing_purchase_ids = [];
        $existing_acc_ids = [];
        if ($q_existing && is_object($q_existing)) {
            foreach ($q_existing->result_array() as $ex) {
                $pid = (int)$ex['purchase_id'];
                if (stripos((string)$ex['service_name'], 'Accountancy (') === 0) {
                    $existing_acc_ids[$pid] = true;
                } else {
                    $existing_purchase_ids[$pid] = true;
                }
            }
        }

        // 1. From tf_purchases where type = 'Credit limit'
        $q_purchases = $this->CI->db->select('id, date, amount, service, service_id, added_on, status')
                                    ->where('user_id', $user_id)
                                    ->where('type', 'Credit limit')
                                    ->where('status >=', 0)
                                    ->get('purchases');
        $purchases = ($q_purchases && is_object($q_purchases)) ? $q_purchases->result_array() : [];

        // 2. From tf_acc_payment where payment_mode = 'Credit Limit' and status = 1
        $q_acc = $this->CI->db->select('id, date, amount, acc_date, added_on')
                              ->where('user_id', $user_id)
                              ->where('payment_mode', 'Credit Limit')
                              ->where('status', 1)
                              ->get('acc_payment');
        $acc_payments = ($q_acc && is_object($q_acc)) ? $q_acc->result_array() : [];

        $to_insert = [];
        $rate = null;
        $now = date('Y-m-d H:i:s');

        foreach ($purchases as $p) {
            $pid = (int)$p['id'];
            if (isset($existing_purchase_ids[$pid])) {
                continue;
            }
            if ($rate === null) {
                $rate = $this->get_interest_rate($user_id);
            }
            $p_date = $this->normalize_valid_date($p['date'] ?? '', $p['added_on'] ?? null);
            $amt = (float)$p['amount'];
            $to_insert[] = [
                'user_id'               => $user_id,
                'purchase_id'           => $pid,
                'service_id'            => $p['service_id'] ?? 1,
                'service_name'          => !empty($p['service']) ? $p['service'] : 'Package / Service',
                'purchase_date'         => $p_date,
                'purchase_amount'       => $amt,
                'wallet_deduction'      => 0.00,
                'credit_used'           => $amt,
                'interest_rate_percent' => $rate,
                'due_date'              => $p_date,
                'interest_start_date'   => $p_date,
                'outstanding_principal' => $amt,
                'recovered_principal'   => 0.00,
                'remaining_principal'   => $amt,
                'interest_accrued'      => 0.00,
                'interest_recovered'    => 0.00,
                'remaining_interest'    => 0.00,
                'recovery_status'       => 'Unpaid',
                'last_calc_date'        => $p_date,
                'created_at'            => !empty($p['added_on']) && strtotime($p['added_on']) !== false ? date('Y-m-d H:i:s', strtotime($p['added_on'])) : $now,
                'updated_at'            => $now
            ];
            $existing_purchase_ids[$pid] = true;
        }

        foreach ($acc_payments as $ap) {
            $apid = (int)$ap['id'];
            if (isset($existing_acc_ids[$apid])) {
                continue;
            }
            if ($rate === null) {
                $rate = $this->get_interest_rate($user_id);
            }
            $p_date = $this->normalize_valid_date($ap['date'] ?? '', $ap['added_on'] ?? null);
            $amt = (float)$ap['amount'];
            $acc_label = !empty($ap['acc_date']) && $ap['acc_date'] !== '0000-00-00' ? $ap['acc_date'] : $p_date;
            $to_insert[] = [
                'user_id'               => $user_id,
                'purchase_id'           => $apid,
                'service_id'            => 1,
                'service_name'          => 'Accountancy (' . $acc_label . ')',
                'purchase_date'         => $p_date,
                'purchase_amount'       => $amt,
                'wallet_deduction'      => 0.00,
                'credit_used'           => $amt,
                'interest_rate_percent' => $rate,
                'due_date'              => $p_date,
                'interest_start_date'   => $p_date,
                'outstanding_principal' => $amt,
                'recovered_principal'   => 0.00,
                'remaining_principal'   => $amt,
                'interest_accrued'      => 0.00,
                'interest_recovered'    => 0.00,
                'remaining_interest'    => 0.00,
                'recovery_status'       => 'Unpaid',
                'last_calc_date'        => $p_date,
                'created_at'            => !empty($ap['added_on']) && strtotime($ap['added_on']) !== false ? date('Y-m-d H:i:s', strtotime($ap['added_on'])) : $now,
                'updated_at'            => $now
            ];
            $existing_acc_ids[$apid] = true;
        }

        if (!empty($to_insert)) {
            $this->CI->db->insert_batch('credit_purchases', $to_insert);
        }

        $this->synced_users[$user_id] = true;
    }

    /**
     * Record a new purchase following: Wallet First, Credit Second
     */
    public function record_purchase($data)
    {
        $user_id         = (int)$data['user_id'];
        $service_id      = (int)($data['service_id'] ?? 1);
        $service_name    = $data['service_name'] ?? 'Package / Service';
        $purchase_amount = round((float)$data['purchase_amount'], 2);
        $purchase_date   = $this->normalize_valid_date($data['purchase_date'] ?? '');
        $purchase_id     = !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null;
        $due_date        = $this->normalize_valid_date($data['due_date'] ?? $purchase_date, $purchase_date);

        if ($purchase_amount <= 0) {
            return ['status' => false, 'message' => 'Invalid purchase amount'];
        }

        $wallet_balance  = (float)$this->CI->wallet->getwalletbalance($user_id);
        $available_credit = $this->get_available_credit($user_id);

        $wallet_deduction = 0.00;
        $credit_used      = 0.00;

        if (isset($data['force_credit']) && $data['force_credit']) {
            $wallet_deduction = 0.00;
            $credit_used = $purchase_amount;
        } elseif ($wallet_balance >= $purchase_amount) {
            $wallet_deduction = $purchase_amount;
            $credit_used = 0.00;
        } elseif ($wallet_balance > 0) {
            $wallet_deduction = round($wallet_balance, 2);
            $credit_used = round($purchase_amount - $wallet_deduction, 2);
        } else {
            $wallet_deduction = 0.00;
            $credit_used = $purchase_amount;
        }

        $credit_purchase_id = null;
        if ($credit_used > 0) {
            $rate = $this->get_interest_rate($user_id);
            $this->CI->db->insert('credit_purchases', [
                'user_id'               => $user_id,
                'purchase_id'           => $purchase_id,
                'service_id'            => $service_id,
                'service_name'          => $service_name,
                'purchase_date'         => $purchase_date,
                'purchase_amount'       => $purchase_amount,
                'wallet_deduction'      => $wallet_deduction,
                'credit_used'           => $credit_used,
                'interest_rate_percent' => $rate,
                'due_date'              => $due_date,
                'interest_start_date'   => $purchase_date,
                'outstanding_principal' => $credit_used,
                'recovered_principal'   => 0.00,
                'remaining_principal'   => $credit_used,
                'interest_accrued'      => 0.00,
                'interest_recovered'    => 0.00,
                'remaining_interest'    => 0.00,
                'recovery_status'       => 'Unpaid',
                'last_calc_date'        => $purchase_date,
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s')
            ]);
            $credit_purchase_id = $this->CI->db->insert_id();
            $this->invalidate_user_sync($user_id);
        }

        return [
            'status'             => true,
            'purchase_amount'    => $purchase_amount,
            'wallet_deduction'   => $wallet_deduction,
            'credit_used'        => $credit_used,
            'credit_purchase_id' => $credit_purchase_id
        ];
    }

    /**
     * Calculate interest on a specific purchase as of a specific date.
     * Simple Interest formula:
     * Daily Interest = Outstanding Principal * (Interest Percentage / 100) / 30
     * Total Interest = Daily Interest * Eligible Unpaid Days
     */
    public function calculate_purchase_interest($purchase, $as_of_date = null, $prefetched_recoveries = null)
    {
        if (empty($as_of_date)) {
            $as_of_date = date('Y-m-d');
        }

        $purchase_date = $this->normalize_valid_date(
            $purchase['interest_start_date'] ?? ($purchase['purchase_date'] ?? ''),
            $purchase['created_at'] ?? null
        );
        $rate = (float)($purchase['interest_rate_percent'] ?? 4.00);
        if ($rate <= 0) {
            $rate = 4.00;
        }
        $daily_rate = ($rate / 100.0) / 30.0;

        $principal = (float)$purchase['outstanding_principal'];
        $remaining_principal = (float)$purchase['remaining_principal'];
        $interest_recovered = (float)($purchase['interest_recovered'] ?? 0.00);

        $purchase_id = $purchase['id'] ?? 0;

        if ($prefetched_recoveries !== null) {
            $recoveries = $prefetched_recoveries;
        } else {
            $recoveries = [];
            if (!empty($purchase_id)) {
                $q = $this->CI->db->order_by('recovery_date', 'ASC')
                                  ->order_by('id', 'ASC')
                                  ->get_where('credit_recovery_logs', [
                                      'credit_purchase_id' => $purchase_id
                                  ]);
                $recoveries = ($q && is_object($q)) ? $q->result_array() : [];
            }
        }

        $total_interest_accrued = 0.00;
        $current_principal = $principal;
        $last_date = $purchase_date;

        if (!empty($recoveries) && $as_of_date >= $purchase_date) {
            foreach ($recoveries as $rec) {
                $rec_date = $this->normalize_valid_date($rec['recovery_date'] ?? '', $rec['created_at'] ?? null);
                if ($rec_date > $as_of_date) {
                    break;
                }

                if ($rec_date > $last_date && $current_principal > 0) {
                    $days = (int)round((strtotime($rec_date) - strtotime($last_date)) / 86400);
                    $total_interest_accrued += $current_principal * $daily_rate * $days;
                }

                $current_principal -= (float)$rec['recovered_principal'];
                if ($current_principal < 0) $current_principal = 0.0;
                if ($rec_date > $last_date) {
                    $last_date = $rec_date;
                }
            }
        }

        // Remaining active days up to as_of_date
        if ($as_of_date > $last_date && $current_principal > 0) {
            $days = (int)round((strtotime($as_of_date) - strtotime($last_date)) / 86400);
            $total_interest_accrued += $current_principal * $daily_rate * $days;
        }

        $total_interest_accrued = round($total_interest_accrued, 2);
        $remaining_interest = max(0.00, round($total_interest_accrued - $interest_recovered, 2));

        $total_days = 0;
        if ($as_of_date > $purchase_date) {
            $total_days = (int)round((strtotime($as_of_date) - strtotime($purchase_date)) / 86400);
        }

        return [
            'purchase_id'          => $purchase_id,
            'purchase_date'        => $purchase_date,
            'eligible_days'        => $total_days,
            'interest_rate'        => $rate,
            'daily_interest'       => round($remaining_principal * $daily_rate, 4),
            'total_accrued_interest' => $total_interest_accrued,
            'interest_recovered'   => $interest_recovered,
            'remaining_interest'   => $remaining_interest,
            'remaining_principal'  => $remaining_principal,
            'total_due'            => round($remaining_principal + $remaining_interest, 2)
        ];
    }

    /**
     * Build principal balance timeline segments (start, end] for a purchase.
     */
    protected function build_purchase_timeline($purchase, $recoveries, $default_rate = 4.00)
    {
        $p_start = $this->normalize_valid_date(
            $purchase['interest_start_date'] ?? ($purchase['purchase_date'] ?? ''),
            $purchase['created_at'] ?? null
        );
        $rate = (float)($purchase['interest_rate_percent'] ?? 0);
        if ($rate <= 0) {
            $rate = $default_rate > 0 ? $default_rate : 4.00;
        }
        $daily_rate = ($rate / 100.0) / 30.0;

        $segments = [];
        $cur_p = (float)$purchase['outstanding_principal'];
        $cur_date = $p_start;

        if (!empty($recoveries)) {
            foreach ($recoveries as $rec) {
                $r_date = $rec['_valid_date'];
                if ($r_date > $cur_date && $cur_p > 0) {
                    $segments[] = ['start' => $cur_date, 'end' => $r_date, 'principal' => $cur_p];
                }
                $cur_p -= (float)$rec['recovered_principal'];
                if ($cur_p < 0) $cur_p = 0.0;
                if ($r_date > $cur_date) {
                    $cur_date = $r_date;
                }
            }
        }

        if ($cur_p > 0) {
            $segments[] = ['start' => $cur_date, 'end' => '9999-12-31', 'principal' => $cur_p];
        }

        return [
            'purchase'   => $purchase,
            'p_start'    => $p_start,
            'rate'       => $rate,
            'daily_rate' => $daily_rate,
            'segments'   => $segments
        ];
    }

    /**
     * Calculate exact unrounded interest and unpaid days for one timeline over [period_start, period_end] inclusive.
     */
    protected function calculate_timeline_period_interest($tl, $period_start, $period_end)
    {
        if ($period_end < $period_start || $period_end <= $tl['p_start']) {
            return ['interest' => 0.0, 'days' => 0];
        }

        $prev_day = date('Y-m-d', strtotime($period_start . ' -1 day'));
        $interest = 0.0;
        $days_total = 0;

        foreach ($tl['segments'] as $seg) {
            if ($seg['principal'] <= 0) {
                continue;
            }
            $lower = ($seg['start'] > $prev_day) ? $seg['start'] : $prev_day;
            $upper = ($seg['end'] < $period_end) ? $seg['end'] : $period_end;
            if ($upper > $lower) {
                $d = (int)round((strtotime($upper) - strtotime($lower)) / 86400);
                $days_total += $d;
                $interest += $seg['principal'] * $tl['daily_rate'] * $d;
            }
        }

        return ['interest' => $interest, 'days' => $days_total];
    }

    /**
     * Calculate interest accrued during a specific month across all purchases for a user (capped at today).
     */
    public function calculate_month_interest_accrued($user_id, $month_start, $month_end)
    {
        $today = date('Y-m-d');
        $effective_end = ($month_end < $today) ? $month_end : $today;

        if ($effective_end < $month_start) {
            return 0.00;
        }

        $sources = $this->load_user_ledger_sources($user_id);
        $month_interest = 0.0;
        foreach ($sources['timelines'] as $tl) {
            $res = $this->calculate_timeline_period_interest($tl, $month_start, $effective_end);
            $month_interest += $res['interest'];
        }

        return round($month_interest, 2);
    }

    /**
     * Automatic Wallet Recovery on Wallet Recharge:
     * Check outstanding principal & applicable interest,
     * Recover interest first, then principal,
     * Update credit purchases & wallet deduction,
     * Maintain audit logs.
     */
    public function process_wallet_recovery($user_id, $wallet_id = null, $recovery_date = null)
    {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return ['status' => false, 'message' => 'Invalid user ID'];

        if (empty($recovery_date)) {
            $recovery_date = date('Y-m-d');
        }

        // Start Transaction
        $this->CI->db->trans_start();

        // 1. Check wallet balance
        $wallet_balance = (float)$this->CI->wallet->getwalletbalance($user_id);
        if ($wallet_balance <= 0) {
            $this->CI->db->trans_complete();
            return ['status' => true, 'recovered' => 0.00, 'message' => 'Wallet balance is 0.'];
        }

        // 2. Fetch all unpaid or partially recovered credit purchases ordered by purchase_date ASC
        $this->sync_existing_credit_purchases($user_id);
        $purchases = $this->CI->db->where('user_id', $user_id)
                                  ->where('recovery_status !=', 'Fully Recovered')
                                  ->order_by('purchase_date', 'ASC')
                                  ->order_by('id', 'ASC')
                                  ->get('credit_purchases')
                                  ->result_array();

        if (empty($purchases)) {
            $this->CI->db->trans_complete();
            return ['status' => true, 'recovered' => 0.00, 'message' => 'No outstanding credit purchases.'];
        }

        $available_for_recovery = $wallet_balance;
        $total_recovered_interest = 0.00;
        $total_recovered_principal = 0.00;
        $recovered_items = [];

        // Priority: Interest first, then Principal (purchase by purchase chronologically)
        foreach ($purchases as $p) {
            if ($available_for_recovery <= 0) {
                break;
            }

            $calc = $this->calculate_purchase_interest($p, $recovery_date);
            $rem_interest  = (float)$calc['remaining_interest'];
            $rem_principal = (float)$calc['remaining_principal'];

            $rec_i = 0.00;
            $rec_p = 0.00;

            // 1. Recover Interest First
            if ($rem_interest > 0 && $available_for_recovery > 0) {
                $rec_i = min($rem_interest, $available_for_recovery);
                $available_for_recovery -= $rec_i;
            }

            // 2. Recover Principal Next
            if ($rem_principal > 0 && $available_for_recovery > 0) {
                $rec_p = min($rem_principal, $available_for_recovery);
                $available_for_recovery -= $rec_p;
            }

            $item_total = round($rec_i + $rec_p, 2);
            if ($item_total > 0) {
                $new_rec_p = round((float)$p['recovered_principal'] + $rec_p, 2);
                $new_rem_p = round((float)$p['outstanding_principal'] - $new_rec_p, 2);
                $new_rec_i = round((float)$p['interest_recovered'] + $rec_i, 2);
                $new_rem_i = round($calc['total_accrued_interest'] - $new_rec_i, 2);

                $status = ($new_rem_p <= 0.01) ? 'Fully Recovered' : 'Partially Recovered';

                // Update tf_credit_purchases
                $this->CI->db->where('id', $p['id'])->update('credit_purchases', [
                    'recovered_principal' => $new_rec_p,
                    'remaining_principal' => max(0.00, $new_rem_p),
                    'interest_accrued'    => $calc['total_accrued_interest'],
                    'interest_recovered'  => $new_rec_i,
                    'remaining_interest'  => max(0.00, $new_rem_i),
                    'recovery_status'     => $status,
                    'last_calc_date'      => $recovery_date,
                    'updated_at'          => date('Y-m-d H:i:s')
                ]);

                // Insert into tf_credit_recovery_logs
                $this->CI->db->insert('credit_recovery_logs', [
                    'user_id'               => $user_id,
                    'credit_purchase_id'    => $p['id'],
                    'wallet_id'             => $wallet_id,
                    'recovery_date'         => $recovery_date,
                    'recovered_interest'    => $rec_i,
                    'recovered_principal'   => $rec_p,
                    'total_recovered'       => $item_total,
                    'wallet_balance_before' => $wallet_balance,
                    'wallet_balance_after'  => round($available_for_recovery, 2),
                    'remarks'               => "Auto-Recovery for {$p['service_name']} (Principal ₹{$rec_p} + Interest ₹{$rec_i})",
                    'created_at'            => date('Y-m-d H:i:s')
                ]);

                $total_recovered_interest += $rec_i;
                $total_recovered_principal += $rec_p;
                $recovered_items[] = [
                    'purchase_id'  => $p['id'],
                    'service_name' => $p['service_name'],
                    'recovered_interest' => $rec_i,
                    'recovered_principal' => $rec_p,
                    'total' => $item_total
                ];
            }
        }

        $grand_recovered = round($total_recovered_interest + $total_recovered_principal, 2);

        if ($grand_recovered > 0) {
            // Insert debit in tf_purchases so Wallet balance reduces cleanly in existing CI3 logic
            $current_year = date('Y') . (date('Y') + 1);
            $this->CI->db->insert('purchases', [
                'user_id'    => $user_id,
                'service_id' => 1,
                'firm_id'    => 0,
                'service'    => 'Wallet Auto-Recovery: Credit Outstanding (Principal ₹' . number_format($total_recovered_principal, 2) . ' + Interest ₹' . number_format($total_recovered_interest, 2) . ')',
                'type'       => 'Credit Recovery',
                'date'       => $recovery_date,
                'year'       => $current_year,
                'rate'       => $grand_recovered,
                'amount'     => $grand_recovered,
                'subtotal'   => $grand_recovered,
                'gst_amount' => 0.00,
                'gst_enabled' => 0,
                'status'     => 1,
                'added_on'   => date('Y-m-d H:i:s'),
                'updated_on' => date('Y-m-d H:i:s')
            ]);

            // Save Notification
            $this->CI->load->model('Common_model', 'common');
            $this->CI->common->savenotification([
                'user_id'    => $user_id,
                'type'       => 'wallet',
                'message'    => "Auto-Recovery: ₹" . number_format($grand_recovered, 2) . " recovered from wallet for outstanding credit (Principal ₹" . number_format($total_recovered_principal, 2) . ", Interest ₹" . number_format($total_recovered_interest, 2) . ").",
                'added_on'   => date('Y-m-d H:i:s'),
                'updated_on' => date('Y-m-d H:i:s')
            ]);
        }

        $this->CI->db->trans_complete();
        $this->invalidate_user_sync($user_id);

        return [
            'status'             => true,
            'total_recovered'    => $grand_recovered,
            'recovered_principal' => $total_recovered_principal,
            'recovered_interest' => $total_recovered_interest,
            'remaining_wallet'   => round($available_for_recovery, 2),
            'items'              => $recovered_items
        ];
    }

    /**
     * Load all credit ledger sources for a user in a minimal fixed number of queries
     * and pre-index them in PHP memory.
     */
    protected function load_user_ledger_sources($user_id)
    {
        $user_id = (int)$user_id;
        $this->sync_existing_credit_purchases($user_id);

        $res_cust = $this->CI->db->select('id, credit_limit, credit_percent')
                                 ->get_where('customers', ['user_id' => $user_id]);
        $customer = ($res_cust && is_object($res_cust)) ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;

        $interest_rate = 0.0;
        if (!empty($customer) && isset($customer['credit_percent']) && $customer['credit_percent'] !== '' && $customer['credit_percent'] !== null) {
            $interest_rate = (float)$customer['credit_percent'];
        } else {
            $res_glob = $this->CI->db->get_where('credit_limit_percentage', ['status' => 1]);
            $global = ($res_glob && is_object($res_glob)) ? $res_glob->unbuffered_row('array') : null;
            if (!empty($global) && isset($global['percent'])) {
                $interest_rate = (float)$global['percent'];
            }
        }
        if ($interest_rate <= 0) {
            $interest_rate = 4.00;
        }

        $q_p = $this->CI->db->where('user_id', $user_id)
                            ->order_by('purchase_date', 'ASC')
                            ->order_by('id', 'ASC')
                            ->get('credit_purchases');
        $purchases = ($q_p && is_object($q_p)) ? $q_p->result_array() : [];

        $q_rec = $this->CI->db->where('user_id', $user_id)
                              ->order_by('recovery_date', 'ASC')
                              ->order_by('id', 'ASC')
                              ->get('credit_recovery_logs');
        $recoveries = ($q_rec && is_object($q_rec)) ? $q_rec->result_array() : [];

        $q_led = $this->CI->db->where('user_id', $user_id)
                              ->order_by('date', 'ASC')
                              ->order_by('id', 'ASC')
                              ->get('credit_limit_ledger');
        $ledger_entries = ($q_led && is_object($q_led)) ? $q_led->result_array() : [];

        $purchases_by_ym = [];
        $purchases_by_date = [];
        $recoveries_by_purchase = [];
        $recoveries_by_ym = [];
        $recoveries_by_date = [];
        $ledger_receipts_by_ym = [];
        $ledger_receipts_by_date = [];
        $ledger_payments_by_ym = [];
        $ledger_payments_by_date = [];
        $earliest_date = null;

        foreach ($recoveries as $rec) {
            $r_date = $this->normalize_valid_date($rec['recovery_date'] ?? '', $rec['created_at'] ?? null);
            $rec['_valid_date'] = $r_date;
            $r_ym = substr($r_date, 0, 7);
            $pid = (int)($rec['credit_purchase_id'] ?? 0);
            if ($pid > 0) {
                $recoveries_by_purchase[$pid][] = $rec;
            }
            $amt = (float)($rec['total_recovered'] ?? 0);
            $recoveries_by_ym[$r_ym] = ($recoveries_by_ym[$r_ym] ?? 0.0) + $amt;
            $recoveries_by_date[$r_date] = ($recoveries_by_date[$r_date] ?? 0.0) + $amt;
        }

        $timelines = [];
        foreach ($purchases as &$p) {
            $p_date = $this->normalize_valid_date($p['purchase_date'] ?? '', $p['created_at'] ?? null);
            $p['_valid_purchase_date'] = $p_date;
            $p['_valid_start_date'] = $this->normalize_valid_date($p['interest_start_date'] ?? $p_date, $p['created_at'] ?? null);
            $p_ym = substr($p_date, 0, 7);
            $c_used = (float)($p['credit_used'] ?? 0);
            $purchases_by_ym[$p_ym] = ($purchases_by_ym[$p_ym] ?? 0.0) + $c_used;
            $purchases_by_date[$p_date] = ($purchases_by_date[$p_date] ?? 0.0) + $c_used;

            if ($earliest_date === null || $p_date < $earliest_date) {
                $earliest_date = $p_date;
            }

            $pid = (int)$p['id'];
            $timelines[$pid] = $this->build_purchase_timeline($p, $recoveries_by_purchase[$pid] ?? [], $interest_rate);
        }
        unset($p);

        foreach ($ledger_entries as $le) {
            $l_date = $this->normalize_valid_date($le['date'] ?? '', $le['added_on'] ?? null);
            $l_ym = substr($l_date, 0, 7);
            $amt = (float)($le['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            if ($earliest_date === null || $l_date < $earliest_date) {
                $earliest_date = $l_date;
            }
            if (($le['type'] ?? '') === 'receipt') {
                $ledger_receipts_by_ym[$l_ym] = ($ledger_receipts_by_ym[$l_ym] ?? 0.0) + $amt;
                $ledger_receipts_by_date[$l_date] = ($ledger_receipts_by_date[$l_date] ?? 0.0) + $amt;
            } else {
                $ledger_payments_by_ym[$l_ym] = ($ledger_payments_by_ym[$l_ym] ?? 0.0) + $amt;
                $ledger_payments_by_date[$l_date] = ($ledger_payments_by_date[$l_date] ?? 0.0) + $amt;
            }
        }

        return [
            'customer'               => $customer,
            'credit_limit'           => $credit_limit,
            'interest_rate'          => $interest_rate,
            'purchases'              => $purchases,
            'recoveries_by_purchase' => $recoveries_by_purchase,
            'timelines'              => $timelines,
            'purchases_by_ym'        => $purchases_by_ym,
            'purchases_by_date'      => $purchases_by_date,
            'recoveries_by_ym'       => $recoveries_by_ym,
            'recoveries_by_date'     => $recoveries_by_date,
            'ledger_receipts_by_ym'  => $ledger_receipts_by_ym,
            'ledger_receipts_by_date'=> $ledger_receipts_by_date,
            'ledger_payments_by_ym'  => $ledger_payments_by_ym,
            'ledger_payments_by_date'=> $ledger_payments_by_date,
            'earliest_date'          => $earliest_date
        ];
    }

    /**
     * Build financial-year month list for a given FY start year (April to March).
     */
    protected function get_fy_month_defs($fy_start_year)
    {
        $fy_end_year = $fy_start_year + 1;
        return [
            sprintf('%04d-04', $fy_start_year) => ['label' => 'April ' . $fy_start_year, 'm' => 4, 'y' => $fy_start_year, 'days' => 30],
            sprintf('%04d-05', $fy_start_year) => ['label' => 'May ' . $fy_start_year, 'm' => 5, 'y' => $fy_start_year, 'days' => 31],
            sprintf('%04d-06', $fy_start_year) => ['label' => 'June ' . $fy_start_year, 'm' => 6, 'y' => $fy_start_year, 'days' => 30],
            sprintf('%04d-07', $fy_start_year) => ['label' => 'July ' . $fy_start_year, 'm' => 7, 'y' => $fy_start_year, 'days' => 31],
            sprintf('%04d-08', $fy_start_year) => ['label' => 'August ' . $fy_start_year, 'm' => 8, 'y' => $fy_start_year, 'days' => 31],
            sprintf('%04d-09', $fy_start_year) => ['label' => 'September ' . $fy_start_year, 'm' => 9, 'y' => $fy_start_year, 'days' => 30],
            sprintf('%04d-10', $fy_start_year) => ['label' => 'October ' . $fy_start_year, 'm' => 10, 'y' => $fy_start_year, 'days' => 31],
            sprintf('%04d-11', $fy_start_year) => ['label' => 'November ' . $fy_start_year, 'm' => 11, 'y' => $fy_start_year, 'days' => 30],
            sprintf('%04d-12', $fy_start_year) => ['label' => 'December ' . $fy_start_year, 'm' => 12, 'y' => $fy_start_year, 'days' => 31],
            sprintf('%04d-01', $fy_end_year)   => ['label' => 'January ' . $fy_end_year, 'm' => 1, 'y' => $fy_end_year, 'days' => 31],
            sprintf('%04d-02', $fy_end_year)   => ['label' => 'February ' . $fy_end_year, 'm' => 2, 'y' => $fy_end_year, 'days' => date('L', strtotime("$fy_end_year-02-01")) ? 29 : 28],
            sprintf('%04d-03', $fy_end_year)   => ['label' => 'March ' . $fy_end_year, 'm' => 3, 'y' => $fy_end_year, 'days' => 31],
        ];
    }

    /**
     * Compute FY ledger completely in memory from preloaded sources.
     */
    protected function compute_fy_ledger_from_sources($fy_start_year, $sources)
    {
        $fy_end_year = $fy_start_year + 1;
        $today = date('Y-m-d');
        $current_ym = date('Y-m');

        $earliest_date = $sources['earliest_date'] ?: sprintf('%04d-04-01', $fy_start_year);
        $e_ts = strtotime($earliest_date);
        $overall_start_year = ($e_ts !== false) ? (int)date('Y', $e_ts) : $fy_start_year;
        if ($e_ts !== false && (int)date('m', $e_ts) < 4) {
            $overall_start_year--;
        }
        // Guard against bogus historical dates (e.g., 0000-00-00 / 1970-01-01)
        if ($overall_start_year < 2015) {
            $overall_start_year = $fy_start_year;
        }
        $overall_start_year = min($overall_start_year, $fy_start_year);

        $running_closing = 0.00;
        for ($yr = $overall_start_year; $yr < $fy_start_year; $yr++) {
            $yr_months = $this->get_fy_month_defs($yr);
            foreach ($yr_months as $ym => $minfo) {
                $m_start = sprintf('%s-01', $ym);
                $m_end   = sprintf('%s-%02d', $ym, $minfo['days']);

                $p_amt   = round(($sources['purchases_by_ym'][$ym] ?? 0.0) + ($sources['ledger_payments_by_ym'][$ym] ?? 0.0), 2);
                $r_amt   = round($sources['ledger_receipts_by_ym'][$ym] ?? 0.0, 2);
                $rec_amt = round($sources['recoveries_by_ym'][$ym] ?? 0.0, 2);

                $interest = 0.00;
                $effective_end = ($m_end < $today) ? $m_end : $today;
                if ($effective_end >= $m_start) {
                    $int_raw = 0.0;
                    foreach ($sources['timelines'] as $tl) {
                        $res = $this->calculate_timeline_period_interest($tl, $m_start, $effective_end);
                        $int_raw += $res['interest'];
                    }
                    $interest = round($int_raw, 2);
                }

                $running_closing = max(0.00, round($running_closing + $p_amt - $r_amt - $rec_amt + $interest, 2));
            }
        }

        $fy_months = $this->get_fy_month_defs($fy_start_year);
        $table_rows = [];
        $total_purchases = 0.00;
        $total_receipts = 0.00;
        $total_recoveries = 0.00;
        $total_interest = 0.00;

        foreach ($fy_months as $ym => $minfo) {
            $m_start = sprintf('%s-01', $ym);
            $m_end   = sprintf('%s-%02d', $ym, $minfo['days']);

            $opening = $running_closing;
            $p_amt   = round(($sources['purchases_by_ym'][$ym] ?? 0.0) + ($sources['ledger_payments_by_ym'][$ym] ?? 0.0), 2);
            $r_amt   = round($sources['ledger_receipts_by_ym'][$ym] ?? 0.0, 2);
            $rec_amt = round($sources['recoveries_by_ym'][$ym] ?? 0.0, 2);

            $total = round($opening + $p_amt, 2);
            $raw_balance = round($total - $r_amt - $rec_amt, 2);

            $interest = 0.00;
            $effective_end = ($m_end < $today) ? $m_end : $today;
            if ($effective_end >= $m_start) {
                $int_raw = 0.0;
                foreach ($sources['timelines'] as $tl) {
                    $res = $this->calculate_timeline_period_interest($tl, $m_start, $effective_end);
                    $int_raw += $res['interest'];
                }
                $interest = round($int_raw, 2);
            }

            $closing = max(0.00, round($raw_balance + $interest, 2));
            $running_closing = $closing;

            $total_purchases += $p_amt;
            $total_receipts += $r_amt;
            $total_recoveries += $rec_amt;
            $total_interest += $interest;

            $table_rows[] = [
                'ym'                  => $ym,
                'month_label'         => $minfo['label'],
                'opening_outstanding' => $opening,
                'purchase_amount'     => $p_amt,
                'receipt_amount'      => $r_amt,
                'total'               => $total,
                'wallet_recovery'     => $rec_amt,
                'balance'             => max(0.00, $raw_balance),
                'panel_interest'      => $interest,
                'closing_outstanding' => $closing,
                'is_current'          => ($ym === $current_ym),
                'is_past'             => ($ym <= $current_ym)
            ];
        }

        // Compute current principal/interest KPIs from already-loaded purchases & recoveries
        $out_principal = 0.00;
        $out_interest = 0.00;
        foreach ($sources['purchases'] as $p) {
            if (($p['recovery_status'] ?? '') === 'Fully Recovered') {
                continue;
            }
            $pid = (int)$p['id'];
            $calc = $this->calculate_purchase_interest($p, $today, $sources['recoveries_by_purchase'][$pid] ?? []);
            $out_principal += (float)$calc['remaining_principal'];
            $out_interest += (float)$calc['remaining_interest'];
        }
        $out_principal = round($out_principal, 2);
        $out_interest = round($out_interest, 2);
        $total_outstanding = round($out_principal + $out_interest, 2);

        $credit_limit = $sources['credit_limit'];
        $available_credit = max(0.00, round($credit_limit - $out_principal, 2));

        return [
            'fy_label'              => $fy_start_year . '-' . $fy_end_year,
            'fy_start_year'         => $fy_start_year,
            'fy_end_year'           => $fy_end_year,
            'credit_limit'          => $credit_limit,
            'interest_rate'         => $sources['interest_rate'],
            'available_credit'      => $available_credit,
            'used_credit'           => $out_principal,
            'outstanding_principal' => $out_principal,
            'accrued_interest'      => $out_interest,
            'total_outstanding'     => $total_outstanding,
            'summary' => [
                'total_purchases'     => round($total_purchases, 2),
                'total_receipts'      => round($total_receipts, 2),
                'total_recoveries'    => round($total_recoveries, 2),
                'total_interest'      => round($total_interest, 2),
                'closing_outstanding' => $running_closing
            ],
            'rows' => $table_rows
        ];
    }

    /**
     * Complete Financial-Year-Wise Credit Ledger (April to March)
     * Continuous carry-forward, never resets at month or year boundary.
     */
    public function get_fy_credit_ledger($user_id, $fy_start_year = null)
    {
        $user_id = (int)$user_id;
        if (empty($fy_start_year)) {
            $fy_start_year = (date('m') >= 4) ? (int)date('Y') : ((int)date('Y') - 1);
        } else {
            $fy_start_year = (int)$fy_start_year;
        }

        $sources = $this->load_user_ledger_sources($user_id);
        return $this->compute_fy_ledger_from_sources($fy_start_year, $sources);
    }

    /**
     * Get purchase-wise modal details for a specific month
     */
    public function get_month_purchase_details($user_id, $year_month, $sources = null)
    {
        $user_id = (int)$user_id;
        if ($sources === null) {
            $sources = $this->load_user_ledger_sources($user_id);
        }

        $m_start = sprintf('%s-01', $year_month);
        $m_end   = date('Y-m-t', strtotime($m_start));
        $today = date('Y-m-d');
        $effective_end = ($m_end < $today) ? $m_end : $today;

        $details = [];

        foreach ($sources['purchases'] as $p) {
            $p_date = $p['_valid_purchase_date'];
            if ($p_date > $m_end) {
                continue;
            }

            $pid = (int)$p['id'];
            $p_start = $p['_valid_start_date'];
            $recoveries = $sources['recoveries_by_purchase'][$pid] ?? [];
            $tl = $sources['timelines'][$pid];

            $recovered_in_month_p = 0.00;
            $recovery_dates = [];
            foreach ($recoveries as $rec) {
                if (substr($rec['_valid_date'], 0, 7) === $year_month) {
                    $recovered_in_month_p += (float)$rec['recovered_principal'];
                    $recovery_dates[] = $rec['_valid_date'];
                }
            }

            $period_res = ($effective_end >= $m_start)
                ? $this->calculate_timeline_period_interest($tl, $m_start, $effective_end)
                : ['interest' => 0.0, 'days' => 0];
            $unpaid_days_in_month = $period_res['days'];
            $month_interest = $period_res['interest'];

            $overall_calc = $this->calculate_purchase_interest($p, $today, $recoveries);

            if (($p['recovery_status'] ?? '') === 'Fully Recovered' && $unpaid_days_in_month === 0 && $recovered_in_month_p == 0 && substr($p_date, 0, 7) !== $year_month) {
                continue;
            }

            $details[] = [
                'purchase_id'               => $pid,
                'purchase_date'             => $p_date,
                'service_name'              => $p['service_name'],
                'purchase_amount'           => (float)$p['purchase_amount'],
                'wallet_deduction'          => (float)$p['wallet_deduction'],
                'credit_used'               => (float)$p['credit_used'],
                'due_date'                  => $p['due_date'],
                'interest_start_date'       => $p_start,
                'interest_rate_percent'     => $tl['rate'],
                'eligible_unpaid_days'      => $unpaid_days_in_month,
                'total_eligible_days'       => $overall_calc['eligible_days'],
                'month_accrued_interest'    => round($month_interest, 2),
                'total_accrued_interest'    => $overall_calc['total_accrued_interest'],
                'recovery_date'             => !empty($recovery_dates) ? implode(', ', array_unique($recovery_dates)) : '—',
                'recovered_principal'       => (float)$p['recovered_principal'],
                'month_recovered_principal' => $recovered_in_month_p,
                'remaining_principal'       => (float)$p['remaining_principal'],
                'remaining_interest'        => $overall_calc['remaining_interest'],
                'recovery_status'           => $p['recovery_status']
            ];
        }

        return $details;
    }

    /**
     * Get complete day-wise ledger breakdown for a specific month
     * Computed in memory from preloaded user sources for full consistency with FY table.
     */
    public function get_month_daily_ledger($user_id, $year_month)
    {
        $user_id = (int)$user_id;
        if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$year_month, $m_parts)) {
            return ['status' => false, 'message' => 'Invalid year-month format'];
        }
        $y = (int)$m_parts[1];
        $m = (int)$m_parts[2];
        $fy_start_year = ($m >= 4) ? $y : ($y - 1);

        $sources = $this->load_user_ledger_sources($user_id);
        $fy_ledger = $this->compute_fy_ledger_from_sources($fy_start_year, $sources);

        $target_row = null;
        foreach ($fy_ledger['rows'] as $r) {
            if ($r['ym'] === $year_month) {
                $target_row = $r;
                break;
            }
        }

        if (!$target_row) {
            return [
                'status' => false,
                'message' => 'Month not found in financial year'
            ];
        }

        $days_in_month = (int)date('t', strtotime($year_month . '-01'));
        $running_exact = (float)$target_row['opening_outstanding'];
        $today = date('Y-m-d');

        $days = [];
        $total_p = 0.00;
        $total_r = 0.00;
        $total_rec = 0.00;
        $total_int_raw = 0.00;

        for ($day = 1; $day <= $days_in_month; $day++) {
            $cur_date = sprintf('%s-%02d', $year_month, $day);
            $is_future = ($cur_date > $today);

            $p_amt = round(($sources['purchases_by_date'][$cur_date] ?? 0.0) + ($sources['ledger_payments_by_date'][$cur_date] ?? 0.0), 2);
            $r_amt = round($sources['ledger_receipts_by_date'][$cur_date] ?? 0.0, 2);
            $rec_amt = round($sources['recoveries_by_date'][$cur_date] ?? 0.0, 2);

            $day_opening = max(0.00, round($running_exact, 2));
            $day_total = round($running_exact + $p_amt, 2);
            $day_balance_raw = $running_exact + $p_amt - $r_amt - $rec_amt;

            $daily_interest_raw = 0.0;
            if (!$is_future) {
                foreach ($sources['timelines'] as $tl) {
                    if ($cur_date <= $tl['p_start']) {
                        continue;
                    }
                    foreach ($tl['segments'] as $seg) {
                        if ($seg['principal'] > 0 && $cur_date > $seg['start'] && $cur_date <= $seg['end']) {
                            $daily_interest_raw += $seg['principal'] * $tl['daily_rate'];
                            break;
                        }
                    }
                }
            }

            $daily_interest = round($daily_interest_raw, 2);
            $running_exact = $day_balance_raw + $daily_interest_raw;
            $closing = max(0.00, round($running_exact, 2));

            $has_activity = ($p_amt > 0 || $r_amt > 0 || $rec_amt > 0 || $daily_interest > 0);

            $days[] = [
                'date'           => $cur_date,
                'date_formatted' => date('d M Y', strtotime($cur_date)),
                'day'            => $day,
                'opening'        => $day_opening,
                'purchase'       => $p_amt,
                'receipt'        => $r_amt,
                'total'          => max(0.00, $day_total),
                'recovery'       => round($rec_amt + $r_amt, 2),
                'balance'        => max(0.00, round($day_balance_raw, 2)),
                'panel_interest' => $daily_interest,
                'closing'        => $closing,
                'has_activity'   => $has_activity,
                'is_today'       => ($cur_date === $today),
                'is_future'      => $is_future
            ];

            $total_p += $p_amt;
            $total_r += $r_amt;
            $total_rec += $rec_amt;
            $total_int_raw += $daily_interest_raw;
        }

        $purchases = $this->get_month_purchase_details($user_id, $year_month, $sources);

        return [
            'status'         => true,
            'month_label'    => $target_row['month_label'],
            'ym'             => $year_month,
            'month_summary'  => [
                'opening'    => $target_row['opening_outstanding'],
                'purchases'  => round($total_p, 2),
                'receipts'   => round($total_r, 2),
                'total'      => $target_row['total'],
                'recoveries' => round($total_rec + $total_r, 2),
                'balance'    => $target_row['balance'],
                'interest'   => $target_row['panel_interest'],
                'closing'    => $target_row['closing_outstanding']
            ],
            'days'           => $days,
            'purchases'      => $purchases
        ];
    }
}
