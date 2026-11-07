<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Credit_service
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model('Wallet_model', 'wallet');
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
        if (empty($as_of_date)) {
            $as_of_date = date('Y-m-d');
        }

        $this->sync_existing_credit_purchases($user_id);
        $purchases = $this->CI->db->get_where('credit_purchases', [
            'user_id' => $user_id,
            'recovery_status !=' => 'Fully Recovered'
        ])->result_array();

        $total_principal = 0.00;
        $total_interest = 0.00;

        foreach ($purchases as $p) {
            $calc = $this->calculate_purchase_interest($p, $as_of_date);
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
     * into tf_credit_purchases so historical records are intact
     */
    public function sync_existing_credit_purchases($user_id)
    {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return;

        // 1. From tf_purchases where type = 'Credit limit'
        $purchases = $this->CI->db->select('id, date, amount, service, service_id, added_on, status')
                                  ->where('user_id', $user_id)
                                  ->where('type', 'Credit limit')
                                  ->where('status >=', 0)
                                  ->get('purchases')
                                  ->result_array();

        $rate = $this->get_interest_rate($user_id);

        foreach ($purchases as $p) {
            $exists = $this->CI->db->get_where('credit_purchases', [
                'user_id'     => $user_id,
                'purchase_id' => $p['id']
            ])->num_rows() > 0;

            if (!$exists) {
                $p_date = !empty($p['date']) ? $p['date'] : date('Y-m-d', strtotime($p['added_on']));
                $amt = (float)$p['amount'];
                $this->CI->db->insert('credit_purchases', [
                    'user_id'               => $user_id,
                    'purchase_id'           => $p['id'],
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
                    'created_at'            => date('Y-m-d H:i:s', strtotime($p['added_on'] ?? 'now')),
                    'updated_at'            => date('Y-m-d H:i:s')
                ]);
            }
        }

        // 2. From tf_acc_payment where payment_mode = 'Credit Limit' and status = 1
        $acc_payments = $this->CI->db->select('id, date, amount, acc_date, added_on')
                                     ->where('user_id', $user_id)
                                     ->where('payment_mode', 'Credit Limit')
                                     ->where('status', 1)
                                     ->get('acc_payment')
                                     ->result_array();

        foreach ($acc_payments as $ap) {
            $exists = $this->CI->db->get_where('credit_purchases', [
                'user_id'     => $user_id,
                'purchase_id' => $ap['id'],
                'service_name LIKE' => 'Accountancy%'
            ])->num_rows() > 0;

            if (!$exists) {
                $p_date = !empty($ap['date']) ? $ap['date'] : date('Y-m-d', strtotime($ap['added_on']));
                $amt = (float)$ap['amount'];
                $this->CI->db->insert('credit_purchases', [
                    'user_id'               => $user_id,
                    'purchase_id'           => $ap['id'],
                    'service_id'            => 1,
                    'service_name'          => 'Accountancy (' . (!empty($ap['acc_date']) ? $ap['acc_date'] : $p_date) . ')',
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
                    'created_at'            => date('Y-m-d H:i:s', strtotime($ap['added_on'] ?? 'now')),
                    'updated_at'            => date('Y-m-d H:i:s')
                ]);
            }
        }
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
        $purchase_date   = !empty($data['purchase_date']) ? $data['purchase_date'] : date('Y-m-d');
        $purchase_id     = !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null;
        $due_date        = !empty($data['due_date']) ? $data['due_date'] : $purchase_date;

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
     * Daily Interest = Outstanding Principal × (Interest Percentage / 100) / 30
     * Total Interest = Daily Interest × Eligible Unpaid Days
     */
    public function calculate_purchase_interest($purchase, $as_of_date = null)
    {
        if (empty($as_of_date)) {
            $as_of_date = date('Y-m-d');
        }

        $purchase_date = $purchase['interest_start_date'] ?? $purchase['purchase_date'];
        $rate = (float)($purchase['interest_rate_percent'] ?? 4.00);
        $daily_rate = ($rate / 100.0) / 30.0;

        $principal = (float)$purchase['outstanding_principal'];
        $remaining_principal = (float)$purchase['remaining_principal'];
        $interest_recovered = (float)($purchase['interest_recovered'] ?? 0.00);

        $purchase_id = $purchase['id'] ?? 0;

        // Fetch all recoveries for this specific purchase ordered by recovery_date ASC
        $recoveries = [];
        if (!empty($purchase_id)) {
            $recoveries = $this->CI->db->order_by('recovery_date', 'ASC')->get_where('credit_recovery_logs', [
                'credit_purchase_id' => $purchase_id
            ])->result_array();
        }

        $total_interest_accrued = 0.00;
        $current_principal = $principal;
        $last_date = $purchase_date;

        if (!empty($recoveries)) {
            foreach ($recoveries as $rec) {
                $rec_date = $rec['recovery_date'];
                if ($rec_date > $as_of_date) {
                    $rec_date = $as_of_date;
                }

                if ($rec_date > $last_date && $current_principal > 0) {
                    $days = (strtotime($rec_date) - strtotime($last_date)) / 86400;
                    $seg_interest = $current_principal * $daily_rate * $days;
                    $total_interest_accrued += $seg_interest;
                }

                $current_principal -= (float)$rec['recovered_principal'];
                if ($current_principal < 0) $current_principal = 0;
                $last_date = $rec_date;

                if ($last_date >= $as_of_date) {
                    break;
                }
            }
        }

        // Remaining active days up to as_of_date
        if ($as_of_date > $last_date && $current_principal > 0) {
            $days = (strtotime($as_of_date) - strtotime($last_date)) / 86400;
            $seg_interest = $current_principal * $daily_rate * $days;
            $total_interest_accrued += $seg_interest;
        }

        $total_interest_accrued = round($total_interest_accrued, 2);
        $remaining_interest = max(0.00, round($total_interest_accrued - $interest_recovered, 2));

        $total_days = 0;
        if (strtotime($as_of_date) > strtotime($purchase_date)) {
            $total_days = (int)((strtotime($as_of_date) - strtotime($purchase_date)) / 86400);
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
     * Calculate interest accrued during a specific month across all purchases for a user
     */
    public function calculate_month_interest_accrued($user_id, $month_start, $month_end)
    {
        $effective_end = $month_end;

        if ($effective_end < $month_start) {
            return 0.00;
        }

        $this->sync_existing_credit_purchases($user_id);
        $purchases = $this->CI->db->where('user_id', $user_id)
                                  ->where('purchase_date <=', $effective_end)
                                  ->get('credit_purchases')
                                  ->result_array();

        if (empty($purchases)) {
            return 0.00;
        }

        $month_interest = 0.00;

        foreach ($purchases as $p) {
            $p_start = $p['interest_start_date'] ?? $p['purchase_date'];
            $rate = (float)($p['interest_rate_percent'] ?? 4.00);
            $daily_rate = ($rate / 100.0) / 30.0;

            // Fetch recoveries for this purchase
            $recoveries = $this->CI->db->where('credit_purchase_id', $p['id'])
                                       ->order_by('recovery_date', 'ASC')
                                       ->get('credit_recovery_logs')
                                       ->result_array();

            // Timeline of principal balances
            $timeline = [];
            $cur_p = (float)$p['outstanding_principal'];
            $cur_date = $p_start;

            if (!empty($recoveries)) {
                foreach ($recoveries as $rec) {
                    $r_date = $rec['recovery_date'];
                    if ($r_date > $cur_date) {
                        $timeline[] = ['start' => $cur_date, 'end' => $r_date, 'principal' => $cur_p];
                    }
                    $cur_p -= (float)$rec['recovered_principal'];
                    if ($cur_p < 0) $cur_p = 0;
                    $cur_date = $r_date;
                }
            }

            if ($cur_p > 0) {
                $timeline[] = ['start' => $cur_date, 'end' => $effective_end, 'principal' => $cur_p];
            }

            // Intersect each timeline segment with [month_start, effective_end]
            foreach ($timeline as $seg) {
                $s = max($seg['start'], $month_start);
                $e = min($seg['end'], $effective_end);
                if ($e > $s && $seg['principal'] > 0) {
                    $days = (strtotime($e) - strtotime($s)) / 86400;
                    $month_interest += $seg['principal'] * $daily_rate * $days;
                }
            }
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
     * Complete Financial-Year-Wise Credit Ledger (April to March)
     * Continuous carry-forward, never resets at month or year boundary.
     * Columns:
     * 1. Month
     * 2. Opening Outstanding
     * 3. Package / Service Purchase
     * 4. Receipt
     * 5. Total = Opening + Purchase + Receipt
     * 6. Payment / Wallet Recovery
     * 7. Balance = Total - Recovery
     * 8. Panel Interest / Late Fee
     * 9. Closing Outstanding = Balance + Panel Interest
     * 10. View Details
     */
    public function get_fy_credit_ledger($user_id, $fy_start_year = null)
    {
        $user_id = (int)$user_id;
        if (empty($fy_start_year)) {
            $fy_start_year = (date('m') >= 4) ? (int)date('Y') : ((int)date('Y') - 1);
        } else {
            $fy_start_year = (int)$fy_start_year;
        }
        $fy_end_year = $fy_start_year + 1;

        $this->sync_existing_credit_purchases($user_id);

        $res_cust = $this->CI->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;
        $interest_rate = $this->get_interest_rate($user_id);

        // Find earliest transaction date for this user to calculate continuous opening balance
        $earliest_p = $this->CI->db->select_min('purchase_date')->where('user_id', $user_id)->get('credit_purchases')->unbuffered_row();
        $earliest_date = !empty($earliest_p->purchase_date) ? $earliest_p->purchase_date : sprintf('%04d-04-01', $fy_start_year);

        $overall_start_year = (int)date('Y', strtotime($earliest_date));
        if ((int)date('m', strtotime($earliest_date)) < 4) {
            $overall_start_year--;
        }
        $overall_start_year = min($overall_start_year, $fy_start_year);

        // Months definition for the requested FY
        $fy_months = [
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

        // Carry forward from overall_start_year to fy_start_year
        $running_closing = 0.00;
        $today = date('Y-m-d');

        for ($yr = $overall_start_year; $yr <= $fy_start_year; $yr++) {
            $next_yr = $yr + 1;
            $yr_months = [
                sprintf('%04d-04', $yr) => ['m' => 4, 'y' => $yr],
                sprintf('%04d-05', $yr) => ['m' => 5, 'y' => $yr],
                sprintf('%04d-06', $yr) => ['m' => 6, 'y' => $yr],
                sprintf('%04d-07', $yr) => ['m' => 7, 'y' => $yr],
                sprintf('%04d-08', $yr) => ['m' => 8, 'y' => $yr],
                sprintf('%04d-09', $yr) => ['m' => 9, 'y' => $yr],
                sprintf('%04d-10', $yr) => ['m' => 10, 'y' => $yr],
                sprintf('%04d-11', $yr) => ['m' => 11, 'y' => $yr],
                sprintf('%04d-12', $yr) => ['m' => 12, 'y' => $yr],
                sprintf('%04d-01', $next_yr) => ['m' => 1, 'y' => $next_yr],
                sprintf('%04d-02', $next_yr) => ['m' => 2, 'y' => $next_yr],
                sprintf('%04d-03', $next_yr) => ['m' => 3, 'y' => $next_yr],
            ];

            foreach ($yr_months as $ym => $minfo) {
                if ($yr == $fy_start_year) {
                    break; // Process the requested FY months in main loop
                }

                $m_start = sprintf('%s-01', $ym);
                $m_end   = date('Y-m-t', strtotime($m_start));

                $opening = $running_closing;

                // Purchases in this month
                $p_amt = (float)$this->CI->db->select_sum('credit_used')
                                             ->where('user_id', $user_id)
                                             ->where("DATE_FORMAT(purchase_date, '%Y-%m') =", $ym)
                                             ->get('credit_purchases')
                                             ->unbuffered_row()->credit_used;

                // Receipts in this month
                $r_amt = (float)$this->CI->db->select_sum('amount')
                                             ->where('user_id', $user_id)
                                             ->where('type', 'receipt')
                                             ->where("DATE_FORMAT(date, '%Y-%m') =", $ym)
                                             ->get('credit_limit_ledger')
                                             ->unbuffered_row()->amount;

                $total = round($opening + $p_amt + $r_amt, 2);

                // Recoveries in this month
                $rec_amt = (float)$this->CI->db->select_sum('total_recovered')
                                               ->where('user_id', $user_id)
                                               ->where("DATE_FORMAT(recovery_date, '%Y-%m') =", $ym)
                                               ->get('credit_recovery_logs')
                                               ->unbuffered_row()->total_recovered;

                $balance = round($total - $rec_amt, 2);

                // Panel interest accrued in this month
                $interest = $this->calculate_month_interest_accrued($user_id, $m_start, $m_end);

                $closing = round($balance + $interest, 2);
                $running_closing = $closing;
            }
        }

        // Now calculate all 12 months for the requested Financial Year
        $table_rows = [];
        $total_purchases = 0.00;
        $total_receipts = 0.00;
        $total_recoveries = 0.00;
        $total_interest = 0.00;

        foreach ($fy_months as $ym => $minfo) {
            $m_start = sprintf('%s-01', $ym);
            $m_end   = date('Y-m-t', strtotime($m_start));

            $opening = $running_closing;

            // Package / Service Purchases funded by credit in this month
            $p_amt = (float)$this->CI->db->select_sum('credit_used')
                                         ->where('user_id', $user_id)
                                         ->where("DATE_FORMAT(purchase_date, '%Y-%m') =", $ym)
                                         ->get('credit_purchases')
                                         ->unbuffered_row()->credit_used;

            // Manual Credit Receipts in this month
            $r_amt = (float)$this->CI->db->select_sum('amount')
                                         ->where('user_id', $user_id)
                                         ->where('type', 'receipt')
                                         ->where("DATE_FORMAT(date, '%Y-%m') =", $ym)
                                         ->get('credit_limit_ledger')
                                         ->unbuffered_row()->amount;

            $total = round($opening + $p_amt + $r_amt, 2);

            // Payment / Wallet Recovery in this month
            $rec_amt = (float)$this->CI->db->select_sum('total_recovered')
                                           ->where('user_id', $user_id)
                                           ->where("DATE_FORMAT(recovery_date, '%Y-%m') =", $ym)
                                           ->get('credit_recovery_logs')
                                           ->unbuffered_row()->total_recovered;

            $balance = round($total - $rec_amt, 2);

            // Panel interest accrued in this month
            $interest = $this->calculate_month_interest_accrued($user_id, $m_start, $m_end);

            $closing = round($balance + $interest, 2);
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
                'balance'             => $balance,
                'panel_interest'      => $interest,
                'closing_outstanding' => $closing,
                'is_current'          => ($ym == date('Y-m')),
                'is_past'             => ($ym <= date('Y-m'))
            ];
        }

        $out_stat = $this->get_total_outstanding($user_id);

        return [
            'fy_label'            => $fy_start_year . '-' . $fy_end_year,
            'fy_start_year'       => $fy_start_year,
            'fy_end_year'         => $fy_end_year,
            'credit_limit'        => $credit_limit,
            'interest_rate'       => $interest_rate,
            'available_credit'    => $this->get_available_credit($user_id),
            'used_credit'         => round($credit_limit - $this->get_available_credit($user_id), 2),
            'outstanding_principal' => $out_stat['total_principal'],
            'accrued_interest'    => $out_stat['total_interest'],
            'total_outstanding'   => $out_stat['total_outstanding'],
            'summary' => [
                'total_purchases'  => round($total_purchases, 2),
                'total_receipts'   => round($total_receipts, 2),
                'total_recoveries' => round($total_recoveries, 2),
                'total_interest'   => round($total_interest, 2),
                'closing_outstanding' => $running_closing
            ],
            'rows' => $table_rows
        ];
    }

    /**
     * Get purchase-wise modal details for a specific month
     */
    public function get_month_purchase_details($user_id, $year_month)
    {
        $user_id = (int)$user_id;
        $m_start = sprintf('%s-01', $year_month);
        $m_end   = date('Y-m-t', strtotime($m_start));

        $this->sync_existing_credit_purchases($user_id);

        // Fetch all credit purchases relevant to this month (purchased on or before month end, active during month)
        $purchases = $this->CI->db->where('user_id', $user_id)
                                  ->where('purchase_date <=', $m_end)
                                  ->order_by('purchase_date', 'ASC')
                                  ->get('credit_purchases')
                                  ->result_array();

        $details = [];
        $today = date('Y-m-d');
        $effective_end = $m_end;

        foreach ($purchases as $p) {
            $p_start = $p['interest_start_date'] ?? $p['purchase_date'];
            $rate = (float)($p['interest_rate_percent'] ?? 4.00);
            $daily_rate = ($rate / 100.0) / 30.0;

            // Fetch recoveries
            $recoveries = $this->CI->db->where('credit_purchase_id', $p['id'])
                                       ->order_by('recovery_date', 'ASC')
                                       ->get('credit_recovery_logs')
                                       ->result_array();

            $recovered_in_month_p = 0.00;
            $recovered_in_month_i = 0.00;
            $recovery_dates = [];

            if (!empty($recoveries)) {
                foreach ($recoveries as $rec) {
                    if (date('Y-m', strtotime($rec['recovery_date'])) == $year_month) {
                        $recovered_in_month_p += (float)$rec['recovered_principal'];
                        $recovered_in_month_i += (float)$rec['recovered_interest'];
                        $recovery_dates[] = $rec['recovery_date'];
                    }
                }
            }

            // Calculate interest accrued during this specific month for this purchase
            $month_interest = 0.00;
            $unpaid_days_in_month = 0;

            // Timeline
            $timeline = [];
            $cur_p = (float)$p['outstanding_principal'];
            $cur_date = $p_start;

            if (!empty($recoveries)) {
                foreach ($recoveries as $rec) {
                    $r_date = $rec['recovery_date'];
                    if ($r_date > $cur_date) {
                        $timeline[] = ['start' => $cur_date, 'end' => $r_date, 'principal' => $cur_p];
                    }
                    $cur_p -= (float)$rec['recovered_principal'];
                    if ($cur_p < 0) $cur_p = 0;
                    $cur_date = $r_date;
                }
            }
            if ($cur_p > 0) {
                $timeline[] = ['start' => $cur_date, 'end' => $effective_end, 'principal' => $cur_p];
            }

            foreach ($timeline as $seg) {
                $s = max($seg['start'], $m_start);
                $e = min($seg['end'], $effective_end);
                if ($e > $s && $seg['principal'] > 0) {
                    $d = (int)((strtotime($e) - strtotime($s)) / 86400);
                    $unpaid_days_in_month += $d;
                    $month_interest += $seg['principal'] * $daily_rate * $d;
                }
            }

            // Total overall stats for purchase up to today
            $overall_calc = $this->calculate_purchase_interest($p, $today);

            // Skip purchase if it was fully recovered before this month started and has 0 activity
            if ($p['recovery_status'] == 'Fully Recovered' && $unpaid_days_in_month == 0 && $recovered_in_month_p == 0 && date('Y-m', strtotime($p['purchase_date'])) != $year_month) {
                continue;
            }

            $details[] = [
                'purchase_id'           => $p['id'],
                'purchase_date'         => $p['purchase_date'],
                'service_name'          => $p['service_name'],
                'purchase_amount'       => (float)$p['purchase_amount'],
                'wallet_deduction'      => (float)$p['wallet_deduction'],
                'credit_used'           => (float)$p['credit_used'],
                'due_date'              => $p['due_date'],
                'interest_start_date'   => $p_start,
                'interest_rate_percent' => $rate,
                'eligible_unpaid_days'  => $unpaid_days_in_month,
                'total_eligible_days'   => $overall_calc['eligible_days'],
                'month_accrued_interest' => round($month_interest, 2),
                'total_accrued_interest' => $overall_calc['total_accrued_interest'],
                'recovery_date'         => !empty($recovery_dates) ? implode(', ', array_unique($recovery_dates)) : '—',
                'recovered_principal'   => (float)$p['recovered_principal'],
                'month_recovered_principal' => $recovered_in_month_p,
                'remaining_principal'   => (float)$p['remaining_principal'],
                'remaining_interest'    => $overall_calc['remaining_interest'],
                'recovery_status'       => $p['recovery_status']
            ];
        }

        return $details;
    }

    /**
     * Get complete day-wise ledger breakdown for a specific month
     * Shows all days (1 to 28/29/30/31) with daily opening, purchases, receipts,
     * total, payment/recovery, balance, daily panel interest, and daily closing.
     */
    public function get_month_daily_ledger($user_id, $year_month)
    {
        $user_id = (int)$user_id;
        $m_parts = explode('-', $year_month);
        $y = (int)$m_parts[0];
        $m = (int)$m_parts[1];
        $fy_start_year = ($m >= 4) ? $y : ($y - 1);

        $fy_ledger = $this->get_fy_credit_ledger($user_id, $fy_start_year);
        $target_row = null;
        foreach ($fy_ledger['rows'] as $r) {
            if ($r['ym'] == $year_month) {
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
        $running_open = (float)$target_row['opening_outstanding'];
        $rate = $this->get_interest_rate($user_id);
        $daily_rate = ($rate / 100.0) / 30.0;
        $today = date('Y-m-d');

        $days = [];
        $total_p = 0.00;
        $total_r = 0.00;
        $total_rec = 0.00;
        $total_int = 0.00;

        for ($day = 1; $day <= $days_in_month; $day++) {
            $cur_date = sprintf('%s-%02d', $year_month, $day);

            // Purchases on this day
            $p_amt = (float)$this->CI->db->select_sum('credit_used')
                                  ->where('user_id', $user_id)
                                  ->where('purchase_date', $cur_date)
                                  ->get('credit_purchases')
                                  ->unbuffered_row()->credit_used;

            // Receipts on this day
            $r_amt = (float)$this->CI->db->select_sum('amount')
                                  ->where('user_id', $user_id)
                                  ->where('type', 'receipt')
                                  ->where('date', $cur_date)
                                  ->get('credit_limit_ledger')
                                  ->unbuffered_row()->amount;

            $total = round($running_open + $p_amt + $r_amt, 2);

            // Recoveries on this day
            $rec_amt = (float)$this->CI->db->select_sum('total_recovered')
                                    ->where('user_id', $user_id)
                                    ->where('recovery_date', $cur_date)
                                    ->get('credit_recovery_logs')
                                    ->unbuffered_row()->total_recovered;

            $balance = round($total - $rec_amt, 2);

            // Daily interest for active unpaid purchases on cur_date (including projected future days of month)
            $daily_interest = 0.00;
            $active_purchases = $this->CI->db->where('user_id', $user_id)
                                      ->where('purchase_date <=', $cur_date)
                                      ->get('credit_purchases')
                                      ->result_array();
            foreach ($active_purchases as $p) {
                $p_start = $p['interest_start_date'] ?? $p['purchase_date'];
                if ($cur_date > $p_start) {
                    $recs = $this->CI->db->select_sum('recovered_principal')
                                  ->where('credit_purchase_id', $p['id'])
                                  ->where('recovery_date <=', $cur_date)
                                  ->get('credit_recovery_logs')
                                  ->unbuffered_row()->recovered_principal;
                    $unpaid_p = max(0.0, (float)$p['outstanding_principal'] - (float)$recs);
                    if ($unpaid_p > 0) {
                        $daily_interest += $unpaid_p * $daily_rate;
                    }
                }
            }

            $daily_interest = round($daily_interest, 2);
            $closing = round($balance + $daily_interest, 2);

            $has_activity = ($p_amt > 0 || $r_amt > 0 || $rec_amt > 0 || $daily_interest > 0);

            $days[] = [
                'date'           => $cur_date,
                'date_formatted' => date('d M Y', strtotime($cur_date)),
                'day'            => $day,
                'opening'        => $running_open,
                'purchase'       => $p_amt,
                'receipt'        => $r_amt,
                'total'          => $total,
                'recovery'       => $rec_amt,
                'balance'        => $balance,
                'panel_interest' => $daily_interest,
                'closing'        => $closing,
                'has_activity'   => $has_activity,
                'is_today'       => ($cur_date === $today),
                'is_future'      => ($cur_date > $today)
            ];

            $total_p += $p_amt;
            $total_r += $r_amt;
            $total_rec += $rec_amt;
            $total_int += $daily_interest;

            $running_open = $closing;
        }

        $purchases = $this->get_month_purchase_details($user_id, $year_month);

        return [
            'status'         => true,
            'month_label'    => $target_row['month_label'],
            'ym'             => $year_month,
            'month_summary'  => [
                'opening'    => $target_row['opening_outstanding'],
                'purchases'  => $total_p,
                'receipts'   => $total_r,
                'total'      => round($target_row['opening_outstanding'] + $total_p + $total_r, 2),
                'recoveries' => $total_rec,
                'balance'    => round($target_row['opening_outstanding'] + $total_p + $total_r - $total_rec, 2),
                'interest'   => round($total_int, 2),
                'closing'    => round($running_open, 2)
            ],
            'days'           => $days,
            'purchases'      => $purchases
        ];
    }
}
