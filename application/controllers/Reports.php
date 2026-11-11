<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reports extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        logrequest();
        checklogin();
        //checkcookie();
        // Allow admin, employee access for admin reports, customer access for customer reports
        if ($this->session->role != 'customer' && $this->session->role != 'admin' && $this->session->role != 'superadmin' && $this->session->role != 'employee') {
            redirect('/');
        }
    }

    private function calculate_accountancy_fee($package_name, $total_turnover)
    {
        $packages = $this->master->getpackages(array('name' => $package_name, 'status' => 1), 'all');
        $regular_slabs = array();
        $per_unit_slab = null;

        foreach ($packages as $package) {
            if (
                !isset($package['turnover'], $package['rate'])
                || !is_numeric($package['turnover'])
                || !is_numeric($package['rate'])
                || (float)$package['rate'] < 0
            ) {
                continue;
            }

            if (stripos(isset($package['remarks']) ? $package['remarks'] : '', 'Per 100 Lac') !== false) {
                $per_unit_slab = $package;
                continue;
            }

            if ((float)$package['turnover'] > 0) {
                $regular_slabs[] = $package;
            }
        }

        if (empty($regular_slabs)) {
            return array(
                'status' => true,
                'package' => array('name' => $package_name),
                'fees' => 0
            );
        }

        usort($regular_slabs, function ($left, $right) {
            return (float)$left['turnover'] <=> (float)$right['turnover'];
        });

        $selected_slab = end($regular_slabs);
        foreach ($regular_slabs as $slab) {
            if ($total_turnover < (float)$slab['turnover']) {
                $selected_slab = $slab;
                break;
            }
        }

        $fees = $total_turnover > 0 ? (float)$selected_slab['rate'] : 0;
        $highest_slab = end($regular_slabs);
        $highest_slab_turnover = (float)$highest_slab['turnover'];

        if ($total_turnover > $highest_slab_turnover) {
            if (empty($per_unit_slab) || (float)$per_unit_slab['turnover'] <= 0) {
                return array(
                    'status' => true,
                    'package' => array('name' => $package_name),
                    'fees' => 0
                );
            }

            $extra_turnover = $total_turnover - $highest_slab_turnover;
            $fees = (float)$highest_slab['rate']
                + ($extra_turnover / (float)$per_unit_slab['turnover']) * (float)$per_unit_slab['rate'];
            $selected_slab = $per_unit_slab;
        }

        return array(
            'status' => true,
            'package' => $selected_slab,
            'fees' => round($fees, 2)
        );
    }

    /**
     * Get accountancy report data and calculations for a user, firm, and financial year.
     * Shared by index() (Accounting Mode), payment() (Accounting Pay), and processpayment().
     */
    private function get_accountancy_report_data($user_id, $firm_id, $year, $include_late_fee = true)
    {
        $yearval = getyearmonthvalues($year);
        $year1 = $yearval['year1'];
        $year2 = $yearval['year2'];
        $from = "$year1-04-01";
        $to = "$year2-03-31";

        $where_firm = array('user_id' => $user_id, 'firm_id' => $firm_id, 'year' => $year, 'status' => 1);
        $query = $this->db->get_where('customer_packages', $where_firm);
        if ($query->num_rows() == 0) {
            $where_fallback = array('user_id' => $user_id, 'year' => $year, 'status' => 1);
            $query = $this->db->get_where('customer_packages', $where_fallback);
        }

        if ($query->num_rows() == 0) {
            return array('status' => false, 'message' => 'Package not Active!', 'redirect' => 'package/');
        }

        $cpackage = $query->unbuffered_row('array');
        $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

        // Check if any package for this firm/year is Monthly type
        $monthly_check = $this->db->get_where('customer_packages', [
            'user_id'      => $user_id,
            'firm_id'      => $firm_id,
            'year'         => $year,
            'status'       => 1,
            'package_type' => 'Monthly'
        ])->row_array();
        if (!empty($monthly_check)) {
            $pkg_type = 'Monthly';
            $cpackage = $monthly_check;
        }

        if ($pkg_type == 'Monthly') {
            ensure_accountancy_fy_months($user_id, $firm_id, $year, 0);

            // Map all active monthly customer packages by Y-m
            $monthly_pkgs = $this->db->get_where('customer_packages', [
                'user_id'      => $user_id,
                'firm_id'      => $firm_id,
                'status'       => 1,
                'package_type' => 'Monthly'
            ])->result_array();

            // Determine true single month rate for Monthly packages (matching pendingservices logic)
            $monthly_single_rate = 0;
            if (!empty($monthly_pkgs)) {
                foreach ($monthly_pkgs as $_dp) {
                    $a = (float)($_dp['amount'] ?? 0);
                    $b = (float)($_dp['bill_amount'] ?? 0);
                    if ($a > 0 && ($monthly_single_rate == 0 || $a < $monthly_single_rate)) {
                        $monthly_single_rate = $a;
                    }
                    if ($b > 0 && ($monthly_single_rate == 0 || $b < $monthly_single_rate)) {
                        $monthly_single_rate = $b;
                    }
                }
            }
            if ($monthly_single_rate <= 0) {
                $monthly_single_rate = !empty($cpackage['amount']) ? (float)$cpackage['amount'] : 6000;
            }
            $m_rate = $monthly_single_rate;

            $monthly_pkgs_by_ym = [];
            foreach ($monthly_pkgs as $mp) {
                if (!empty($mp['purchase_date'])) {
                    $ym = date('Y-m', strtotime($mp['purchase_date']));
                    $monthly_pkgs_by_ym[$ym] = $mp;
                }
            }

            // Fetch purchases for accountancy (service_id = 1)
            $purchases_accountancy = $this->db->get_where('purchases', [
                'user_id'    => $user_id,
                'firm_id'    => $firm_id,
                'service_id' => 1
            ])->result_array();
            $purchases_by_ym = [];
            foreach ($purchases_accountancy as $pa) {
                if (!empty($pa['date'])) {
                    $ym = date('Y-m', strtotime($pa['date']));
                    $purchases_by_ym[$ym] = $pa;
                }
            }

            $user_id_escaped = $this->db->escape($user_id);
            $firm_id_escaped = $this->db->escape($firm_id);
            $from_escaped    = $this->db->escape($from);
            $to_escaped      = $this->db->escape($to);
            $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";
            $accountancy = $this->service->getturnoverswithpayment($where2);

            if (empty($accountancy)) {
            ensure_accountancy_fy_months($user_id, $firm_id, $year, $cpackage['package_id'] ?? 0);
            $accountancy = $this->service->getturnoverswithpayment($where2);
        }

        if (empty($accountancy)) {
                $now = time();
                $cur_y = (int)date('Y', $now);
                $cur_m = (int)date('n', $now);
                if ($cur_y < (int)$year1 || ($cur_y == (int)$year1 && $cur_m < 4)) {
                    $max_m_idx = 1;
                } elseif ($cur_y > (int)$year2 || ($cur_y == (int)$year2 && $cur_m >= 4)) {
                    $max_m_idx = 12;
                } else {
                    $max_m_idx = ($cur_y == (int)$year1) ? ($cur_m - 3) : ($cur_m + 9);
                }
                if ($max_m_idx < 1) $max_m_idx = 1;
                if ($max_m_idx > 12) $max_m_idx = 12;

                $accountancy = [];
                for ($i = 1; $i <= $max_m_idx; $i++) {
                    $m = ($i <= 9) ? ($i + 3) : ($i - 9);
                    $y = ($i <= 9) ? (int)$year1 : (int)$year2;
                    $m_date = sprintf('%04d-%02d-01', $y, $m);
                    $next_m = ($m == 12) ? 1 : ($m + 1);
                    $next_y = ($m == 12) ? ($y + 1) : $y;
                    $due_d = sprintf('%04d-%02d-28', $next_y, $next_m);
                    $accountancy[] = [
                        'id' => $i,
                        'user_id' => $user_id,
                        'firm_id' => $firm_id,
                        'year' => $year,
                        'date' => $m_date,
                        'turnover' => 0,
                        'other_fee' => 0,
                        'due_date' => $due_d,
                        'paid' => 0,
                        'payment_date' => null
                    ];
                }
            }

            // Filter accountancy to ONLY include months that exist in customer_packages for this Monthly package
            if (!empty($monthly_pkgs_by_ym)) {
                $filtered_accountancy = [];
                foreach ($accountancy as $acct_item) {
                    $acct_ym = !empty($acct_item['date']) ? date('Y-m', strtotime($acct_item['date'])) : '';
                    if (isset($monthly_pkgs_by_ym[$acct_ym])) {
                        $filtered_accountancy[] = $acct_item;
                    }
                }
                $accountancy = $filtered_accountancy;
            }

            $date = date('Y-m-d');
            $percent = 2 / 100;
            $total_fees = $total_other = $total_paid = $total_penalty = $total_days = 0;
            $outstanding = $total = $balance = 0;
            $total_sum = 0;
            $total_balance_sum = 0;
            $records = array();
            $unpaid_months = array();

            foreach ($accountancy as &$single) {
                $single_date = !empty($single['date']) ? $single['date'] : '';
                $ym = !empty($single_date) ? date('Y-m', strtotime($single_date)) : '';

                // Accounting Fee is strictly for turnover-based packages.
                // For Monthly package, the monthly fee is shown in Other Fee!
                $acc_fees = 0;
                $other_fee = $monthly_single_rate;
                $total_other += $other_fee;

                $gross_total = round($outstanding + $acc_fees + $other_fee, 2);

                // Check payment from acc_payment, purchases, or customer_packages
                $paid = !empty($single['paid']) ? (float)$single['paid'] : 0;
                // In monthly package, paid amount for this month should not exceed the package rate (ignores old turnover payments)
                if ($paid > $other_fee) {
                    $paid = $other_fee;
                }
                $payment_date = !empty($single['payment_date']) ? $single['payment_date'] : null;

                if ($paid <= 0 && !empty($ym) && isset($purchases_by_ym[$ym])) {
                    $paid = (float)$purchases_by_ym[$ym]['amount'];
                    if (!empty($purchases_by_ym[$ym]['date'])) {
                        $payment_date = $purchases_by_ym[$ym]['date'];
                    }
                }

                if ($paid <= 0 && !empty($ym) && isset($monthly_pkgs_by_ym[$ym])) {
                    $is_pkg_paid = !empty($monthly_pkgs_by_ym[$ym]['payment_status']) || ($monthly_pkgs_by_ym[$ym]['auto_debit_status'] ?? '') === 'Confirmed' || ($single['auto_debit_status'] ?? '') === 'Confirmed';
                    if ($is_pkg_paid) {
                        $paid = $other_fee;
                        $payment_date = !empty($monthly_pkgs_by_ym[$ym]['updated_on']) ? $monthly_pkgs_by_ym[$ym]['updated_on'] : (!empty($monthly_pkgs_by_ym[$ym]['purchase_date']) ? $monthly_pkgs_by_ym[$ym]['purchase_date'] : null);
                    }
                }

                if ($paid > $other_fee) {
                    $paid = $other_fee;
                }

                // Due date
                $due_date_raw = '';
                if (!empty($ym) && !empty($monthly_pkgs_by_ym[$ym]['expiry_date'])) {
                    $due_date_raw = $monthly_pkgs_by_ym[$ym]['expiry_date'];
                } elseif (!empty($single['due_date'])) {
                    $due_date_raw = $single['due_date'];
                }

                $is_paid_month = ($paid >= ($acc_fees + $other_fee));
                $penalty = 0;
                $display_penalty = 0;
                $days = 0;
                $display_days = 0;

                if (!empty($due_date_raw)) {
                    $due_dt = new DateTime($due_date_raw);
                    $due_dt->setTime(0, 0, 0);
                    $ref_dt = ($is_paid_month && !empty($payment_date)) ? new DateTime(date('Y-m-d', strtotime($payment_date))) : new DateTime($date);
                    $ref_dt->setTime(0, 0, 0);

                    if ($due_dt < $ref_dt) {
                        $display_days = (int)$due_dt->diff($ref_dt)->days;
                        $display_penalty = round(($percent * $gross_total / 30) * $display_days);
                        if ($display_penalty < 0) $display_penalty = 0;
                    }

                    if (!$is_paid_month && $due_dt < $ref_dt) {
                        $days = $display_days;
                        $penalty = $include_late_fee ? $display_penalty : 0;
                        $total_penalty += $penalty;
                        $total_days += $days;
                    }
                }

                if ($is_paid_month) {
                    $balance = 0;
                    $auto_debit_status_label = 'Confirmed';
                } else {
                    $balance = round($gross_total + $penalty - $paid, 2);
                    if ($balance < 0) $balance = 0;

                    if (!empty($due_date_raw)) {
                        $due_dt = new DateTime($due_date_raw);
                        $due_dt->setTime(0, 0, 0);
                        $today_dt = new DateTime($date);
                        $today_dt->setTime(0, 0, 0);
                        $interval = $today_dt->diff($due_dt);
                        $daysLeft = (int)$interval->format('%R%a');
                        if ($daysLeft > 0) {
                            $auto_debit_status_label = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left auto debit';
                        } elseif ($daysLeft === 0) {
                            $auto_debit_status_label = 'Auto debit today';
                        } else {
                            $auto_debit_status_label = 'Auto debit processing';
                        }
                    } else {
                        $auto_debit_status_label = 'Pending';
                    }
                }

                $month_str = !empty($single['date']) ? date('F-y', strtotime($single['date'])) : '--';
                $due_date_str = !empty($due_date_raw) ? date('d-m-Y F', strtotime($due_date_raw)) : '--';

                if ($auto_debit_status_label === 'Confirmed') {
                    $auto_debit_status_html = '<span class="badge bg-success">Confirmed</span>';
                } else {
                    $auto_debit_status_html = '<span class="badge bg-warning text-dark">' . $auto_debit_status_label . '</span>';
                }

                if ($auto_debit_status_label === 'Confirmed' || $paid > 0) {
                    $p_date_fmt = !empty($payment_date) ? date('d-m-Y', strtotime($payment_date)) : '';
                    $action_date_html = '<span class="text-success font-weight-bold"><i class="fa fa-check-circle"></i> Confirmed' . ($p_date_fmt ? '<br><small>' . $p_date_fmt . '</small>' : '') . '</span>';
                } else {
                    $action_date_html = '<span class="text-muted">Auto Debit</span>';
                }

                $record = array(
                    'id'                      => $single['id'],
                    'date'                    => $single['date'],
                    'month'                   => $month_str,
                    'due_date'                => $due_date_str,
                    'outstanding'             => round($outstanding, 2),
                    'gto'                     => 0,
                    'acc_fees'                => round($acc_fees, 2),
                    'other_fee'               => round($other_fee, 2),
                    'penalty'                 => round($penalty, 2),
                    'display_penalty'         => $display_penalty,
                    'gross_total'             => round($gross_total + $penalty, 2),
                    'total'                   => round($gross_total + $penalty, 2),
                    'paid'                    => round($paid, 2),
                    'balance'                 => round($balance, 2),
                    'days'                    => $days,
                    'display_days'            => $display_days,
                    'due_days'                => $days,
                    'auto_debit_status'       => $auto_debit_status_label,
                    'auto_debit_status_label' => $auto_debit_status_html,
                    'action_date'             => $action_date_html
                );

                $records[] = $record;
                if ($balance > 0) {
                    $unpaid_months[] = $record;
                }

                $total_paid += $paid;
                $total_sum += ($gross_total + $penalty);
                $total_balance_sum = $balance;
            }

            $last_month_balance = 0;
            $last_month_data = null;
            $first_month_data = null;
            $payment_month_range = '';

            if (!empty($unpaid_months)) {
                $first_month_data = reset($unpaid_months);
                $last_month_data = end($unpaid_months);
                $last_month_balance = $include_late_fee
                    ? $last_month_data['balance']
                    : array_sum(array_column($unpaid_months, 'balance'));

                if (count($unpaid_months) > 1) {
                    $payment_month_range = $first_month_data['month'] . '-' . $last_month_data['month'];
                } else {
                    $payment_month_range = $last_month_data['month'];
                }
            }

            return array(
                'status'              => true,
                'package'             => array('name' => 'Monthly Account Work', 'package_type' => 'Monthly'),
                'pkg_type'            => 'Monthly',
                'cpackage'            => $cpackage,
                'records'             => $records,
                'unpaid_months'       => $unpaid_months,
                'total_turnover'      => 0,
                'total_fees'          => $total_fees,
                'total_other'         => $total_other,
                'total_penalty'       => $total_penalty,
                'total_sum'           => $total_sum,
                'total_paid'          => $total_paid,
                'total_balance'       => $total_balance_sum,
                'total_days'          => $total_days,
                'last_month_balance'  => $last_month_balance,
                'last_month_data'     => $last_month_data,
                'first_month_data'    => $first_month_data,
                'payment_month_range' => $payment_month_range,
                'accountancy'         => $accountancy
            );
        }

        $user_id_escaped = $this->db->escape($user_id);
        $firm_id_escaped = $this->db->escape($firm_id);
        $from_escaped = $this->db->escape($from);
        $to_escaped = $this->db->escape($to);
        $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";
        $accountancy = $this->service->getturnoverswithpayment($where2);

        if (empty($accountancy)) {
            ensure_accountancy_fy_months($user_id, $firm_id, $year, $cpackage['package_id'] ?? 0);
            $accountancy = $this->service->getturnoverswithpayment($where2);
        }

        if (empty($accountancy)) {
            return array('status' => false, 'message' => 'No Data Found!', 'redirect' => 'home/');
        }

        $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
        $turnover = array_sum($turnovers);
        $total_turnover = $turnover;
        $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
        $fee_data = $this->calculate_accountancy_fee($name, $total_turnover);
        if (!$fee_data['status']) {
            return array('status' => false, 'message' => $fee_data['message'], 'redirect' => 'home/');
        }
        $package = $fee_data['package'];

        $date = date('Y-m-d');
        $percent = 2 / 100;

        $fees = $fee_data['fees'];

        $count = count($accountancy);
        $last = end($accountancy);
        if ($last['date'] == '') {
            $count--;
        }

        $activeMonthsCount = 0;
        foreach ($accountancy as $acct) {
            if (isset($acct['turnover']) && $acct['turnover'] > 0) {
                $activeMonthsCount++;
            }
        }

        $monthlyAccountsFee = $activeMonthsCount > 0 ? ($fees / $activeMonthsCount) : 0;
        $acc_fees_default = $count > 0 ? ($fees / $count) : 0;

        $total_fees = $total_other = $total_paid = $total_penalty = $total_days = 0;
        $outstanding = $total = $balance = 0;
        $total_sum = 0;
        $total_balance_sum = 0;
        $records = array();
        $unpaid_months = array();

        foreach ($accountancy as &$single) {
            $days = $paid = $penalty = 0;
            $paid = !empty($single['paid']) ? $single['paid'] : 0;

            // Outstanding should be the previous month's balance
            $outstanding = $balance;

            if (isset($name) && ($name === 'Accountancy Prime' || $name === 'Accountancy Premium')) {
                if (isset($single['turnover']) && $single['turnover'] > 0) {
                    $acc_fees = $monthlyAccountsFee;
                } else {
                    $acc_fees = 0;
                }
            } else {
                if ($single['date'] != '') {
                    $acc_fees = $acc_fees_default;
                } else {
                    $acc_fees = 0;
                }
            }

            $other_fee = $single['other_fee'] ?? 0;
            $total_other += $other_fee;
            $balance = round($outstanding + $acc_fees + $other_fee, 2);

            // Store gross total BEFORE subtracting paid (for display on paid months)
            $gross_total = $balance;

            // Display-only: calculate delay days and late fee regardless of payment status
            $display_days = 0;
            $display_penalty = 0;
            if (!empty($single['due_date']) && $single['due_date'] < $date && $gross_total > 0) {
                $due_dt   = new DateTime($single['due_date']);
                // For paid months use payment_date, for unpaid use today
                $ref_date = (!empty($single['payment_date']))
                    ? new DateTime(date('Y-m-d', strtotime($single['payment_date'])))
                    : new DateTime($date);
                $display_days = (int)$due_dt->diff($ref_date)->days;
                $display_penalty = ($percent * $gross_total);
                if ($display_days < 30) {
                    $display_penalty = ($display_penalty / 30) * $display_days;
                }
                $display_penalty = round($display_penalty);
            }

            if ($single['due_date'] < $date && $paid < $balance) {
                $balance = round($balance - $paid, 2);
                $date1 = new DateTime($single['due_date']);
                $date2 = new DateTime($date);
                $interval = $date1->diff($date2);
                $days = $interval->days;
                $penalty = ($percent * $balance);
                if ($days < 30) {
                    $penalty /= 30;
                    $penalty *= $days;
                }
                $penalty = round($penalty);
                if (!$include_late_fee) {
                    $penalty = 0;
                }
                $total_penalty += $penalty;
                $total_days += $days;
            } else {
                $balance = round($balance - $paid, 2);
            }

            if ($balance < 0) {
                $balance = 0;
            }
            $total = round($balance + $penalty, 2);

            // --- AUTO DEBIT LOGIC ---
            $auto_debit_status = $single['auto_debit_status'] ?? 'Pending';
            if ($auto_debit_status !== 'Confirmed' && $single['due_date'] <= $date && $total > 0) {
                $this->load->model('Wallet_model', 'wallet');
                $wallet_bal = $this->wallet->getwalletbalance($user_id);
                if ($wallet_bal >= $total) {
                    $payment_data = array(
                        'user_id' => $user_id,
                        'firm_id' => $firm_id,
                        'amount' => $total,
                        'acc_date' => $single['date'],
                        'added_on' => date('Y-m-d H:i:s'),
                        'updated_on' => date('Y-m-d H:i:s')
                    );
                    $this->db->insert('acc_payment', $payment_data);
                    $this->db->update('accountancy', ['auto_debit_status' => 'Confirmed'], ['id' => $single['id']]);
                    $paid += $total;
                    $auto_debit_status = 'Confirmed';
                    $single['auto_debit_status'] = 'Confirmed';
                    $single['payment_date'] = date('Y-m-d H:i:s');
                    $balance = 0;
                    $total = 0;
                }
            }
            // --- END AUTO DEBIT LOGIC ---

            $total_sum += $total;
            $total_fees += $acc_fees;
            $total_paid += $paid;
            $total_balance_sum += $balance;

            $isPastMonth = false;
            $renewalMethod = 'AUTO_WALLET';
            $auto_debit_status_label = isset($auto_debit_status) ? $auto_debit_status : 'Pending';

            if ($single['date'] != '') {
                $todayDate = new DateTime(date('Y-m-d'));
                $todayDate->setTime(0, 0, 0);

                $rowDate = new DateTime($single['date']);
                $rowDate->setTime(0, 0, 0);

                $isPastMonth = (date('Y-m', $rowDate->getTimestamp()) < date('Y-m', $todayDate->getTimestamp()));
                $renewalMethod = $isPastMonth ? 'ADMIN' : 'AUTO_WALLET';

                if ($auto_debit_status_label !== 'Confirmed') {
                    if ($isPastMonth) {
                        if ($total > 0) {
                            $auto_debit_status_label = 'Admin Renew';
                        } else {
                            $auto_debit_status_label = 'Renewed';
                        }
                    } else {
                        if (!empty($single['due_date'])) {
                            $dueDateObj = new DateTime($single['due_date']);
                            $dueDateObj->setTime(0, 0, 0);

                            $interval = $todayDate->diff($dueDateObj);
                            $daysLeft = (int)$interval->format('%R%a');

                            if ($daysLeft > 0) {
                                $auto_debit_status_label = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left auto debit';
                            } else if ($daysLeft === 0) {
                                $auto_debit_status_label = 'Auto debit today';
                            } else {
                                $auto_debit_status_label = 'Auto debit processing';
                            }
                        } else {
                            $auto_debit_status_label = 'Pending';
                        }
                    }
                }
            }

            $month = $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--';
            $due_date = $single['due_date'] != '' ? date('d-m-Y F', strtotime($single['due_date'])) : '--';

            $auto_debit_status_html = '';
            if ($auto_debit_status_label === 'Confirmed') {
                $auto_debit_status_html = '<span class="badge bg-success">Confirmed</span>';
            } else {
                $auto_debit_status_html = '<span class="badge bg-warning text-dark">' . $auto_debit_status_label . '</span>';
            }

            $action_date_html = '';
            if ($auto_debit_status_label === 'Confirmed' || $paid > 0) {
                $p_date = !empty($single['payment_date']) ? date('d-m-Y', strtotime($single['payment_date'])) : '';
                $action_date_html = '<span class="text-success font-weight-bold"><i class="fa fa-check-circle"></i> Confirmed<br><small>' . $p_date . '</small></span>';
            } else {
                if ($renewalMethod === 'ADMIN') {
                    $action_date_html = 'Admin Renew';
                } else {
                    $action_date_html = '<span class="text-muted">Auto Debit</span>';
                }
            }

            $record = array(
                'id' => $single['id'],
                'date' => $single['date'],
                'month' => $month,
                'due_date' => $due_date,
                'outstanding' => round($outstanding, 2),
                'gto' => round($single['turnover'], 2),
                'acc_fees' => round($acc_fees, 2),
                'penalty' => round($penalty, 2),
                'display_penalty' => $display_penalty,
                'gross_total' => round($gross_total + $penalty, 2),
                'total' => round($total, 2),
                'paid' => round($paid, 2),
                'balance' => round($balance, 2),
                'days' => $days,
                'display_days' => $display_days,
                'due_days' => $days,
                'auto_debit_status' => $auto_debit_status_label,
                'auto_debit_status_label' => $auto_debit_status_html,
                'action_date' => $action_date_html
            );

            $records[] = $record;

            if ($balance > 0) {
                $unpaid_months[] = $record;
            }
        }

        $last_month_balance = 0;
        $last_month_data = null;
        $first_month_data = null;
        $payment_month_range = '';

        if (!empty($unpaid_months)) {
            $first_month_data = reset($unpaid_months);
            $last_month_data = end($unpaid_months);
            $last_month_balance = $last_month_data['balance'];

            if (count($unpaid_months) > 1) {
                $payment_month_range = $first_month_data['month'] . '-' . $last_month_data['month'];
            } else {
                $payment_month_range = $last_month_data['month'];
            }
        }

        return array(
            'status' => true,
            'package' => $package,
            'cpackage' => $cpackage,
            'pkg_type' => 'Turnover',
            'records' => $records,
            'unpaid_months' => $unpaid_months,
            'total_turnover' => $total_turnover,
            'total_fees' => $total_fees,
            'total_penalty' => $total_penalty,
            'total_sum' => $total_sum,
            'total_paid' => $total_paid,
            'total_balance' => $total_balance_sum,
            'total_days' => $total_days,
            'last_month_balance' => $last_month_balance,
            'last_month_data' => $last_month_data,
            'first_month_data' => $first_month_data,
            'payment_month_range' => $payment_month_range,
            'accountancy' => $accountancy
        );
    }

    public function index()
    {
        $data = ['title' => 'Accounting Fee'];
        $data['breadcrumb'] = array("active" => "Accounting Fee");
        $data['alertify'] = true;
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user_id = $user['id'];

        // Validate required session data
        if (empty($year) || empty($firm_id)) {
            $this->session->set_flashdata('err_msg', 'Please select Year and Firm!');
            redirect('home/');
            return;
        }

        $acc_data = $this->get_accountancy_report_data($user_id, $firm_id, $year);
        if (!$acc_data['status']) {
            $this->session->set_flashdata('err_msg', $acc_data['message']);
            redirect($acc_data['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? 'home/'));
            return;
        }

        if (isset($acc_data['pkg_type']) && $acc_data['pkg_type'] === 'Monthly') {
            $this->session->set_flashdata('err_msg', 'Accounting Fee is not available for Monthly type packages. Please check Accounting Pay.');
            redirect('reports/payment/');
            return;
        }

        // On this page, Accounting Mode displays ONLY three columns:
        // Month, GTO, Accounts Fee
        // All underlying business logic, calculations, and data are preserved.
        $report = array();
        foreach ($acc_data['records'] as $record) {
            $report[] = array(
                'month' => $record['month'],
                'gto' => $record['gto'],
                'acc_fees' => $record['acc_fees'],
                'outstanding' => $record['outstanding'],
                'penalty' => $record['penalty'],
                'total' => $record['total'],
                'paid' => $record['paid'],
                'balance' => $record['balance'],
                'due_date' => $record['due_date'],
                'due_days' => $record['days'],
                'auto_debit_status_label' => $record['auto_debit_status_label'],
                'action_date' => $record['action_date']
            );
        }

        // Total row for the 3 columns
        $report[] = array(
            'month' => 'Total',
            'gto' => round($acc_data['total_turnover'], 2),
            'acc_fees' => round($acc_data['total_fees'], 2),
            'outstanding' => 0,
            'penalty' => round($acc_data['total_penalty'], 2),
            'total' => round($acc_data['total_sum'], 2),
            'paid' => round($acc_data['total_paid'], 2),
            'balance' => 0,
            'due_date' => '',
            'due_days' => $acc_data['total_days'],
            'auto_debit_status' => '',
            'action_date' => ''
        );

        $data['report'] = $report;
        $data['package'] = $acc_data['package'];
        $data['last_month_balance'] = $acc_data['last_month_balance'];
        $data['payment_month_range'] = $acc_data['payment_month_range'];
        $data['last_month_name'] = !empty($acc_data['last_month_data']['month']) ? $acc_data['last_month_data']['month'] : '';

        $this->template->load('reports', 'accountancy', $data);
    }

    public function otherfee()
    {
        $data = ['title' => 'Other Fee Report'];
        $data['breadcrumb'] = array("active" => "Other Fee Report");
        $data['alertify'] = true;
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user_id = $user['id'];

        // Validate required session data
        if (empty($year) || empty($firm_id)) {
            $this->session->set_flashdata('err_msg', 'Please select Year and Firm!');
            redirect($_SERVER['HTTP_REFERER'] ?? 'home/');
            return;
        }

        $years = getyearmonthvalues($year);
        $where = array('t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.date>=' => $years['year1'] . '-04-01', 't1.date<=' => $years['year2'] . '-03-31');
        $purchases_db = $this->service->getpurchases($where);

        // Fetch Monthly Account Work packages first so upfront combined purchase rows don't duplicate/misplace monthly amounts
        $this->db->select('id, user_id, firm_id, year, purchase_date as date, bill_amount, amount as base_amount, added_on, status');
        $this->db->where([
            'user_id' => $user['id'],
            'firm_id' => $firm_id,
            'package_type' => 'Monthly',
            'year' => $year
        ]);
        $monthly_packages = $this->db->get('customer_packages')->result_array();

        $purchases = array();
        $acct_months_seen = array();
        $acct_split_seen = array();

        // 1. Process actual purchases from purchases table
        if (!empty($purchases_db)) {
            foreach ($purchases_db as $p) {
                $sid = (string)$p['service_id'];
                $ym  = !empty($p['date']) ? date('Y-m', strtotime($p['date'])) : '';

                if ($sid === '1') {
                    $svc_label  = trim((string)($p['service'] ?? ''));
                    $period_val = trim((string)($p['period_value'] ?? ''));

                    // Upfront combined purchase inserted by Services::buyservice() covers all months already stored in customer_packages
                    if (
                        !empty($monthly_packages)
                        && $period_val === ''
                        && in_array($svc_label, array(
                            'Account Work Monthly',
                            'Account Work Monthly (Wallet)',
                            'Account Work Monthly (Credit Limit)'
                        ), true)
                    ) {
                        continue;
                    }

                    // Keep both halves of a split Wallet + Credit Limit auto-debit while still deduplicating retries
                    if ($period_val !== '' && (strpos($svc_label, 'Auto-Debit Wallet') !== false || strpos($svc_label, 'Auto-Debit Credit Limit') !== false)) {
                        $split_part = (strpos($svc_label, 'Auto-Debit Credit Limit') !== false) ? 'credit' : 'wallet';
                        $split_key  = $ym . '|' . $period_val . '|' . $split_part;
                        if (isset($acct_split_seen[$split_key])) {
                            continue;
                        }
                        $acct_split_seen[$split_key] = true;
                        $acct_months_seen[$ym] = true;
                    } else {
                        // Deduplicate Account Work per month
                        if (isset($acct_months_seen[$ym])) {
                            continue; // Skip duplicate Account Work purchase in same month
                        }
                        $acct_months_seen[$ym] = true;
                    }
                }
                $purchases[] = $p;
            }
        }

        // 2. Add Monthly Account Work packages to purchases array for display (only for months not already covered)
        if (!empty($monthly_packages)) {
            $has_multiple_rows = count($monthly_packages) > 1;

            foreach ($monthly_packages as $mp) {
                $bill_amount = (float)$mp['bill_amount'];
                $base_amount = (float)$mp['base_amount'];
                if ($base_amount <= 0 && $bill_amount > 0) {
                    $base_amount = $bill_amount;
                }
                if ($base_amount <= 0) {
                    $base_amount = 1500;
                }

                if ($has_multiple_rows) {
                    $date_str = !empty($mp['date']) ? date('Y-m-01', strtotime($mp['date'])) : sprintf('%04d-%02d-01', $years['year1'], 4);
                    $ym = date('Y-m', strtotime($date_str));

                    if (!isset($acct_months_seen[$ym])) {
                        $acct_months_seen[$ym] = true;
                        $entry = $mp;
                        $entry['amount'] = $base_amount;
                        $entry['date'] = $date_str;
                        $entry['service_id'] = '1'; // Ensure it's a string to match DB type
                        $entry['service'] = 'Account Work Monthly';
                        
                        $purchases[] = $entry;
                    }
                } else {
                    // Single accumulated package record: distribute across covered months
                    $months_covered = 1;
                    if ($base_amount > 0 && $bill_amount > 0) {
                        $months_covered = (int)round($bill_amount / $base_amount);
                    }
                    if ($months_covered < 1)  $months_covered = 1;
                    if ($months_covered > 12) $months_covered = 12;
                    
                    for ($m = 0; $m < $months_covered; $m++) {
                        $month_num = 4 + $m;
                        $calc_year = $years['year1'];
                        
                        if ($month_num > 12) {
                            $month_num -= 12;
                            $calc_year = $years['year2'];
                        }
                        
                        $date_str = sprintf('%04d-%02d-01', $calc_year, $month_num);
                        $ym = date('Y-m', strtotime($date_str));

                        if (!isset($acct_months_seen[$ym])) {
                            $acct_months_seen[$ym] = true;
                            $entry = $mp;
                            $entry['amount'] = $base_amount;
                            $entry['date'] = $date_str;
                            $entry['service_id'] = '1'; // Ensure it's a string to match DB type
                            $entry['service'] = 'Account Work Monthly';
                            
                            $purchases[] = $entry;
                        }
                    }
                }
            }
        }
        $services = $this->master->getservices();
        $report = array();
        $row = array('service' => 'Service');
        if (empty($years)) {
            if (date('d') <= 31 && date('m') < 4) {
                $start = (date('Y') - 1) . '-04-01';
            } else {
                $start = date('Y') . '-04-01';
            }
        } else {
            $start = date($years['year1'] . '-04-01');
        }
        for ($i = 0; $i < 12; $i++) {
            $m = date('F', strtotime($start . " +$i month"));
            $row[$m] = date('F-Y', strtotime($start . " +$i month"));
        }
        $row['total'] = "Total";

        $report[] = $row;

        if (!empty($purchases)) {
            $service_ids = array_column($purchases, 'service_id');
            $dates = array_column($purchases, 'date');
            //print_pre($service_ids);
            //print_pre($dates);
        }
        $total_amount = 0;
        if (!empty($services)) {
            foreach ($services as $single) {
                $row = array();
                $row['service'] = $single['name'];
                for ($i = 0; $i < 12; $i++) {
                    $m = date('F', strtotime($start . " +$i month"));
                    $row[$m] = 0;
                }
                //echo $single['name'];
                $filteredDates = array();
                $indices = !empty($purchases) ? array_keys($service_ids, $single['id']) : array();
                if (!empty($indices)) {
                    foreach ($indices as $index) {
                        $filteredDates[$index] = $dates[$index];
                    }
                }
                //print_pre($filteredDates);
                $total = 0;

                for ($i = 0; $i < 12; $i++) {
                    $m = date('F', strtotime($start . " +$i month"));
                    if (empty($month[$i])) {
                        $month[$i] = 0;
                    }
                    $text = 0;
                    if (!empty($filteredDates)) {
                        //echo date('F-Y',strtotime($start." +$i month"));
                        $first = date('Y-m-01', strtotime($start . " +$i month"));
                        $last = date('Y-m-t', strtotime($start . " +$i month"));
                        $searches = findDateIndices($filteredDates, $first, $last);
                        if (!empty($searches)) {
                            $text = 0;
                            foreach ($searches as $index) {
                                $text += $purchases[$index]['amount'];
                            }
                            $total += $text;
                            $month[$i] += $text;
                            $text = $this->amount->toDecimal($text, false);
                        }
                    }
                    $row[$m] = $text;
                }
                $row['total'] = $this->amount->toDecimal($total, false);
                $report[] = $row;

                $total_amount += $total;
            }
        }
        $row = array('service' => 'Total');
        for ($i = 0; $i < 12; $i++) {
            $m = date('F', strtotime($start . " +$i month"));
            $row[$m] = !empty($month[$i]) ? $this->amount->toDecimal($month[$i], false) : 0;
        }
        $row['total'] = $this->amount->toDecimal($total_amount, false);
        $report[] = $row;


        if (!empty($report)) {
            $data['report'] = $report;
            $this->template->load('reports', 'otherfee', $data);
        } else {
            $this->session->set_flashdata('err_msg', 'No Reports Available!');
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function feereport()
    {
        $data = ['title' => 'Fee Report'];
        $data['breadcrumb'] = array("active" => "Fee Report");
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user = getuser();
        $folders = array();
        $folder = array();
        $folder['name'] = "Accounting Fee";
        $folder['count'] = '';
        $folder['link'] = ('reports/');
        $folders[] = $folder;
        $folder = array();
        $folder['name'] = "Other Fee Report";
        $folder['count'] = '';
        $folder['link'] = ('reports/otherfee/');
        $folders[] = $folder;
        $data['folders'] = $folders;
        //print_pre($data,true);
        $data['datatable'] = true;
        $data['styles'] = array('file' => 'includes/custom/folder.css');
        //$data['folders']=$folders;
        $this->template->load('pages', 'folder-view', $data);
    }

    // Admin Income Reports
    public function adminincome()
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('home/');
        }

        $data = ['title' => 'Income Reports'];
        $data['breadcrumb'] = array("active" => "Income Reports");
        $data['datatable'] = true;
        $data['alertify'] = true;

        // Get filter parameters
        $period = $this->input->get('period') ? $this->input->get('period') : '';
        $service_id = $this->input->get('service_id') ? $this->input->get('service_id') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;
        $selected_month = $this->input->get('month') ? $this->input->get('month') : NULL;
        $selected_quarter = $this->input->get('quarter') ? $this->input->get('quarter') : NULL;
        $selected_year = $this->input->get('year') ? $this->input->get('year') : NULL;

        // If custom date range is selected but period is not set to custom, set it
        if (!empty($start_date) && !empty($end_date) && $period != 'custom') {
            $period = 'custom';
        }
        // Default to monthly if no period selected
        if (empty($period)) {
            $period = 'monthly';
        }

        // Get all services for dropdown
        $services = $this->master->getservices(['status' => 1]);
        $service_options = array('' => 'All Services');
        if (!empty($services)) {
            foreach ($services as $service) {
                $service_options[$service['id']] = $service['name'];
            }
        }

        // Generate month dropdown options (last 12 months)
        $month_options = array('' => 'Select Month');
        $current_year = date('Y');
        $current_month = date('m');
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $month_value = $date; // Format: YYYY-MM
            $month_label = date('F Y', strtotime($date . '-01'));
            $month_options[$month_value] = $month_label;
        }

        // Generate quarter dropdown options (last 8 quarters)
        $quarter_options = array('' => 'Select Quarter');
        $current_year = date('Y');
        $current_month = date('n');
        $current_quarter = ceil($current_month / 3);

        // Generate quarters: start from current quarter and go back 8 quarters
        $added_quarters = array();
        for ($i = 0; $i < 24; $i++) { // Go back 24 months to cover 8 quarters
            $months_back = $i;
            $date = date('Y-m', strtotime("-$months_back months"));
            $year = date('Y', strtotime($date . '-01'));
            $month = date('n', strtotime($date . '-01'));
            $quarter = ceil($month / 3);
            $quarter_value = $year . '-Q' . $quarter; // Format: YYYY-Q1, YYYY-Q2, etc.

            // Only add if not already added and we haven't reached 8 quarters yet
            if (!isset($added_quarters[$quarter_value]) && count($added_quarters) < 8) {
                $quarter_label = 'Q' . $quarter . ' ' . $year;
                $quarter_options[$quarter_value] = $quarter_label;
                $added_quarters[$quarter_value] = true;
            }
        }

        // Generate year dropdown options (last 5 years)
        $year_options = array('' => 'Select Year');
        $current_year = date('Y');
        for ($i = 0; $i < 5; $i++) {
            $year = $current_year - $i;
            $year_options[$year] = $year;
        }

        $data['services'] = $service_options;
        $data['month_options'] = $month_options;
        $data['quarter_options'] = $quarter_options;
        $data['year_options'] = $year_options;
        $data['selected_service'] = $service_id;
        $data['selected_period'] = $period;
        $data['selected_month'] = $selected_month;
        $data['selected_quarter'] = $selected_quarter;
        $data['selected_year'] = $selected_year;
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        // Get income data - use empty array instead of "1" string
        $where = array();
        $income_data = $this->service->getincomebyperiod($where, $period, $service_id, $start_date, $end_date, $selected_month, $selected_quarter, $selected_year);
        $data['income_data'] = $income_data;

        // Calculate totals
        $total_amount = 0;
        $total_orders = 0;
        $total_customers = 0;
        if (!empty($income_data)) {
            foreach ($income_data as $row) {
                $total_amount += $row['total_amount'];
                $total_orders += $row['total_orders'];
                $total_customers += $row['total_customers'];
            }
        }
        $data['total_amount'] = $total_amount;
        $data['total_orders'] = $total_orders;
        $data['total_customers'] = $total_customers;

        $this->template->load('reports', 'admin_income', $data);
    }

    public function gstsalesreport()
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('home/');
        }

        $data = ['title' => 'GST Sales Report B2B'];
        $data['breadcrumb'] = array("active" => "GST Sales Report B2B");
        $data['datatable'] = true;
        $data['alertify'] = true;

        $period = $this->input->get('period') ? $this->input->get('period') : 'all';
        $selected_month = $this->input->get('month') ? $this->input->get('month') : date('Y-m');
        $selected_quarter = $this->input->get('quarter') ? $this->input->get('quarter') : '';
        $selected_year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $export = strtolower(trim((string)$this->input->get('export')));

        $month_options = array('' => 'Select Month');
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $month_options[$date] = date('F Y', strtotime($date . '-01'));
        }

        $quarter_options = array('' => 'Select Quarter');
        $added_quarters = array();
        for ($i = 0; $i < 24; $i++) {
            $date = date('Y-m', strtotime("-$i months"));
            $year = date('Y', strtotime($date . '-01'));
            $month = date('n', strtotime($date . '-01'));
            $quarter = ceil($month / 3);
            $qv = $year . '-Q' . $quarter;
            if (!isset($added_quarters[$qv]) && count($added_quarters) < 8) {
                $quarter_options[$qv] = 'Q' . $quarter . ' ' . $year;
                $added_quarters[$qv] = true;
            }
        }

        $year_options = array('' => 'Select Year');
        $current_year = date('Y');
        for ($i = 0; $i < 5; $i++) {
            $year = $current_year - $i;
            $year_options[$year] = $year;
        }

        $rows = $this->service->get_gst_sales_report($period, $selected_month, $selected_quarter, $selected_year, 'b2b');
        $report_rows = array();
        foreach ($rows as $row) {
            $invoice_no = !empty($row['invoice_no']) ? $row['invoice_no'] : (!empty($row['order_id']) ? $row['order_id'] : '');
            $invoice_date = !empty($row['invoice_date']) ? date('d-M-y', strtotime($row['invoice_date'])) : '';
            $invoice_value = isset($row['invoice_value']) ? (float)$row['invoice_value'] : 0.0;
            $taxable_value = isset($row['taxable_value']) ? (float)$row['taxable_value'] : 0.0;
            $rate = isset($row['gst_rate']) ? (float)$row['gst_rate'] : 0.0;
            $recipient_gstin = !empty($row['recipient_gstin']) ? $row['recipient_gstin'] : '';
            $place_of_supply = !empty($row['place_of_supply']) ? $row['place_of_supply'] : '';
            $gst_state_code = substr($recipient_gstin, 0, 2);
            // Keep buyer's state name when available; use GST code mapping only as fallback.
            if (empty($place_of_supply) && !empty($gst_state_code)) {
                $state_name = $this->gststatename($gst_state_code);
                if (!empty($state_name)) {
                    $place_of_supply = $gst_state_code . '-' . $state_name;
                } elseif (empty($place_of_supply)) {
                    $place_of_supply = $gst_state_code;
                }
            }

            $report_rows[] = array(
                'recipient_gstin' => $recipient_gstin,
                'receiver_name' => !empty($row['receiver_name']) ? $row['receiver_name'] : '',
                'invoice_number' => $invoice_no,
                'invoice_date' => $invoice_date,
                'invoice_value' => round($invoice_value, 2),
                'place_of_supply' => $place_of_supply,
                'reverse_charge' => 'N',
                'applicable_tax_rate' => '',
                'invoice_type' => 'Regular B2B',
                'ecommerce_gstin' => '',
                'rate' => rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.'),
                'taxable_value' => round($taxable_value, 2),
                'cess_amount' => ''
            );
        }

        if (in_array($export, array('csv', 'pdf', 'json'), true)) {
            $this->exportgstsalesreport($report_rows, $export, $period, $selected_month, $selected_quarter, $selected_year);
            return;
        }

        $data['selected_period'] = $period;
        $data['selected_month'] = $selected_month;
        $data['selected_quarter'] = $selected_quarter;
        $data['selected_year'] = $selected_year;
        $data['month_options'] = $month_options;
        $data['quarter_options'] = $quarter_options;
        $data['year_options'] = $year_options;
        $data['report_rows'] = $report_rows;

        $this->template->load('reports', 'gst_sales_report', $data);
    }

    public function gstsalesreportb2c()
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('home/');
        }

        $data = ['title' => 'GST Sales Report B2C'];
        $data['breadcrumb'] = array("active" => "GST Sales Report B2C");
        $data['datatable'] = true;
        $data['alertify'] = true;

        $period = $this->input->get('period') ? $this->input->get('period') : 'all';
        $selected_month = $this->input->get('month') ? $this->input->get('month') : date('Y-m');
        $selected_quarter = $this->input->get('quarter') ? $this->input->get('quarter') : '';
        $selected_year = $this->input->get('year') ? $this->input->get('year') : date('Y');
        $export = strtolower(trim((string)$this->input->get('export')));

        $month_options = array('' => 'Select Month');
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $month_options[$date] = date('F Y', strtotime($date . '-01'));
        }

        $quarter_options = array('' => 'Select Quarter');
        $added_quarters = array();
        for ($i = 0; $i < 24; $i++) {
            $date = date('Y-m', strtotime("-$i months"));
            $year = date('Y', strtotime($date . '-01'));
            $month = date('n', strtotime($date . '-01'));
            $quarter = ceil($month / 3);
            $qv = $year . '-Q' . $quarter;
            if (!isset($added_quarters[$qv]) && count($added_quarters) < 8) {
                $quarter_options[$qv] = 'Q' . $quarter . ' ' . $year;
                $added_quarters[$qv] = true;
            }
        }

        $year_options = array('' => 'Select Year');
        $current_year = date('Y');
        for ($i = 0; $i < 5; $i++) {
            $year = $current_year - $i;
            $year_options[$year] = $year;
        }

        $rows = $this->service->get_gst_sales_report($period, $selected_month, $selected_quarter, $selected_year, 'b2c');
        $report_rows = array();
        foreach ($rows as $row) {
            $invoice_no = !empty($row['invoice_no']) ? $row['invoice_no'] : (!empty($row['order_id']) ? $row['order_id'] : '');
            $invoice_date = !empty($row['invoice_date']) ? date('d-M-y', strtotime($row['invoice_date'])) : '';
            $invoice_value = isset($row['invoice_value']) ? (float)$row['invoice_value'] : 0.0;
            $taxable_value = isset($row['taxable_value']) ? (float)$row['taxable_value'] : 0.0;
            $rate = isset($row['gst_rate']) ? (float)$row['gst_rate'] : 0.0;
            $recipient_pan = !empty($row['recipient_pan']) ? $row['recipient_pan'] : '';
            $place_of_supply = !empty($row['place_of_supply']) ? $row['place_of_supply'] : '';

            $report_rows[] = array(
                'recipient_pan' => $recipient_pan,
                'receiver_name' => !empty($row['receiver_name']) ? $row['receiver_name'] : '',
                'invoice_number' => $invoice_no,
                'invoice_date' => $invoice_date,
                'invoice_value' => round($invoice_value, 2),
                'place_of_supply' => $place_of_supply,
                'reverse_charge' => 'N',
                'applicable_tax_rate' => '',
                'invoice_type' => 'Regular B2C',
                'ecommerce_gstin' => '',
                'rate' => rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.'),
                'taxable_value' => round($taxable_value, 2),
                'cess_amount' => ''
            );
        }

        if (in_array($export, array('csv', 'pdf', 'json'), true)) {
            $this->exportgstsalesreport($report_rows, $export, $period, $selected_month, $selected_quarter, $selected_year, 'b2c');
            return;
        }

        $data['selected_period'] = $period;
        $data['selected_month'] = $selected_month;
        $data['selected_quarter'] = $selected_quarter;
        $data['selected_year'] = $selected_year;
        $data['month_options'] = $month_options;
        $data['quarter_options'] = $quarter_options;
        $data['year_options'] = $year_options;
        $data['report_rows'] = $report_rows;

        $this->template->load('reports', 'gst_sales_report_b2c', $data);
    }

    private function exportgstsalesreport($rows, $format, $period, $month, $quarter, $year, $report_type = 'b2b')
    {
        $first_column = $report_type === 'b2c' ? 'PAN of Recipient' : 'GSTIN/UIN of Recipient';
        $headers = array(
            $first_column,
            'Receiver Name',
            'Invoice Number',
            'Invoice date',
            'Invoice Value',
            'Place Of Supply',
            'Reverse Charge',
            'Applicable % of Tax Rate',
            'Invoice Type',
            'E-Commerce GSTIN',
            'Rate',
            'Taxable Value',
            'Cess Amount'
        );
        $period_label = $this->gstperiodlabel($period, $month, $quarter, $year);
        $filename_base = ($report_type === 'b2c' ? 'gst_sales_report_b2c_' : 'gst_sales_report_b2b_') . preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($period_label)) . '_' . date('Ymd_His');

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
            exit;
        }

        if ($format === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename_base . '.json"');
            $payload = array(
                'report' => $report_type === 'b2c' ? 'GST Sales Report B2C' : 'GST Sales Report B2B',
                'period' => $period,
                'month' => $month,
                'quarter' => $quarter,
                'year' => $year,
                'generated_at' => date('c'),
                'total_records' => count($rows),
                'data' => array_values($rows)
            );
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($format === 'pdf') {
            $html = '<h3>GST Sales Report - ' . htmlspecialchars($period_label) . '</h3>';
            $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%" style="border-collapse: collapse; font-size: 11px;">';
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th style="background:#f4f4f4;">' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach (array_values($row) as $value) {
                        $html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="13" style="text-align:center;">No data found</td></tr>';
            }
            $html .= '</tbody></table>';

            $this->load->library('pdf');
            $this->pdf->generate($html, $filename_base, 'A4', 'landscape');
            exit;
        }
    }

    private function gstperiodlabel($period, $month, $quarter, $year)
    {
        if ($period === 'all') {
            return 'all_time';
        }
        if ($period === 'monthly') {
            return !empty($month) ? date('F_Y', strtotime($month . '-01')) : 'monthly';
        }
        if ($period === 'quarterly') {
            return !empty($quarter) ? str_replace('-', '_', $quarter) : 'quarterly';
        }
        if ($period === 'yearly') {
            return !empty($year) ? 'year_' . $year : 'yearly';
        }
        return 'report';
    }

    private function gststatename($code)
    {
        $states = array(
            '01' => 'Jammu and Kashmir',
            '02' => 'Himachal Pradesh',
            '03' => 'Punjab',
            '04' => 'Chandigarh',
            '05' => 'Uttarakhand',
            '06' => 'Haryana',
            '07' => 'Delhi',
            '08' => 'Rajasthan',
            '09' => 'Uttar Pradesh',
            '10' => 'Bihar',
            '11' => 'Sikkim',
            '12' => 'Arunachal Pradesh',
            '13' => 'Nagaland',
            '14' => 'Manipur',
            '15' => 'Mizoram',
            '16' => 'Tripura',
            '17' => 'Meghalaya',
            '18' => 'Assam',
            '19' => 'West Bengal',
            '20' => 'Jharkhand',
            '21' => 'Odisha',
            '22' => 'Chhattisgarh',
            '23' => 'Madhya Pradesh',
            '24' => 'Gujarat',
            '26' => 'Dadra and Nagar Haveli and Daman and Diu',
            '27' => 'Maharashtra',
            '28' => 'Andhra Pradesh',
            '29' => 'Karnataka',
            '30' => 'Goa',
            '31' => 'Lakshadweep',
            '32' => 'Kerala',
            '33' => 'Tamil Nadu',
            '34' => 'Puducherry',
            '35' => 'Andaman and Nicobar Islands',
            '36' => 'Telangana',
            '37' => 'Andhra Pradesh',
            '38' => 'Ladakh'
        );
        return isset($states[$code]) ? $states[$code] : '';
    }

    public function servicecustomers()
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('home/');
        }

        $data = ['title' => 'Service Customers'];
        $data['breadcrumb'] = array("active" => "Service Customers");
        $data['datatable'] = true;
        $data['alertify'] = true;

        // Get filter parameters
        $service_id = $this->input->get('service_id') ? $this->input->get('service_id') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;

        // Get all services for dropdown
        $services = $this->master->getservices(['status' => 1]);
        $service_options = array('' => 'Select Service');
        if (!empty($services)) {
            foreach ($services as $service) {
                $service_options[$service['id']] = $service['name'];
            }
        }
        $data['services'] = $service_options;
        $data['selected_service'] = $service_id;
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        // Get customers data - use empty array instead of "1" string
        $where = array();
        $customers_data = array();
        if (!empty($service_id)) {
            $customers_data = $this->service->getcustomersbyservice($where, $service_id, $start_date, $end_date);
        }
        $data['customers_data'] = $customers_data;

        // Calculate totals
        $total_amount = 0;
        if (!empty($customers_data)) {
            foreach ($customers_data as $row) {
                $total_amount += $row['amount'];
            }
        }
        $data['total_amount'] = $total_amount;
        $data['total_count'] = count($customers_data);

        $this->template->load('reports', 'service_customers', $data);
    }

    public function assignmentreports()
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('home/');
        }

        $data = ['title' => 'Assignment Reports'];
        $data['breadcrumb'] = array("active" => "Assignment Reports");
        $data['datatable'] = true;
        $data['alertify'] = true;

        // Get filter parameters
        $service_id = $this->input->get('service_id') ? $this->input->get('service_id') : NULL;
        $period = $this->input->get('period') ? $this->input->get('period') : '';
        $selected_date = $this->input->get('date') ? $this->input->get('date') : NULL;
        $selected_month = $this->input->get('month') ? $this->input->get('month') : NULL;
        $selected_year = $this->input->get('year') ? $this->input->get('year') : NULL;
        $start_date = $this->input->get('start_date') ? $this->input->get('start_date') : NULL;
        $end_date = $this->input->get('end_date') ? $this->input->get('end_date') : NULL;

        // Get all services for dropdown
        $services = $this->master->getservices(['status' => 1]);
        $service_options = array('' => 'Select Service');
        if (!empty($services)) {
            foreach ($services as $service) {
                $service_options[$service['id']] = $service['name'];
            }
        }

        // Generate month dropdown options
        $month_options = array('' => 'Select Month');
        $current_year = date('Y');
        for ($i = 0; $i < 12; $i++) {
            $date = date('Y-m', strtotime("-$i months"));
            $year = date('Y', strtotime($date . '-01'));
            $month = date('n', strtotime($date . '-01'));
            $month_value = date('Ym', strtotime($date . '-01'));
            $month_label = date('F Y', strtotime($date . '-01'));
            $month_options[$month_value] = $month_label;
        }

        // Generate year dropdown options
        $year_options = array('' => 'Select Year');
        $current_year = date('Y');
        for ($i = 0; $i < 5; $i++) {
            $year = $current_year - $i;
            $year_options[$year] = $year;
        }

        $data['services'] = $service_options;
        $data['month_options'] = $month_options;
        $data['year_options'] = $year_options;
        $data['selected_service'] = $service_id;
        $data['selected_period'] = $period;
        $data['selected_date'] = $selected_date;
        $data['selected_month'] = $selected_month;
        $data['selected_year'] = $selected_year;
        $data['start_date'] = $start_date;
        $data['end_date'] = $end_date;

        // Check if assignment_done column exists
        $table_prefix = $this->db->dbprefix;
        $assessments_table = $table_prefix . 'assessments';
        $check_column = $this->db->query("SHOW COLUMNS FROM `{$assessments_table}` LIKE 'assignment_done'");
        $has_assignment_done = ($check_column && $check_column->num_rows() > 0);

        // Build query to get assignment reports
        // Use aggregate functions to comply with ONLY_FULL_GROUP_BY mode
        if ($has_assignment_done) {
            $this->db->select('a.id, 
                              MAX(a.order_id) as order_id, 
                              MAX(a.date) as assessment_date, 
                              MAX(a.assignment_done) as assignment_done, 
                              MAX(a.assignment_done_date) as assignment_done_date, 
                              MAX(p.service_id) as service_id, 
                              MAX(s.name) as service_name, 
                              MAX(c.user_id) as customer_id, 
                              MAX(c.name) as customer_name,
                              MAX(oa.user_id) as employee_id, 
                              MAX(u.username) as employee_name, 
                              MAX(u.name) as employee_full_name,
                              MAX(p.added_on) as order_date');
        } else {
            // Fallback if columns don't exist yet
            $this->db->select('a.id, 
                              MAX(a.order_id) as order_id, 
                              MAX(a.date) as assessment_date, 
                              0 as assignment_done, 
                              NULL as assignment_done_date, 
                              MAX(p.service_id) as service_id, 
                              MAX(s.name) as service_name, 
                              MAX(c.user_id) as customer_id, 
                              MAX(c.name) as customer_name,
                              MAX(oa.user_id) as employee_id, 
                              MAX(u.username) as employee_name, 
                              MAX(u.name) as employee_full_name,
                              MAX(p.added_on) as order_date');
        }

        // Use subquery to get only the latest order_assign per order_id to prevent duplicates
        $order_assign_table = $this->db->dbprefix . 'order_assign';
        $order_assign_subquery = "(SELECT order_id, MAX(id) as max_id FROM {$order_assign_table} WHERE status = 0 GROUP BY order_id) latest_oa";

        $this->db->from('assessments a');
        $this->db->join('purchases p', 'a.order_id = p.id', 'left');
        $this->db->join('services s', 'p.service_id = s.id', 'left');
        $this->db->join('customers c', 'p.user_id = c.user_id', 'left');
        $this->db->join($order_assign_subquery, 'a.order_id = latest_oa.order_id', 'left', false);
        $this->db->join('order_assign oa', 'latest_oa.order_id = oa.order_id AND latest_oa.max_id = oa.id', 'left');
        $this->db->join('users u', 'oa.user_id = u.id', 'left');

        // Group by assessment ID to ensure one row per assessment
        // Using MAX() for other columns to comply with ONLY_FULL_GROUP_BY mode
        $this->db->group_by('a.id');

        // Apply filters
        if (!empty($service_id)) {
            $this->db->where('p.service_id', $service_id);
        }

        // Ensure date field is not NULL
        $this->db->where('a.date IS NOT NULL');

        // Date filter based on period
        if ($period == 'date' && !empty($selected_date)) {
            $this->db->where('DATE(a.date)', $selected_date);
        } elseif ($period == 'month' && !empty($selected_month)) {
            // selected_month format is YYYYMM (e.g., 202602 for February 2026)
            // Convert to proper date range for better performance
            if (strlen($selected_month) == 6) {
                $year = substr($selected_month, 0, 4);
                $month = substr($selected_month, 4, 2);
                $start_date_filter = $year . '-' . $month . '-01';
                $end_date_filter = date('Y-m-t', strtotime($start_date_filter)); // Last day of month
                $this->db->where('DATE(a.date) >=', $start_date_filter);
                $this->db->where('DATE(a.date) <=', $end_date_filter);
            } else {
                // Fallback to DATE_FORMAT if format is unexpected
                $this->db->where('DATE_FORMAT(a.date, "%Y%m")', $selected_month);
            }
        } elseif ($period == 'year' && !empty($selected_year)) {
            $this->db->where('YEAR(a.date)', $selected_year);
        } elseif ($period == 'custom' && !empty($start_date) && !empty($end_date)) {
            $this->db->where('DATE(a.date) >=', $start_date);
            $this->db->where('DATE(a.date) <=', $end_date);
        }

        $this->db->order_by('a.date', 'DESC');
        $this->db->order_by('a.id', 'DESC');
        $query = $this->db->get();

        // Check if query succeeded
        if ($query === false) {
            $error = $this->db->error();
            $last_query = $this->db->last_query();
            log_message('error', 'Assignment reports query failed: ' . $error['message']);
            log_message('error', 'Failed SQL Query: ' . $last_query);
            log_message('error', 'Month filter value: ' . $selected_month . ', Period: ' . $period);
            $this->session->set_flashdata('err_msg', 'Error loading assignment reports. Check error logs for details.');
            $assignments = array();
        } else {
            $assignments = $query->result_array();
        }

        // Process assignments to handle assignment_done field (backward compatibility)
        foreach ($assignments as $key => $assignment) {
            if (!isset($assignment['assignment_done'])) {
                $assignments[$key]['assignment_done'] = 0;
            }
        }

        $data['assignments'] = $assignments;

        // Calculate totals
        $total_pending = 0;
        $total_done = 0;
        if (!empty($assignments)) {
            foreach ($assignments as $row) {
                if (isset($row['assignment_done']) && $row['assignment_done'] == 1) {
                    $total_done++;
                } else {
                    $total_pending++;
                }
            }
        }
        $data['total_pending'] = $total_pending;
        $data['total_done'] = $total_done;
        $data['total_records'] = count($assignments);

        $this->template->load('reports', 'assignment_reports', $data);
    }

    /**
     * Admin/Employee view: Show all customers with pending package renewals
     * Displays which customers have packages pending to renew
     */
    public function pendingpackagerenewals()
    {
        // Only allow admin and employee access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin' && $this->session->role != 'employee') {
            redirect('home/');
            return;
        }

        $data = ['title' => 'Pending Package Renewals'];
        $data['breadcrumb'] = array("active" => "Pending Package Renewals");
        $data['datatable'] = true;
        $data['alertify'] = true;

        // Get filter parameters
        $selected_year = $this->input->get('year') ? $this->input->get('year') : NULL;
        $selected_customer_user_id = $this->input->get('customer_id') ? $this->input->get('customer_id') : NULL;

        // Get current year (default to current financial year)
        $current_date = date('Y-m-d');
        $current_month = date('m');
        $current_year = date('Y');

        // Financial year starts from April, so if current month < April, use previous year
        if ($current_month < 4) {
            $fy_start_year = $current_year - 1;
        } else {
            $fy_start_year = $current_year;
        }

        // Default year format: YYYY(YYYY+1) e.g., 20232024
        $default_year = $fy_start_year . ($fy_start_year + 1);
        $year = $selected_year ? $selected_year : $default_year;

        // Get all customers (filter by employee if needed)
        $where_customers = array();
        if ($this->session->role == 'employee') {
            // Employees can only see customers they added
            $where_customers['md5(t1.added_by)'] = $this->session->user;
        }

        $all_customers = $this->customer->getcustomers($where_customers);

        // Get all firms for these customers
        $customer_user_ids = array();
        if (!empty($all_customers)) {
            $customer_user_ids = array_column($all_customers, 'user_id');
        }

        $pending_renewals = array();

        if (!empty($customer_user_ids)) {
            // Get all service packages for these customers
            $packages_where = array();
            if (!empty($selected_customer_user_id)) {
                $packages_where['t1.user_id'] = $selected_customer_user_id;
            }

            $all_packages = $this->customer->getservicepackage($packages_where, 'all');

            // Group packages by user_id and firm_id
            $packages_by_customer = array();
            if (!empty($all_packages)) {
                foreach ($all_packages as $pkg) {
                    $key = $pkg['user_id'] . '_' . $pkg['firm_id'];
                    if (!isset($packages_by_customer[$key])) {
                        $packages_by_customer[$key] = array();
                    }
                    $packages_by_customer[$key][] = $pkg;
                }
            }

            // Process each customer-firm combination
            foreach ($packages_by_customer as $key => $packages) {
                if (empty($packages)) continue;

                $first_pkg = $packages[0];
                $user_id = $first_pkg['user_id'];
                $firm_id = $first_pkg['firm_id'];
                $customer_name = $first_pkg['customer_name'];
                $firm_name = $first_pkg['firm_name'];

                // Get customer ID from user_id
                $customer = $this->customer->getcustomers(array('t1.user_id' => $user_id), 'single');
                $customer_id = !empty($customer) ? $customer['id'] : null;

                // Find current year package
                $current_package = null;
                $expired_packages = array();

                foreach ($packages as $pkg) {
                    if ($pkg['year'] == $year) {
                        $current_package = $pkg;
                    } else {
                        $expired_packages[] = $pkg;
                    }
                }

                // Get current year service IDs
                $current_service_ids = array();
                if (!empty($current_package) && !empty($current_package['service_ids'])) {
                    $current_service_ids = array_filter(array_map('trim', explode(',', $current_package['service_ids'])));
                }

                // Find renewal candidates (services in expired packages but not in current)
                $renewal_services = array();
                $expired_years = array();

                foreach ($expired_packages as $exp_pkg) {
                    if (empty($exp_pkg['service_ids'])) continue;

                    $exp_service_ids = array_filter(array_map('trim', explode(',', $exp_pkg['service_ids'])));
                    $exp_year = $exp_pkg['year'];

                    foreach ($exp_service_ids as $service_id) {
                        if (empty($service_id)) continue;

                        // If service is not in current package, it's a renewal candidate
                        if (!in_array($service_id, $current_service_ids, true)) {
                            if (!isset($renewal_services[$service_id])) {
                                // Get service details
                                $service = $this->master->getservices(array('id' => $service_id, 'status' => 1), 'single');
                                if (!empty($service)) {
                                    $renewal_services[$service_id] = array(
                                        'service_id' => $service_id,
                                        'service_name' => $service['name'],
                                        'expired_year' => $exp_year
                                    );
                                }
                            }
                        }
                    }

                    if (!empty($exp_service_ids)) {
                        $expired_years[] = $exp_year;
                    }
                }

                // Also check for pending purchases (status=0)
                $pending_purchases = array();
                if (!empty($current_service_ids) || !empty($renewal_services)) {
                    $all_service_ids = array_merge($current_service_ids, array_keys($renewal_services));
                    $all_service_ids = array_unique(array_filter($all_service_ids));

                    if (!empty($all_service_ids)) {
                        $user_id_escaped = $this->db->escape($user_id);
                        $firm_id_escaped = $this->db->escape($firm_id);
                        $year_escaped = $this->db->escape($year);
                        $service_ids_str = implode(',', array_map('intval', $all_service_ids));

                        $where_pending = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.year={$year_escaped} AND t1.status='0' AND t1.service_id IN ($service_ids_str)";
                        $pending_purchases = $this->service->getpurchasedservices($where_pending, 'all', true);
                    }
                }

                // Only add to list if there are renewal candidates or pending purchases
                if (!empty($renewal_services) || !empty($pending_purchases)) {
                    // Format year display
                    $year_display = $year;
                    if (strlen($year_display) == 8 && is_numeric($year_display)) {
                        $year1 = substr($year_display, 0, 4);
                        $year2 = substr($year_display, 4, 4);
                        $year_display = $year1 . '-' . $year2;
                    }

                    $pending_renewals[] = array(
                        'user_id' => $user_id,
                        'customer_id' => $customer_id,
                        'customer_name' => $customer_name,
                        'firm_id' => $firm_id,
                        'firm_name' => !empty($firm_name) ? $firm_name : 'N/A',
                        'year' => $year,
                        'year_display' => $year_display,
                        'renewal_services' => array_values($renewal_services),
                        'pending_purchases' => $pending_purchases,
                        'renewal_count' => count($renewal_services),
                        'pending_count' => count($pending_purchases),
                        'expired_years' => array_unique($expired_years)
                    );
                }
            }
        }

        // Get customer dropdown for filter
        $customer_options = array('' => 'All Customers');
        if (!empty($all_customers)) {
            foreach ($all_customers as $cust) {
                $customer_options[$cust['user_id']] = $cust['name'] . ' (' . $cust['mobile'] . ')';
            }
        }

        // Get year options (last 5 years)
        $year_options = array('' => 'Select Year');
        $current_fy = $fy_start_year;
        for ($i = 0; $i < 5; $i++) {
            $yr = ($current_fy - $i) . ($current_fy - $i + 1);
            $yr_display = ($current_fy - $i) . '-' . substr(($current_fy - $i + 1), -2);
            $year_options[$yr] = 'TY ' . $yr_display;
        }

        $data['pending_renewals'] = $pending_renewals;
        $data['customers'] = $customer_options;
        $data['years'] = $year_options;
        $data['selected_customer'] = $selected_customer_user_id;
        $data['selected_year'] = $selected_year ? $selected_year : $year;

        $this->template->load('reports', 'pending_package_renewals', $data);
    }

    public function payment()
    {
        $data = ['title' => 'Accountancy Payment'];
        $data['breadcrumb'] = array("reports" => "Reports", "active" => "Payment");
        $data['alertify'] = true;

        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user_id = $user['id'];

        // Validate required session data
        if (empty($year) || empty($firm_id)) {
            $this->session->set_flashdata('err_msg', 'Please select Year and Firm!');
            redirect('home/');
            return;
        }

        $acc_data = $this->get_accountancy_report_data($user_id, $firm_id, $year, false);
        if (!$acc_data['status']) {
            $this->session->set_flashdata('err_msg', $acc_data['message']);
            redirect($acc_data['redirect'] ?? 'home/');
            return;
        }

        $last_month_balance = $acc_data['last_month_balance'];
        $unpaid_months = $acc_data['unpaid_months'];

        $unpaid_fees_subtotal = 0;
        if (!empty($unpaid_months)) {
            foreach ($unpaid_months as $um) {
                $unpaid_fees_subtotal += ((float)($um['acc_fees'] ?? 0) + (float)($um['other_fee'] ?? 0));
            }
        } else {
            $unpaid_fees_subtotal = $last_month_balance;
        }

        // Check customer GST setting and calculate GST if enabled (calculated on actual package fee)
        $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $gst_rate = $gst_enabled ? 18.0 : 0.0;
        $gst_amount = $gst_enabled ? round(($unpaid_fees_subtotal * $gst_rate) / 100, 2) : 0;
        $total_with_gst = round($last_month_balance + $gst_amount, 2);

        // Get customer state for SGST/CGST vs IGST calculation
        $customer_state_id = null;
        if (!empty($customer) && !empty($customer['parent_id'])) {
            $customer_state_id = $customer['parent_id'];
        }

        // Get admin/company state (from admin user address)
        $admin_state_id = null;
        $admin_user = $this->db->select('id')->where_in('role', ['admin', 'superadmin'])->limit(1)->get('users')->row_array();
        if (!empty($admin_user)) {
            $admin_address = $this->customer->getaddresses(['t1.user_id' => $admin_user['id']], 'single');
            if (!empty($admin_address) && !empty($admin_address['parent_id'])) {
                $admin_state_id = $admin_address['parent_id'];
            }
        }

        // Calculate SGST/CGST or IGST breakdown
        $sgst_amount = 0;
        $cgst_amount = 0;
        $igst_amount = 0;
        $states_match = false;

        if ($gst_amount > 0 && !empty($customer_state_id) && !empty($admin_state_id)) {
            $states_match = ($customer_state_id == $admin_state_id);

            if ($states_match) {
                // Same state: SGST 9% + CGST 9% = 18%
                $sgst_amount = round(($unpaid_fees_subtotal * 9) / 100, 2);
                $cgst_amount = round(($unpaid_fees_subtotal * 9) / 100, 2);
                $total_gst = round($sgst_amount + $cgst_amount, 2);
                if (abs($total_gst - $gst_amount) > 0.01) {
                    $diff = round($gst_amount - $total_gst, 2);
                    $sgst_amount = round($sgst_amount + round($diff / 2, 2), 2);
                    $cgst_amount = round($gst_amount - $sgst_amount, 2);
                }
            } else {
                // Different states: IGST 18%
                $igst_amount = round($gst_amount, 2);
            }
        } else {
            // Fallback: if states not available, use IGST
            $igst_amount = $gst_amount;
        }

        $data['payment_records'] = $acc_data['records'];
        $data['unpaid_months'] = $acc_data['unpaid_months'];
        $data['total_balance'] = $acc_data['total_balance'];
        $data['total_fees'] = $acc_data['total_fees'];
        $data['total_other'] = $acc_data['total_other'];
        $data['total_penalty'] = $acc_data['total_penalty'];
        $data['total_sum'] = $acc_data['total_sum'];
        $data['total_paid'] = $acc_data['total_paid'];
        $data['total_days'] = $acc_data['total_days'];
        $data['last_month_balance'] = $last_month_balance;
        $data['last_month_data'] = $acc_data['last_month_data'];
        $data['first_month_data'] = $acc_data['first_month_data'];
        $data['payment_month_range'] = $acc_data['payment_month_range'];
        $data['package'] = $acc_data['package'];
        $data['year'] = $year;
        $data['firm_id'] = $firm_id;
        $data['user_id'] = $user_id;
        $data['gst_enabled'] = $gst_enabled;
        $data['gst_rate'] = $gst_rate;
        $data['gst_amount'] = $gst_amount;
        $data['total_with_gst'] = $total_with_gst;
        $data['sgst_amount'] = $sgst_amount;
        $data['cgst_amount'] = $cgst_amount;
        $data['igst_amount'] = $igst_amount;
        $data['states_match'] = $states_match;

        // Fetch other fee (non-accountancy purchases) grouped by month for payment page
        $years_data = getyearmonthvalues($year);
        $purchases_where = array(
            't1.user_id' => $user_id,
            't1.firm_id' => $firm_id,
            't1.date>='  => $years_data['year1'] . '-04-01',
            't1.date<='  => $years_data['year2'] . '-03-31'
        );
        $other_purchases_db = $this->service->getpurchases($purchases_where);
        $other_fee_by_month = array();
        $total_other_fee = 0;
        if (!empty($other_purchases_db)) {
            foreach ($other_purchases_db as $p) {
                if ((string)$p['service_id'] === '1') continue; // Skip accountancy service
                $ym = !empty($p['date']) ? date('Y-m', strtotime($p['date'])) : '';
                if (empty($ym)) continue;
                if (!isset($other_fee_by_month[$ym])) {
                    $other_fee_by_month[$ym] = 0;
                }
                $other_fee_by_month[$ym] += round(floatval($p['amount']), 2);
                $total_other_fee += round(floatval($p['amount']), 2);
            }
        }
        $data['other_fee_by_month'] = $other_fee_by_month;
        $data['total_other_fee'] = round($total_other_fee, 2);

        $this->load->model('Wallet_model', 'wallet');
        $data['wallet_balance'] = (float)$this->wallet->getwalletbalance($user_id);
        $data['credit_balance'] = (float)$this->wallet->get_available_credit($user_id);

        $this->template->load('reports', 'payment', $data);
    }

    public function processpayment()
    {
        if ($this->input->post('makepayment') !== NULL) {
            $user = getuser();
            $data = $this->input->post();
            $year = $data['year'];
            $firm_id = $data['firm_id'];
            $user_id = $data['user_id'];
            $amount = floatval($data['amount']);

            if (empty($year) || empty($firm_id) || empty($amount) || $amount <= 0) {
                $this->session->set_flashdata('err_msg', 'Invalid payment data!');
                redirect('reports/payment/');
                return;
            }

            $acc_data = $this->get_accountancy_report_data($user_id, $firm_id, $year, false);
            if (!$acc_data['status']) {
                $this->session->set_flashdata('err_msg', $acc_data['message']);
                redirect($acc_data['redirect'] ?? 'home/');
                return;
            }

            $unpaid_months = $acc_data['unpaid_months'];
            $last_month_balance = $acc_data['last_month_balance'];
            $last_month_data = $acc_data['last_month_data'];

            if (empty($unpaid_months) || $last_month_balance <= 0) {
                $this->session->set_flashdata('err_msg', 'No unpaid month found!');
                redirect('reports/payment/');
                return;
            }

            // Calculate unpaid fees subtotal for GST calculation
            $unpaid_fees_subtotal = 0;
            if (!empty($unpaid_months)) {
                foreach ($unpaid_months as $um) {
                    $unpaid_fees_subtotal += ((float)($um['acc_fees'] ?? 0) + (float)($um['other_fee'] ?? 0));
                }
            } else {
                $unpaid_fees_subtotal = $last_month_balance;
            }

            // Check customer GST setting to validate payment amount
            $customer_check = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
            $gst_enabled_check = !empty($customer_check) && !empty($customer_check['gst_enabled']) && $customer_check['gst_enabled'] == 1;
            $gst_amount_check = $gst_enabled_check ? round(($unpaid_fees_subtotal * 18) / 100, 2) : 0;
            $expected_total = round($last_month_balance + $gst_amount_check, 2);

            if (abs($amount - $expected_total) > 0.01) {
                $this->session->set_flashdata('err_msg', 'Payment amount does not match the expected total! Expected: ₹' . number_format($expected_total, 2) . ($gst_enabled_check ? ' (including GST)' : ''));
                redirect('reports/payment/');
                return;
            }

            // Check wallet and credit limit balance before processing payment
            $this->load->model('Wallet_model', 'wallet');
            $wallet_balance = (float)$this->wallet->getwalletbalance($user_id);
            $credit_balance = (float)$this->wallet->get_available_credit($user_id);
            $total_amount_needed = $expected_total;

            if ($wallet_balance < $total_amount_needed && $credit_balance < $total_amount_needed && ($wallet_balance + $credit_balance) < $total_amount_needed) {
                $this->session->set_flashdata('err_msg', 'Insufficient balance! Required: ₹' . number_format($total_amount_needed, 2) . '. Wallet Balance: ₹' . number_format(max(0, $wallet_balance), 2) . ', Available Credit Limit: ₹' . number_format(max(0, $credit_balance), 2) . '. Please add funds or request a credit limit increase.');
                redirect('reports/payment/');
                return;
            }

            // Process payment for ALL unpaid months (from first to last)
            $payment_success = true;
            $payment_errors = array();
            $previous_balance = 0;
            $is_monthly_package = isset($acc_data['pkg_type']) && $acc_data['pkg_type'] === 'Monthly';
            $rem_wallet = max(0.0, $wallet_balance);
            $used_credit_total = 0.0;
            $used_wallet_total = 0.0;

            foreach ($unpaid_months as $month_data) {
                $month_payment_amount = $is_monthly_package
                    ? $month_data['balance']
                    : $month_data['balance'] - $previous_balance;

                if ($month_payment_amount <= 0) {
                    continue;
                }

                if (!$is_monthly_package) {
                    $previous_balance = $month_data['balance'];
                }

                $payment_date = date('Y-m-d');
                if (!empty($month_data['due_date']) && $month_data['due_date'] != '--') {
                    $due_date_parts = explode('-', $month_data['due_date']);
                    if (count($due_date_parts) == 3) {
                        $payment_date = $due_date_parts[2] . '-' . $due_date_parts[1] . '-' . $due_date_parts[0];
                    }
                }

                $acc_date = !empty($month_data['date']) ? $month_data['date'] : date('Y-m-d');

                $month_fee = (float)($month_data['acc_fees'] ?? 0) + (float)($month_data['other_fee'] ?? 0);
                $month_gst = $gst_enabled_check ? round(($month_fee * 18) / 100, 2) : 0;
                $month_total = round($month_payment_amount + $month_gst, 2);

                // Determine wallet vs credit limit deduction for this month
                $w_deduct = 0.0;
                $c_deduct = 0.0;

                if ($rem_wallet >= $month_total) {
                    $w_deduct = $month_total;
                    $rem_wallet -= $month_total;
                } elseif ($rem_wallet > 0) {
                    $w_deduct = $rem_wallet;
                    $c_deduct = round($month_total - $rem_wallet, 2);
                    $rem_wallet = 0.0;
                } else {
                    $c_deduct = $month_total;
                }

                $used_wallet_total += $w_deduct;
                $used_credit_total += $c_deduct;

                if ($w_deduct > 0 && $c_deduct > 0) {
                    // Split payment row 1: Wallet
                    $p1 = array(
                        'user_id'      => $user_id,
                        'firm_id'      => $firm_id,
                        'year'         => $year,
                        'acc_date'     => $acc_date,
                        'date'         => $payment_date,
                        'amount'       => $w_deduct,
                        'payment_mode' => 'Wallet',
                        'status'       => 1,
                        'acc_fee'      => round($month_payment_amount * ($w_deduct / $month_total), 2),
                        'added_by'     => $user['id'],
                        'added_on'     => date('Y-m-d H:i:s'),
                        'updated_on'   => date('Y-m-d H:i:s')
                    );
                    $this->db->insert('acc_payment', $p1);

                    // Split payment row 2: Credit Limit
                    $p2 = array(
                        'user_id'      => $user_id,
                        'firm_id'      => $firm_id,
                        'year'         => $year,
                        'acc_date'     => $acc_date,
                        'date'         => $payment_date,
                        'amount'       => $c_deduct,
                        'payment_mode' => 'Credit Limit',
                        'status'       => 1,
                        'acc_fee'      => round($month_payment_amount * ($c_deduct / $month_total), 2),
                        'added_by'     => $user['id'],
                        'added_on'     => date('Y-m-d H:i:s'),
                        'updated_on'   => date('Y-m-d H:i:s')
                    );
                    $this->db->insert('acc_payment', $p2);
                } else {
                    $chosen_mode = ($c_deduct > 0) ? 'Credit Limit' : 'Wallet';
                    $payment_data = array(
                        'user_id'      => $user_id,
                        'firm_id'      => $firm_id,
                        'year'         => $year,
                        'acc_date'     => $acc_date,
                        'date'         => $payment_date,
                        'amount'       => $month_total,
                        'payment_mode' => $chosen_mode,
                        'status'       => 1,
                        'acc_fee'      => $month_payment_amount,
                        'added_by'     => $user['id']
                    );

                    $result = $this->wallet->makeaccountancypayment($payment_data);
                    if ($result['status'] !== true) {
                        $payment_success = false;
                        $payment_errors[] = $result['message'] . ' (Month: ' . (!empty($month_data['date']) ? date('F Y', strtotime($month_data['date'])) : 'N/A') . ', Amount: ₹' . number_format($month_payment_amount, 2) . ')';
                    }
                }

                // Update auto_debit_status in accountancy table
                $this->db->where('user_id', $user_id);
                $this->db->where('firm_id', $firm_id);
                $this->db->where('date', $acc_date);
                $this->db->update('accountancy', ['auto_debit_status' => 'Confirmed']);
            }

            if (!$payment_success) {
                $this->session->set_flashdata('err_msg', 'Payment partially failed: ' . implode(', ', $payment_errors));
                redirect('reports/payment/');
                return;
            }

            // If monthly package, update corresponding customer_packages records to Confirmed
            if (isset($acc_data['pkg_type']) && $acc_data['pkg_type'] === 'Monthly') {
                foreach ($unpaid_months as $m_data) {
                    $m_dt = !empty($m_data['date']) ? $m_data['date'] : '';
                    if (!empty($m_dt)) {
                        $m_part = (int)date('m', strtotime($m_dt));
                        $y_part = (int)date('Y', strtotime($m_dt));
                        $this->db->where('user_id', $user_id);
                        $this->db->where('firm_id', $firm_id);
                        $this->db->where('package_type', 'Monthly');
                        $this->db->where("MONTH(purchase_date) = {$m_part}", null, false);
                        $this->db->where("YEAR(purchase_date) = {$y_part}", null, false);
                        $this->db->update('customer_packages', [
                            'payment_status'    => 1,
                            'auto_debit_status' => 'Confirmed',
                            'updated_on'        => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            // Get customer details for invoice
            $this->load->model('Customer_model', 'customer');
            $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
            $firm = $this->db->get_where('firms', ['id' => $firm_id])->row_array();

            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
            $subtotal = $last_month_balance;
            $gst_rate = $gst_enabled ? 18.0 : 0.0;
            $gst_amount = $gst_enabled ? round(($unpaid_fees_subtotal * $gst_rate) / 100, 2) : 0;
            $total_amount = $subtotal + $gst_amount;

            $this->load->model('Invoice_model', 'invoice');

            $period_value = '';
            if (!empty($unpaid_months)) {
                $first_month = reset($unpaid_months);
                $last_month = end($unpaid_months);

                $first_month_name = !empty($first_month['date']) ? strtolower(date('F-y', strtotime($first_month['date']))) : '';
                $last_month_name = !empty($last_month['date']) ? strtolower(date('F-y', strtotime($last_month['date']))) : '';

                if ($first_month_name == $last_month_name) {
                    $period_value = $first_month_name;
                } else {
                    $period_value = $first_month_name . '/' . $last_month_name;
                }
            } else {
                $last_month_name = !empty($last_month_data['date']) ? strtolower(date('F-y', strtotime($last_month_data['date']))) : '';
                $period_value = $last_month_name;
            }

            $invoice_data = array(
                'user_id' => $user_id,
                'firm_id' => $firm_id,
                'type' => 'accountancy',
                'year' => $year,
                'subtotal' => $subtotal,
                'gst_rate' => $gst_rate,
                'gst_amount' => $gst_amount,
                'total_amount' => $total_amount,
                'invoice_date' => date('Y-m-d'),
                'billing_name' => !empty($customer['name']) ? $customer['name'] : '',
                'billing_email' => !empty($customer['email']) ? $customer['email'] : '',
                'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                'firm_name' => !empty($firm['name']) ? $firm['name'] : '',
                'firm_gstin' => !empty($firm['gstin']) ? $firm['gstin'] : '',
                'firm_pan' => !empty($firm['pan']) ? $firm['pan'] : '',
                'service_name' => 'Accountancy Service',
                'period_value' => $period_value
            );

            $invoice_result = $this->invoice->create_custom_invoice($invoice_data);
            $invoice_no = '';
            if ($invoice_result['status'] === true && !empty($invoice_result['invoice'])) {
                $invoice_no = $invoice_result['invoice']['invoice_no'];
            }

            $payment_msg = 'Payment of ₹' . number_format($subtotal, 2);
            if ($gst_enabled && $gst_amount > 0) {
                $payment_msg .= ' + GST ₹' . number_format($gst_amount, 2) . ' (Total: ₹' . number_format($total_amount, 2) . ')';
            }
            if ($used_credit_total > 0 && $used_wallet_total > 0) {
                $payment_msg .= ' [₹' . number_format($used_wallet_total, 2) . ' from Wallet, ₹' . number_format($used_credit_total, 2) . ' from Credit Limit]';
            } elseif ($used_credit_total > 0) {
                $payment_msg .= ' [Deducted from Credit Limit: ₹' . number_format($used_credit_total, 2) . ']';
            }
            $payment_msg .= ' processed successfully!' . (!empty($invoice_no) ? ' Invoice: ' . $invoice_no : '');
            $this->session->set_flashdata('msg', $payment_msg);
            redirect('reports/');
        } else {
            redirect('reports/payment/');
        }
    }
}
