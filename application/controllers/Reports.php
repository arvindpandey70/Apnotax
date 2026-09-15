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
            redirect($_SERVER['HTTP_REFERER'] ?? 'home/');
            return;
        }

        $yearval = getyearmonthvalues($year);
        $year1 = $yearval['year1'];
        $year2 = $yearval['year2'];
        $from = "$year1-04-01";
        $to = "$year2-03-31";
        $where = array('user_id' => $user_id, 'status' => 1);
        $query = $this->db->get_where('customer_packages', $where);
        if ($query->num_rows() > 0) {
            $cpackage = $query->unbuffered_row('array');
            $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

            // Accounting Fee is only for Turnover type packages
            // Monthly type packages don't use turnover-based fee calculation
            if ($pkg_type == 'Monthly') {
                $this->session->set_flashdata('err_msg', 'Accounting Fee is not available for Monthly type packages. Monthly packages are auto-debited based on the fixed amount.');
                redirect('package/');
                return;
            }

            // Use proper escaping for SQL query
            $user_id_escaped = $this->db->escape($user_id);
            $firm_id_escaped = $this->db->escape($firm_id);
            $from_escaped = $this->db->escape($from);
            $to_escaped = $this->db->escape($to);
            $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";
            $accountancy = $this->service->getturnoverswithpayment($where2);

            // Log for debugging (only in development)
            if (ENVIRONMENT !== 'production') {
                log_message('debug', 'Reports index - User ID: ' . $user_id . ', Firm ID: ' . $firm_id . ', Year: ' . $year);
                log_message('debug', 'Reports index - Date range: ' . $from . ' to ' . $to);
                log_message('debug', 'Reports index - Accountancy records found: ' . count($accountancy));
            }

            $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
            $turnover = array_sum($turnovers);
            $total_turnover = $turnover;
            $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            $package = $this->master->getpackages(['name' => $name, 'turnover>' => $turnover], 'single');
            $date = date('Y-m-d');
            $percent = 2 / 100;
            $report = array();
            if (!empty($accountancy)) {
                $total_fees = $total_other = $total_paid = $total_penalty = $total_days = 0;
                $outstanding = $total = $balance = 0;
                $total_sum = 0;
                // Slab-based Prime plan fee, per updated remuneration table
                if (isset($name) && $name === 'Accountancy Prime') {
                    $gto = $total_turnover / 100000;
                    if ($gto <= 0) {
                        $fees = 0;
                    } else if ($gto <= 25) {
                        $fees = (12000 / 25) * $gto;
                    } else if ($gto <= 50) {
                        $fees = (20000 / 50) * $gto;
                    } else if ($gto <= 75) {
                        $fees = (25000 / 75) * $gto;
                    } else if ($gto <= 100) {
                        $fees = (30000 / 100) * $gto;
                    } else {
                        $fees = 30000 + (($gto - 100) * (10000 / 100));
                    }
                    $fees = round($fees, 2);
                } else if (isset($name) && $name === 'Accountancy Premium') {
                    // Slab-based Premium plan fee, per updated remuneration table
                    $gto = $total_turnover / 100000;
                    if ($gto <= 0) {
                        $fees = 0;
                    } else if ($gto <= 25) {
                        $fees = (15000 / 25) * $gto;
                    } else if ($gto <= 50) {
                        $fees = (24000 / 50) * $gto;
                    } else if ($gto <= 75) {
                        $fees = (30000 / 75) * $gto;
                    } else if ($gto <= 100) {
                        $fees = (36000 / 100) * $gto;
                    } else {
                        $fees = 36000 + (($gto - 100) * (15000 / 100));
                    }
                    $fees = round($fees, 2);
                } else {
                    $fees = $total_turnover / $package['turnover'];
                    $fees *= $package['rate'];
                }
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

                $acc_fees = $count > 0 ? ($fees / $count) : 0;


                foreach ($accountancy as &$single) {

                    $days = $paid = $penalty = 0;

                    $paid = !empty($single['paid']) ? $single['paid'] : 0;

                    // Outstanding should be the previous month's balance (unpaid amount)
                    $outstanding = $balance;

                    if (isset($name) && ($name === 'Accountancy Prime' || $name === 'Accountancy Premium')) {

                        if (isset($single['turnover']) && $single['turnover'] > 0) {

                            $acc_fees = $monthlyAccountsFee;

                        } else {

                            $acc_fees = 0;

                        }

                    } else {

                        if ($single['date'] != '') {

                            $acc_fees = $count > 0 ? ($fees / $count) : 0;

                        } else {

                            $acc_fees = 0;

                        }

                    }
                    $other_fee = $single['other_fee'] ?? 0;
                    $total_other += $other_fee;
                    $balance = $outstanding + $acc_fees + $other_fee;
                    if ($single['due_date'] < $date && $paid < $balance) {
                        $balance -= $paid;
                        $date1 = new DateTime($single['due_date']);
                        $date2 = new DateTime($date);

                        // Calculate the difference
                        $interval = $date1->diff($date2);

                        // Get the difference in days
                        $days = $interval->days;
                        $penalty = ($percent * $balance);
                        if ($days < 30) {
                            $penalty /= 30;
                            $penalty *= $days;
                        }
                        $penalty = round($penalty);
                        $total_penalty += $penalty;
                        $total_days += $days;
                    } else {
                        $balance -= $paid;
                    }
                    // Ensure balance doesn't go negative
                    if ($balance < 0) {
                        $balance = 0;
                    }
                    $total = $balance + $penalty;
                    
                    // --- AUTO DEBIT LOGIC ---
                    $auto_debit_status = $single['auto_debit_status'] ?? 'Pending';
                    if ($auto_debit_status !== 'Confirmed' && $single['due_date'] <= $date && $total > 0) {
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

                    $row = array('month' => $month,
                        'outstanding' => round($outstanding, 2),
                        'gto' => round($single['turnover'], 2),
                        'acc_fees' => round($acc_fees, 2),
                        'penalty' => round($penalty, 2),
                        'total' => round($total, 2),
                        'paid' => round($paid, 2),
                        'balance' => round($balance, 2),
                        'due_date' => $due_date,
                        'due_days' => $days,
                        'auto_debit_status_label' => $auto_debit_status_html,
                        'action_date' => $action_date_html
                    );

                    $report[] = $row;
                }
                $row = array(
                    'month' => 'Total',
                    'outstanding' => 0,
                    'gto' => round($total_turnover, 2),
                    'acc_fees' => round($total_fees, 2),
                    'penalty' => round($total_penalty, 2),
                    'total' => round($total_sum, 2), // Use sum of all row totals instead of calculation
                    'paid' => round($total_paid, 2),
                    'balance' => 0,
                    'due_date' => '',
                    'due_days' => $total_days,
                    'auto_debit_status' => '',
                    'action_date' => ''
                );

                $report[] = $row;
                $data['report'] = $report;
                $data['package'] = $package;
                $this->template->load('reports', 'accountancy', $data);
            } else {
                $this->session->set_flashdata('err_msg', 'No Data Found!');
                redirect($_SERVER['HTTP_REFERER']);
            }
        } else {
            $this->session->set_flashdata('err_msg', 'Package not Active!');
            redirect($_SERVER['HTTP_REFERER']);
        }
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

        $purchases = array();
        $acct_months_seen = array();

        // 1. Process actual purchases from purchases table
        if (!empty($purchases_db)) {
            foreach ($purchases_db as $p) {
                $sid = (string)$p['service_id'];
                $ym  = !empty($p['date']) ? date('Y-m', strtotime($p['date'])) : '';

                if ($sid === '1') {
                    // Deduplicate Account Work per month
                    if (isset($acct_months_seen[$ym])) {
                        continue; // Skip duplicate Account Work purchase in same month
                    }
                    $acct_months_seen[$ym] = true;
                }
                $purchases[] = $p;
            }
        }
        
        // 2. Add Monthly Account Work packages to purchases array for display (only for months not already covered)
        $this->db->select('id, user_id, firm_id, year, purchase_date as date, bill_amount, amount as base_amount, added_on, status');
        $this->db->where([
            'user_id' => $user['id'], 
            'firm_id' => $firm_id,
            'package_type' => 'Monthly',
            'year' => $year
        ]);
        $monthly_packages = $this->db->get('customer_packages')->result_array();
        
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
                                // Note: Currently shows all services (web + app). 
                                // If filtering by platform is needed, add platform field check here.
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
            redirect('reports/');
            return;
        }

        $yearval = getyearmonthvalues($year);
        $year1 = $yearval['year1'];
        $year2 = $yearval['year2'];
        $from = "$year1-04-01";
        $to = "$year2-03-31";

        // Check package type first - Accounting Pay is only for Turnover type packages
        $where = array('user_id' => $user_id, 'status' => 1);
        $query = $this->db->get_where('customer_packages', $where);
        if ($query->num_rows() > 0) {
            $cpackage = $query->unbuffered_row('array');
            $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

            // Accounting Pay is only for Turnover type packages
            // Monthly type packages don't use turnover-based payment calculation
            if ($pkg_type == 'Monthly') {
                $this->session->set_flashdata('err_msg', 'Accounting Pay is not applicable for Monthly Account Work packages.');
                redirect('reports/');
                return;
            }
        }

        $user_id_escaped = $this->db->escape($user_id);
        $firm_id_escaped = $this->db->escape($firm_id);
        $from_escaped = $this->db->escape($from);
        $to_escaped = $this->db->escape($to);
        $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";

        $accountancy = $this->service->getturnoverswithpayment($where2);

        if (empty($accountancy)) {
            $this->session->set_flashdata('err_msg', 'No Data Found!');
            redirect('reports/');
            return;
        }
        if ($query->num_rows() == 0) {
            $this->session->set_flashdata('err_msg', 'Package not Active!');
            redirect('reports/');
            return;
        }

        $cpackage = $query->unbuffered_row('array');
        $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
        $package = $this->master->getpackages(['name' => $name], 'single');

        $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
        $turnover = array_sum($turnovers);
        $total_turnover = $turnover;

        $date = date('Y-m-d');
        $percent = 2 / 100;
        $unpaid_months = array();
        $total_balance = 0;
        $total_fees = 0;
        $total_penalty = 0;
        $total_paid = 0;
        $outstanding = $total = 0;

        // Slab-based Prime plan fee, per updated remuneration table
        if (isset($name) && $name === 'Accountancy Prime') {
            $gto = $total_turnover / 100000;
            if ($gto <= 0) {
                $fees = 0;
            } else if ($gto <= 25) {
                $fees = 12000;
            } else if ($gto <= 50) {
                $fees = (20000 / 50) * $gto;
            } else if ($gto <= 75) {
                $fees = (25000 / 75) * $gto;
            } else if ($gto <= 100) {
                $fees = (30000 / 100) * $gto;
            } else {
                $fees = 30000 + (($gto - 100) * (10000 / 100));
            }
            $fees = round($fees, 2);
        } else if (isset($name) && $name === 'Accountancy Premium') {
            // Slab-based Premium plan fee, per updated remuneration table
            $gto = $total_turnover / 100000;
            if ($gto <= 0) {
                $fees = 0;
            } else if ($gto <= 25) {
                $fees = 15000;
            } else if ($gto <= 50) {
                $fees = (24000 / 50) * $gto;
            } else if ($gto <= 75) {
                $fees = (30000 / 75) * $gto;
            } else if ($gto <= 100) {
                $fees = (36000 / 100) * $gto;
            } else {
                $fees = 36000 + (($gto - 100) * (15000 / 100));
            }
            $fees = round($fees, 2);
        } else {
            $fees = $total_turnover / $package['turnover'];
            $fees *= $package['rate'];
        }
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

        $acc_fees = $count > 0 ? ($fees / $count) : 0;


        foreach ($accountancy as $single) {

            $days = $paid = $penalty = 0;

            $paid = !empty($single['paid']) ? $single['paid'] : 0;

            $outstanding = $total;

            if (isset($name) && ($name === 'Accountancy Prime' || $name === 'Accountancy Premium')) {

                if (isset($single['turnover']) && $single['turnover'] > 0) {

                    $acc_fees = $monthlyAccountsFee;

                } else {

                    $acc_fees = 0;

                }

            } else {

                if ($single['date'] != '') {

                    $acc_fees = $count > 0 ? ($fees / $count) : 0;

                } else {

                    $acc_fees = 0;

                }

            }
            $other_fee = $single['other_fee'] ?? 0;
            $balance = $outstanding + $acc_fees + $other_fee;

            if ($single['due_date'] < $date && $paid < $balance) {
                $balance -= $paid;
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
            } else {
                $balance -= $paid;
            }
            $total = $balance + $penalty;

            // Only include unpaid months
            if ($balance > 0) {
                $unpaid_months[] = array(
                    'id' => $single['id'],
                    'date' => $single['date'],
                    'month' => $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--',
                    'due_date' => $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--',
                    'outstanding' => $outstanding,
                    'acc_fees' => $acc_fees,
                    'penalty' => $penalty,
                    'balance' => $balance,
                    'total' => $total,
                    'days' => $days
                );
                $total_balance += $balance;
                $total_fees += $acc_fees;
                $total_penalty += $penalty;
            }
            $total_paid += $paid;
        }

        // Get only the last month's balance for payment
        $last_month_balance = 0;
        $last_month_data = null;
        $first_month_data = null;
        $payment_month_range = '';
        if (!empty($unpaid_months)) {
            $first_month_data = reset($unpaid_months);
            $last_month_data = end($unpaid_months);
            $last_month_balance = $last_month_data['balance'];

            // Create payment month range (FirstMonth-LastMonth)
            if (count($unpaid_months) > 1) {
                $payment_month_range = $first_month_data['month'] . '-' . $last_month_data['month'];
            } else {
                $payment_month_range = $last_month_data['month'];
            }
        }

        // Check customer GST setting and calculate GST if enabled
        $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $gst_rate = $gst_enabled ? 18.0 : 0.0;
        $gst_amount = $gst_enabled ? round(($last_month_balance * $gst_rate) / 100, 2) : 0;
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
                $sgst_amount = round(($last_month_balance * 9) / 100, 2);
                $cgst_amount = round(($last_month_balance * 9) / 100, 2);
                // Adjust for rounding differences
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

        $data['unpaid_months'] = $unpaid_months;
        $data['total_balance'] = $total_balance;
        $data['last_month_balance'] = $last_month_balance;
        $data['last_month_data'] = $last_month_data;
        $data['first_month_data'] = $first_month_data;
        $data['payment_month_range'] = $payment_month_range;
        $data['package'] = $package;
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

            // Get unpaid months data
            $yearval = getyearmonthvalues($year);
            $year1 = $yearval['year1'];
            $year2 = $yearval['year2'];
            $from = "$year1-04-01";
            $to = "$year2-03-31";

            $user_id_escaped = $this->db->escape($user_id);
            $firm_id_escaped = $this->db->escape($firm_id);
            $from_escaped = $this->db->escape($from);
            $to_escaped = $this->db->escape($to);
            $where2 = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.date>={$from_escaped} AND t1.date<={$to_escaped}";

            $accountancy = $this->service->getturnoverswithpayment($where2);

            if (empty($accountancy)) {
                $this->session->set_flashdata('err_msg', 'No Data Found!');
                redirect('reports/payment/');
                return;
            }

            $where = array('user_id' => $user_id, 'status' => 1);
            $query = $this->db->get_where('customer_packages', $where);
            if ($query->num_rows() == 0) {
                $this->session->set_flashdata('err_msg', 'Package not Active!');
                redirect('reports/');
                return;
            }

            $cpackage = $query->unbuffered_row('array');
            $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

            // Accounting Pay is only for Turnover type packages
            // Monthly type packages don't use turnover-based payment system
            if ($pkg_type == 'Monthly') {
                $this->session->set_flashdata('err_msg', 'Accounting Pay is not available for Monthly type packages. Monthly packages are auto-debited based on the fixed amount.');
                redirect('package/');
                return;
            }

            $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            $package = $this->master->getpackages(['name' => $name], 'single');

            $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
            $turnover = array_sum($turnovers);
            $total_turnover = $turnover;

            $date = date('Y-m-d');
            $percent = 2 / 100;
            $unpaid_months = array();
            $total_balance = 0;
            $total_fees = 0;
            $total_penalty = 0;
            $outstanding = $total = 0;

            // Slab-based Prime plan fee, per updated remuneration table
            if (isset($name) && $name === 'Accountancy Prime') {
                $gto = $total_turnover / 100000;
                if ($gto <= 0) {
                    $fees = 0;
                } else if ($gto <= 25) {
                    $fees = 12000;
                } else if ($gto <= 50) {
                    $fees = (20000 / 50) * $gto;
                } else if ($gto <= 75) {
                    $fees = (25000 / 75) * $gto;
                } else if ($gto <= 100) {
                    $fees = (30000 / 100) * $gto;
                } else {
                    $fees = 30000 + (($gto - 100) * (10000 / 100));
                }
                $fees = round($fees, 2);
            } else if (isset($name) && $name === 'Accountancy Premium') {
                // Slab-based Premium plan fee, per updated remuneration table
                $gto = $total_turnover / 100000;
                if ($gto <= 0) {
                    $fees = 0;
                } else if ($gto <= 25) {
                    $fees = 15000;
                } else if ($gto <= 50) {
                    $fees = (24000 / 50) * $gto;
                } else if ($gto <= 75) {
                    $fees = (30000 / 75) * $gto;
                } else if ($gto <= 100) {
                    $fees = (36000 / 100) * $gto;
                } else {
                    $fees = 36000 + (($gto - 100) * (15000 / 100));
                }
                $fees = round($fees, 2);
            } else {
                $fees = $total_turnover / $package['turnover'];
                $fees *= $package['rate'];
            }
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

            $acc_fees = $count > 0 ? ($fees / $count) : 0;


            foreach ($accountancy as $single) {

                $days = $paid = $penalty = 0;

                $paid = !empty($single['paid']) ? $single['paid'] : 0;

                $outstanding = $total;

                if (isset($name) && ($name === 'Accountancy Prime' || $name === 'Accountancy Premium')) {

                    if (isset($single['turnover']) && $single['turnover'] > 0) {

                        $acc_fees = $monthlyAccountsFee;

                    } else {

                        $acc_fees = 0;

                    }

                } else {

                    if ($single['date'] != '') {

                        $acc_fees = $count > 0 ? ($fees / $count) : 0;

                    } else {

                        $acc_fees = 0;

                    }

                }
                $other_fee = $single['other_fee'] ?? 0;
                $balance = $outstanding + $acc_fees + $other_fee;

                if ($single['due_date'] < $date && $paid < $balance) {
                    $balance -= $paid;
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
                } else {
                    $balance -= $paid;
                }
                $total = $balance + $penalty;

                if ($balance > 0) {
                    $unpaid_months[] = array(
                        'id' => $single['id'],
                        'date' => $single['date'],
                        'due_date' => $single['due_date'],
                        'balance' => $balance,
                        'total' => $total,
                        'acc_fees' => $acc_fees,
                        'penalty' => $penalty,
                        'outstanding' => $outstanding
                    );
                    $total_balance += $balance;
                    $total_fees += $acc_fees;
                    $total_penalty += $penalty;
                }
            }

            // Get only the last month's balance for validation
            $last_month_balance = 0;
            $last_month_data = null;
            if (!empty($unpaid_months)) {
                $last_month_data = end($unpaid_months);
                $last_month_balance = $last_month_data['balance'];
            }

            // Check customer GST setting to validate payment amount
            $customer_check = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
            $gst_enabled_check = !empty($customer_check) && !empty($customer_check['gst_enabled']) && $customer_check['gst_enabled'] == 1;
            $gst_amount_check = $gst_enabled_check ? round(($last_month_balance * 18) / 100, 2) : 0;
            $expected_total = $last_month_balance + $gst_amount_check;

            if (abs($amount - $expected_total) > 0.01) {
                $this->session->set_flashdata('err_msg', 'Payment amount does not match the expected total! Expected: ₹' . number_format($expected_total, 2) . ($gst_enabled_check ? ' (including GST)' : ''));
                redirect('reports/payment/');
                return;
            }

            // Check wallet balance before processing payment
            $this->load->model('Wallet_model', 'wallet');
            $wallet_balance = $this->wallet->getwalletbalance($user_id);

            if ($wallet_balance <= 0) {
                $this->session->set_flashdata('err_msg', 'Insufficient wallet balance! Your wallet balance is ₹' . number_format($wallet_balance, 2) . '. Please add funds to your wallet first.');
                redirect('reports/payment/');
                return;
            }

            $total_amount_needed = $expected_total;

            if ($wallet_balance < $total_amount_needed) {
                $needed = round($total_amount_needed - $wallet_balance, 2);
                $this->session->set_flashdata('err_msg', 'Insufficient wallet balance! You need ₹' . number_format($needed, 2) . ' more. Current balance: ₹' . number_format(round($wallet_balance, 2), 2) . ', Required: ₹' . number_format($total_amount_needed, 2) . ($gst_enabled_check ? ' (including GST)' : ''));
                redirect('reports/payment/');
                return;
            }

            // Process payment for ALL unpaid months (from first to last)

            if (!empty($unpaid_months)) {
                $payment_success = true;
                $payment_errors = array();
                $previous_balance = 0;

                // Process payment for each unpaid month
                // Calculate the incremental amount for each month (current balance - previous balance)
                // This ensures we only pay the amount for each month, not the cumulative total
                foreach ($unpaid_months as $month_data) {
                    // Calculate this month's incremental payment amount
                    // Current balance minus previous balance = this month's accounts fee + penalty
                    $month_payment_amount = $month_data['balance'] - $previous_balance;

                    // Skip if amount is zero or negative (shouldn't happen, but safety check)
                    if ($month_payment_amount <= 0) {
                        continue;
                    }

                    // Update previous balance for next iteration
                    $previous_balance = $month_data['balance'];

                    // Convert due_date from d-m-Y format to Y-m-d format for database
                    $payment_date = date('Y-m-d');
                    if (!empty($month_data['due_date']) && $month_data['due_date'] != '--') {
                        // due_date is in d-m-Y format, convert to Y-m-d
                        $due_date_parts = explode('-', $month_data['due_date']);
                        if (count($due_date_parts) == 3) {
                            $payment_date = $due_date_parts[2] . '-' . $due_date_parts[1] . '-' . $due_date_parts[0];
                        }
                    }

                    // Ensure acc_date is in correct format (should already be Y-m-d from database)
                    $acc_date = !empty($month_data['date']) ? $month_data['date'] : date('Y-m-d');

                    // Calculate GST for this month's payment if enabled (use customer_check defined earlier)
                    $month_gst = $gst_enabled_check ? round(($month_payment_amount * 18) / 100, 2) : 0;
                    $month_total = round($month_payment_amount + $month_gst, 2);

                    $payment_data = array(
                        'user_id' => $user_id,
                        'firm_id' => $firm_id,
                        'year' => $year,
                        'acc_date' => $acc_date,
                        'date' => $payment_date,
                        'amount' => $month_total, // Include GST in payment amount
                        'added_by' => $user['id']
                    );

                    $result = $this->wallet->makeaccountancypayment($payment_data);
                    if ($result['status'] !== true) {
                        $payment_success = false;
                        $payment_errors[] = $result['message'] . ' (Month: ' . (!empty($month_data['date']) ? date('F Y', strtotime($month_data['date'])) : 'N/A') . ', Amount: ₹' . number_format($month_payment_amount, 2) . ')';
                    }
                }

                if (!$payment_success) {
                    $this->session->set_flashdata('err_msg', 'Payment partially failed: ' . implode(', ', $payment_errors));
                    redirect('reports/payment/');
                    return;
                }
            } else {
                $this->session->set_flashdata('err_msg', 'No unpaid month found!');
                redirect('reports/payment/');
                return;
            }

            // Get customer details for invoice
            $this->load->model('Customer_model', 'customer');
            $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
            $firm = $this->db->get_where('firms', ['id' => $firm_id])->row_array();

            // Check if GST is enabled for this customer
            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

            // Calculate GST - last_month_balance is base rate, GST is added on top
            $subtotal = $last_month_balance; // Base rate
            $gst_rate = $gst_enabled ? 18.0 : 0.0;
            $gst_amount = $gst_enabled ? round(($last_month_balance * $gst_rate) / 100, 2) : 0; // 18% of base rate
            $total_amount = $subtotal + $gst_amount; // Total = base + GST

            // Generate invoice for the payment - show period range from first to last unpaid month
            $this->load->model('Invoice_model', 'invoice');

            // Get first and last unpaid months for period display
            $period_value = '';
            if (!empty($unpaid_months)) {
                $first_month = reset($unpaid_months);
                $last_month = end($unpaid_months);

                // Format: april-25/july-25 (first month/last month) - lowercase month name
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
            $payment_msg .= ' processed successfully!' . (!empty($invoice_no) ? ' Invoice: ' . $invoice_no : '');
            $this->session->set_flashdata('msg', $payment_msg);
            redirect('reports/');
        } else {
            redirect('reports/payment/');
        }
    }
}
