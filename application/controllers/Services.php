<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Services extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        logrequest();
        //checkcookie();
        if ($this->session->role != 'customer') {
            redirect('home/');
        }
        // Load invoice model for billing
        $this->load->model('Invoice_model', 'invoice');
    }

    /**
     * Normalize stored period value to dropdown key + human label.
     * Handles both canonical IDs (e.g. 202504 / 2025Q1) and older text values.
     */
    private function normalizePeriodValue($period_value, $type, $year)
    {
        $result = array('id' => '', 'label' => '');
        if (empty($period_value)) {
            return $result;
        }

        $period_value = trim((string) $period_value);

        // Preferred/canonical format: helper can decode it directly.
        $period_info = getyearmonthvalues($period_value);
        if (!empty($period_info) && !empty($period_info['value'])) {
            $result['id'] = $period_value;
            $result['label'] = $period_info['value'];
            return $result;
        }

        // Backward compatibility: stored as text label on some environments.
        $options = array();
        if ($type === 'Monthly') {
            $options = month_dropdown($year);
        } elseif ($type === 'Quarterly') {
            $options = quarter_dropdown($year);
        }
        if (!empty($options)) {
            foreach ($options as $id => $label) {
                if ($id === '') {
                    continue;
                }
                if (strcasecmp(trim((string) $label), $period_value) === 0 || strcasecmp(trim((string) $id), $period_value) === 0) {
                    $result['id'] = (string) $id;
                    $result['label'] = (string) $label;
                    return $result;
                }
            }
        }

        // Last fallback: show raw value so table never shows just "Pending" for period column.
        $result['label'] = $period_value;
        return $result;
    }

    /**
     * Firm-wise KYC gate for purchases (optional).
     */
    private function hasFirmKycForPurchase($user_id, $firm_id)
    {
        return true;
    }

    public function index()
    {
        $data = ['title' => 'Services'];
        $data['breadcrumb'] = array("active" => "Services");
        $user = getuser();
        $data['user'] = $user;
        $firm_id = $this->session->firm;
        $year    = $this->session->year;
        $where = array();
        $services = $this->master->getservices($where);

        // Load service options for services that have dynamic options
        if (!empty($services)) {
            foreach ($services as $key => $service) {
                $service_options = $this->master->getserviceoptions(array('service_id' => $service['id'], 'status' => 1), 'all');
                if (!empty($service_options)) {
                    $services[$key]['has_options'] = true;
                    $services[$key]['options'] = $service_options;
                } else {
                    $services[$key]['has_options'] = false;
                }
            }
        }

        // Collect service IDs that are already in ANY of the user's service packages
        $package_service_ids = array();
        if (!empty($user['id']) && !empty($firm_id) && !empty($year)) {
            $all_packages = $this->customer->getservicepackage(
                ['t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year],
                'all'
            );
            if (!empty($all_packages)) {
                foreach ($all_packages as $pkg) {
                    if (!empty($pkg['service_ids'])) {
                        $ids = array_filter(array_map('trim', explode(',', $pkg['service_ids'])));
                        $package_service_ids = array_unique(array_merge($package_service_ids, $ids));
                    }
                }
            }
        }

        $data['services'] = $services;
        $data['package_service_ids'] = $package_service_ids;
        $data['wallet_balance'] = $this->wallet->getwalletbalance($user['id']);
        $data['datatable'] = true;
        $this->template->load('services', 'services', $data);
    }

    /**
     * AJAX endpoint to get service options
     */
    public function getserviceoptions()
    {
        $service_id = $this->input->post('service_id');
        if (empty($service_id)) {
            echo json_encode(array('status' => false, 'message' => 'Service ID required'));
            return;
        }

        $options = $this->master->getserviceoptions(array('service_id' => $service_id, 'status' => 1), 'all');
        $pricing = array();
        $display_names = array();

        if (!empty($options)) {
            foreach ($options as $option) {
                $pricing[$option['option_key']] = $option['rate'];
                $display_names[$option['option_key']] = $option['display_name'];
            }
        }

        echo json_encode(array(
            'status' => true,
            'pricing' => $pricing,
            'display_names' => $display_names,
            'options' => $options
        ));
    }

    public function purchasedservices()
    {
        $data = ['title' => 'Purchased Services'];
        $data['breadcrumb'] = array("active" => "Purchased Services");
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user = getuser();
        $data['user'] = $user;

        // Validate required session data
        if (empty($year) || empty($firm_id) || empty($user['id'])) {
            $this->session->set_flashdata('err_msg', 'Please select Year and Firm!');
            redirect($_SERVER['HTTP_REFERER'] ?? 'home/');
            return;
        }

        // Use array format for WHERE clause (more reliable than string)
        $where = array(
            't1.user_id' => $user['id'],
            't1.firm_id' => $firm_id,
            't1.year' => $year
        );

        // Pass group_by as a parameter or handle it in the model
        $services = $this->service->getpurchasedservices($where, 'all', true); // Pass flag for group_by

        // Get service package to retrieve service options from package
        $service_package = $this->customer->getservicepackage(['t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year], 'single');
        $package_service_options = array();
        if (!empty($service_package) && !empty($service_package['service_option_ids'])) {
            $package_service_options = json_decode($service_package['service_option_ids'], true);
            if (!is_array($package_service_options)) {
                $package_service_options = array();
            }
        }

        // Log for debugging
        if (ENVIRONMENT !== 'production') {
            log_message('debug', 'purchasedservices - User ID: ' . $user['id'] . ', Firm ID: ' . $firm_id . ', Year: ' . $year);
            log_message('debug', 'purchasedservices - Services found: ' . count($services));
            log_message('debug', 'purchasedservices - Package service options: ' . json_encode($package_service_options));
            // Also log the WHERE clause for debugging
            log_message('debug', 'purchasedservices - WHERE: ' . json_encode($where));
        }

        if (!empty($services)) {
            foreach ($services as $key => $service) {
                $services[$key]['name'] = $service['service_name'];
                $services[$key]['count'] = '';
                $services[$key]['link'] = ('services/monthlyservices/' . $service['service_slug']);

                // First check if service_option_display exists in purchase record (for manually purchased services)
                $service_option_display = !empty($service['service_option_display']) ? $service['service_option_display'] : '';

                // If not found in purchase record, check package service options
                if (empty($service_option_display) && !empty($package_service_options)) {
                    $service_id = $service['service_id'];
                    if (!empty($package_service_options[$service_id])) {
                        $option_id = $package_service_options[$service_id];
                        // Handle both old array format and new single value format for backward compatibility
                        if (is_array($option_id)) {
                            $option_id = !empty($option_id[0]) ? $option_id[0] : '';
                        }

                        if (!empty($option_id)) {
                            // Get option display name from service_options table
                            $option = $this->master->getserviceoptions(array('id' => $option_id, 'status' => 1), 'single');
                            if (!empty($option) && !empty($option['display_name'])) {
                                $service_option_display = $option['display_name'];
                            }
                        }
                    }
                }

                $services[$key]['service_option_display'] = $service_option_display;
            }
        } else {
            // Log when no services found for debugging
            log_message('info', 'purchasedservices - No services found for User ID: ' . $user['id'] . ', Firm ID: ' . $firm_id . ', Year: ' . $year);
        }
        $data['services'] = $services;
        //print_pre($data,true);
        $data['datatable'] = true;
        //$data['styles']=array('file'=>'includes/custom/folder.css');
        //$data['folders']=$folders;
        $this->template->load('services', 'purchasedservices', $data);
    }

    public function pendingservices()
    {
        $data = ['title' => 'Pending Services'];
        $data['breadcrumb'] = array("active" => "Pending Services");
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user = getuser();
        $data['user'] = $user;

        // Validate required session data
        if (empty($year) || empty($firm_id)) {
            $this->session->set_flashdata('err_msg', 'Please select Year and Firm!');
            redirect($_SERVER['HTTP_REFERER'] ?? 'home/');
            return;
        }

        // ── Service packages ───────────────────────────
        $all_service_pkgs = array();
        $expired_pkgs     = array();
        $all_user_pkgs    = $this->customer->getservicepackage(
            ['t1.user_id' => $user['id'], 't1.firm_id' => $firm_id],
            'all'
        );
        if (!empty($all_user_pkgs)) {
            foreach ($all_user_pkgs as $_pkg) {
                $exp       = !empty($_pkg['expiry_date']) ? strtotime($_pkg['expiry_date']) : 0;
                $is_unpaid = empty($_pkg['payment_status']) || $_pkg['payment_status'] == 0 || ($_pkg['auto_debit_status'] ?? '') === 'Pending';
                $is_exp    = $is_unpaid;

                // Resolve service names for display
                $svc_ids   = array_filter(array_map('trim', explode(',', $_pkg['service_ids'] ?? '')));
                $svc_names = [];
                foreach ($svc_ids as $_sid) {
                    $svc = $this->master->getservices(['id' => (int)$_sid], 'single');
                    if (!empty($svc['name'])) {
                        $svc_names[] = $svc['name'];
                    }
                }
                $_pkg['service_names']  = $svc_names;
                $_pkg['package_source'] = 'service_packages';
                $_pkg['is_expired']     = $is_exp ? 1 : 0;

                $all_service_pkgs[] = $_pkg;
                if ($is_exp) {
                    $expired_pkgs[] = $_pkg;
                }
            }
        }

        // ── Account Work packages ───────────────────────
        $all_account_work     = array();
        $expired_account_work = array();
        $account_work_pkgs    = $this->db->get_where('customer_packages', [
            'user_id' => $user['id'],
            'firm_id' => $firm_id,
            'status'  => 1
        ])->result_array();
        
        if (!empty($account_work_pkgs)) {
            foreach ($account_work_pkgs as $_acpkg) {
                $exp       = !empty($_acpkg['expiry_date']) ? strtotime($_acpkg['expiry_date']) : 0;
                $is_unpaid = empty($_acpkg['payment_status']) || $_acpkg['payment_status'] == 0 || ($_acpkg['auto_debit_status'] ?? '') === 'Pending';
                $is_exp    = $is_unpaid;

                // For Turnover type, use service rate
                if (empty($_acpkg['bill_amount']) || $_acpkg['bill_amount'] == 0) {
                    $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                    $_acpkg['bill_amount'] = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
                }
                
                $package_id    = $_acpkg['package_id'];
                $service_name  = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                $_acpkg['service_names']  = ['Account Work (' . $service_name . ')'];
                $_acpkg['package_source'] = 'customer_packages';
                $_acpkg['package_type']   = !empty($_acpkg['package_type']) ? $_acpkg['package_type'] : 'Turnover';
                $_acpkg['is_expired']     = $is_exp ? 1 : 0;

                $all_account_work[] = $_acpkg;
                if ($is_exp) {
                    $expired_account_work[] = $_acpkg;
                }
            }
        }
        
        // Helper inline closure for deduplicating package records by expiry date
        $_dedupe = function($pkgs) {
            if (empty($pkgs)) return [];
            $grouped = [];
            foreach ($pkgs as $p) {
                $key = ($p['package_type'] ?? '') . '_' . ($p['expiry_date'] ?? '');
                if (!isset($grouped[$key])) {
                    $grouped[$key] = $p;
                } else {
                    if (empty($grouped[$key]['payment_status']) && !empty($p['payment_status'])) {
                        $grouped[$key] = $p;
                    }
                }
            }
            return array_values($grouped);
        };

        // Load wallet balance and credit limit for summary header
        $this->load->model('Wallet_model', 'wallet');
        $data['wallet_balance']   = $this->wallet->getwalletbalance($user['id']);
        $data['credit_limit']     = $this->wallet->get_available_credit($user['id']);

        // Merge both types of packages
        $data['expired_packages'] = $_dedupe(array_merge($expired_pkgs, $expired_account_work));
        $data['all_packages']     = $_dedupe(array_merge($all_service_pkgs, $all_account_work));

        $this->template->load('services', 'pendingservices', $data);
    }

    /**
     * Renew a service from expired package into current year's package.
     * - Adds the service_id back into current year service_packages row (create or update).
     * - Does NOT create a purchase; this is purely package-level renewal for now.
     */
    public function renewservice()
    {
        // Only customers can renew
        if ($this->session->role != 'customer') {
            echo json_encode(array('status' => false, 'message' => 'Unauthorized request.'));
            return;
        }

        $service_id = $this->input->post('service_id');
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;

        if (empty($service_id) || !is_numeric($service_id)) {
            echo json_encode(array('status' => false, 'message' => 'Invalid service selected.'));
            return;
        }
        if (empty($user['id']) || empty($firm_id) || empty($year)) {
            echo json_encode(array('status' => false, 'message' => 'Please select Year and Firm before renewing services.'));
            return;
        }

        // Make sure service exists and is active
        $service = $this->master->getservices(array('id' => $service_id, 'status' => 1), 'single');
        if (empty($service)) {
            echo json_encode(array('status' => false, 'message' => 'Service not found or inactive.'));
            return;
        }

        // Check that this service existed in at least one expired package for this user/firm
        $user_id_escaped = $this->db->escape($user['id']);
        $firm_id_escaped = $this->db->escape($firm_id);
        $year_escaped = $this->db->escape($year);
        $service_id_int = (int)$service_id;

        $expired_check_sql = "
            SELECT t1.*
            FROM " . $this->db->dbprefix('service_packages') . " t1
            WHERE t1.user_id = {$user_id_escaped}
              AND t1.firm_id = {$firm_id_escaped}
              AND t1.year != {$year_escaped}
              AND FIND_IN_SET(" . $service_id_int . ", t1.service_ids) > 0
            LIMIT 1
        ";
        $expired_row = $this->db->query($expired_check_sql)->unbuffered_row('array');

        if (empty($expired_row)) {
            echo json_encode(array('status' => false, 'message' => 'This service is not part of any previous package for renewal.'));
            return;
        }

        // Get or create current year package
        $current_package = $this->customer->getservicepackage(
            array('t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year),
            'single'
        );

        $current_ids = array();
        if (!empty($current_package) && !empty($current_package['service_ids'])) {
            $current_ids_raw = explode(',', $current_package['service_ids']);
            $current_ids = array_filter(array_map('trim', $current_ids_raw));
        }

        // If already present in current package, nothing to do
        if (in_array((string)$service_id_int, $current_ids, true)) {
            echo json_encode(array('status' => true, 'message' => 'Service is already active in your current package.'));
            return;
        }

        $current_ids[] = (string)$service_id_int;
        $current_ids = array_unique(array_filter($current_ids));

        $data = array(
            'user_id' => $user['id'],
            'firm_id' => $firm_id,
            'year' => $year,
            'service_ids' => implode(',', $current_ids)
        );

        // Preserve existing service_option_ids if any
        if (!empty($current_package) && isset($current_package['service_option_ids'])) {
            $data['service_option_ids'] = $current_package['service_option_ids'];
        }

        $result = $this->customer->createpackage($data);

        if (!empty($result['status']) && $result['status'] === true) {
            // Create an invoice for this renewed service (package-level billing)
            try {
                // Fetch customer & firm info for billing header
                $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                $firm_info = $this->customer->getfirms(['t1.id' => $firm_id], 'single');

                $subtotal = isset($service['rate']) ? (float)$service['rate'] : 0.0;
                $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                $gst_rate = $gst_enabled ? 18.0 : 0.0;
                $gst_amount = $gst_enabled ? round(($subtotal * $gst_rate) / 100, 2) : 0.0;
                $total = $subtotal + $gst_amount;

                // Determine a sensible type/period for renewal billing
                $types = !empty($service['type']) ? explode(',', $service['type']) : [];
                $primary_type = !empty($types[0]) ? trim($types[0]) : 'Yearly';

                $invoice_data = [
                    'user_id'        => $user['id'],
                    'firm_id'        => $firm_id,
                    'year'           => $year,
                    'invoice_date'   => date('Y-m-d'),
                    'billing_name'   => !empty($customer['name']) ? $customer['name'] : $user['name'],
                    'billing_email'  => !empty($customer['email']) ? $customer['email'] : (isset($user['email']) ? $user['email'] : ''),
                    'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : (isset($user['mobile']) ? $user['mobile'] : ''),
                    'firm_name'      => !empty($firm_info['name']) ? $firm_info['name'] : '',
                    'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                    'firm_pan'       => !empty($firm_info['pan']) ? $firm_info['pan'] : '',
                    'service_name'   => $service['name'] . ' (Renewal)',
                    'type'           => $primary_type,
                    'period_value'   => $year,
                    'subtotal'       => $subtotal,
                    'gst_rate'       => $gst_rate,
                    'gst_amount'     => $gst_amount,
                    'total_amount'   => $total,
                ];

                if (isset($this->invoice) && method_exists($this->invoice, 'create_custom_invoice')) {
                    $invoice_result = $this->invoice->create_custom_invoice($invoice_data);
                    if (empty($invoice_result['status']) || $invoice_result['status'] !== true) {
                        log_message('error', 'Renewal invoice creation failed for user ' . $user['id'] . ', service ' . $service_id . ': ' . $invoice_result['message']);
                    }
                }
            } catch (Exception $e) {
                log_message('error', 'Renewal invoice unexpected error: ' . $e->getMessage());
            }

            // Customer notification: package / subscription renewal
            $notifydata = array(
                "type"    => "package",
                "user_id" => $user['id'],
                "message" => 'Your service "' . $service['name'] . '" has been renewed for year ' . $year . '.',
            );
            if (isset($this->common) && method_exists($this->common, 'savenotification')) {
                $this->common->savenotification($notifydata);
            }

            echo json_encode(array('status' => true, 'message' => 'Service renewed and added to your current package successfully.'));
        } else {
            $message = !empty($result['message']) ? $result['message'] : 'Failed to renew service. Please try again.';
            echo json_encode(array('status' => false, 'message' => $message));
        }
    }

    /**
     * Manually renew an expired service package from Pending Services.
     * Deducts bill_amount from wallet, creates purchase rows, generates
     * invoice, and extends the package expiry.
     *
     * POST param: package_id
     */
    public function renewpackage()
    {
        header('Content-Type: application/json');

        if ($this->session->role != 'customer') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized.']);
            return;
        }

        $package_id = (int)$this->input->post('package_id');
        $user       = getuser();

        if (empty($package_id)) {
            echo json_encode(['status' => false, 'message' => 'Invalid package.']);
            return;
        }

        // Fetch package owned by this user - check both service_packages and customer_packages
        $pkg = $this->db->get_where(
            'service_packages',
            ['id' => $package_id, 'user_id' => $user['id']]
        )->unbuffered_row('array');
        
        $is_account_work = false;
        if (empty($pkg)) {
            // Try customer_packages (Account Work)
            $pkg = $this->db->get_where(
                'customer_packages',
                ['id' => $package_id, 'user_id' => $user['id']]
            )->unbuffered_row('array');
            $is_account_work = !empty($pkg);
        }

        if (empty($pkg)) {
            echo json_encode(['status' => false, 'message' => 'Package not found.']);
            return;
        }

        // Must be expired to renew
        $exp = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
        if (!$exp || $exp > time()) {
            echo json_encode(['status' => false, 'message' => 'This package has not expired yet.']);
            return;
        }

        $bill = (float)($pkg['bill_amount'] ?? 0);
        if ($bill <= 0) {
            echo json_encode(['status' => false, 'message' => 'No bill amount for this package.']);
            return;
        }

        $pkg_type = !empty($pkg['package_type']) ? $pkg['package_type'] : ($is_account_work ? 'Turnover' : 'Yearly');
        $firm_id  = $pkg['firm_id'];
        $year     = $pkg['year'];
        $today    = date('Y-m-d');
        $datetime = date('Y-m-d H:i:s');

        // ── Handle Account Work packages ────────────────────────────────
        if ($is_account_work) {
            // Check if this is a Monthly Account Work package (supports cascade renewal for prior months)
            if ($pkg_type == 'Monthly') {
                $sel_pdate = !empty($pkg['purchase_date']) ? $pkg['purchase_date'] : $today;
                $sel_id    = (int)$pkg['id'];

                // Fetch all active Monthly customer_packages for user/firm/year
                $all_monthly_pkgs = $this->db->get_where('customer_packages', [
                    'user_id'      => $user['id'],
                    'firm_id'      => $firm_id,
                    'year'         => $year,
                    'package_type' => 'Monthly',
                    'status'       => 1
                ])->result_array();

                // Filter for expired & unpaid packages up to the selected package (by purchase_date or id)
                $pkgs_to_renew = [];
                if (!empty($all_monthly_pkgs)) {
                    foreach ($all_monthly_pkgs as $_p) {
                        $exp = !empty($_p['expiry_date']) ? strtotime($_p['expiry_date']) : 0;
                        $is_unpaid = empty($_p['payment_status']) || $_p['payment_status'] == 0;
                        $_p_date = !empty($_p['purchase_date']) ? $_p['purchase_date'] : $today;
                        $_p_id   = (int)$_p['id'];

                        if ($exp && $exp <= time() && $is_unpaid) {
                            if (strtotime($_p_date) < strtotime($sel_pdate) || (strtotime($_p_date) == strtotime($sel_pdate) && $_p_id <= $sel_id)) {
                                $pkgs_to_renew[] = $_p;
                            }
                        }
                    }
                }

                // Ensure current package is included
                $ids_to_renew = array_column($pkgs_to_renew, 'id');
                if (!in_array($pkg['id'], $ids_to_renew)) {
                    $pkgs_to_renew[] = $pkg;
                }

                // Sort chronologically by purchase_date ASC
                usort($pkgs_to_renew, function ($a, $b) {
                    $da = !empty($a['purchase_date']) ? strtotime($a['purchase_date']) : 0;
                    $db = !empty($b['purchase_date']) ? strtotime($b['purchase_date']) : 0;
                    if ($da == $db) {
                        return ((int)$a['id']) - ((int)$b['id']);
                    }
                    return $da - $db;
                });

                // Calculate total base amount and GST
                $customer    = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                $gst_rate    = $gst_enabled ? 18.0 : 0.0;

                $total_subtotal = 0;
                $month_labels   = [];
                foreach ($pkgs_to_renew as $_p) {
                    $m_rate = (float)($_p['bill_amount'] ?? 0);
                    if ($m_rate <= 0 && !empty($_p['amount'])) {
                        $m_rate = (float)$_p['amount'];
                    }
                    if ($m_rate <= 0) {
                        $m_rate = 500;
                    }
                    $total_subtotal += $m_rate;

                    $m_date = !empty($_p['purchase_date']) ? $_p['purchase_date'] : null;
                    if ($m_date) {
                        $month_labels[] = date('F Y', strtotime($m_date));
                    }
                }

                $total_gst_amount = $gst_enabled ? round(($total_subtotal * $gst_rate) / 100, 2) : 0;
                $total_required_amount = $total_subtotal + $total_gst_amount;

                // Check wallet and credit limit
                $wallet_balance = $this->wallet->getwalletbalance($user['id']);
                $credit_balance = $this->wallet->get_available_credit($user['id']);

                $is_credit_limit = false;
                if ($wallet_balance >= $total_required_amount) {
                    $is_credit_limit = false;
                } elseif ($credit_balance >= $total_required_amount) {
                    $is_credit_limit = true;
                } else {
                    $m_str = !empty($month_labels) ? ' (' . implode(', ', $month_labels) . ')' : '';
                    echo json_encode([
                        'status'   => false,
                        'message'  => 'Insufficient funds for renewing ' . count($pkgs_to_renew) . ' month(s)' . $m_str . '. Wallet Balance: ₹' . number_format($wallet_balance, 2) . ', Credit Limit: ₹' . number_format($credit_balance, 2) . '. Required: ₹' . number_format($total_required_amount, 2) . '.',
                        'redirect' => base_url('mywallet/')
                    ]);
                    return;
                }

                $insert_type = $is_credit_limit ? 'Credit limit' : 'Monthly';

                // Process renewal for each month in order
                $service_1 = $this->master->getservices(['id' => 1], 'single');
                $debit_date = !empty($service_1['debit_date']) ? $service_1['debit_date'] : null;
                $dd = $debit_date ? (int)date('d', strtotime($debit_date)) : 28;

                foreach ($pkgs_to_renew as $_p) {
                    $m_rate = (float)($_p['bill_amount'] ?? 0);
                    if ($m_rate <= 0 && !empty($_p['amount'])) {
                        $m_rate = (float)$_p['amount'];
                    }
                    if ($m_rate <= 0) $m_rate = 500;

                    $m_gst_amount   = $gst_enabled ? round(($m_rate * $gst_rate) / 100, 2) : 0;
                    $m_total_amount = $m_rate + $m_gst_amount;
                    $m_date_str     = !empty($_p['purchase_date']) ? date('F Y', strtotime($_p['purchase_date'])) : 'Monthly';

                    // 1. Insert purchase row
                    $this->db->insert('purchases', [
                        'date'        => $today,
                        'year'        => $year,
                        'type'        => $insert_type,
                        'user_id'     => $user['id'],
                        'service_id'  => 1,
                        'firm_id'     => $firm_id,
                        'service'     => 'Account Work Monthly (' . $m_date_str . ' Renewal)',
                        'rate'        => $m_rate,
                        'subtotal'    => $m_rate,
                        'gst_amount'  => $m_gst_amount,
                        'gst_enabled' => $gst_enabled ? 1 : 0,
                        'amount'      => $m_total_amount,
                        'status'      => 0,
                        'added_on'    => $datetime,
                        'updated_on'  => $datetime,
                    ]);

                    // 2. Update package expiry (next month debit date)
                    $p_ts = !empty($_p['purchase_date']) ? strtotime($_p['purchase_date']) : strtotime($today);
                    $next_month = strtotime('+1 month', $p_ts);
                    $nm = (int)date('m', $next_month);
                    $ny = (int)date('Y', $next_month);
                    $new_expiry = sprintf('%04d-%02d-%02d', $ny, $nm, $dd);
                    if (!checkdate($nm, $dd, $ny)) {
                        $new_expiry = date('Y-m-t', $next_month);
                    }

                    $this->db->update('customer_packages', [
                        'payment_status' => 0,
                        'purchase_date'  => $today,
                        'expiry_date'    => $new_expiry,
                        'updated_on'     => $datetime,
                    ], ['id' => $_p['id']]);

                    // 3. Update accountancy other_fee
                    $month_start = !empty($_p['purchase_date']) ? date('Y-m-01', strtotime($_p['purchase_date'])) : date('Y-m-01');
                    $acc_record  = $this->db->get_where('accountancy', [
                        'user_id' => $user['id'],
                        'firm_id' => $firm_id,
                        'date'    => $month_start
                    ])->unbuffered_row('array');

                    if (!empty($acc_record)) {
                        $existing_other_fee = (float)($acc_record['other_fee'] ?? 0);
                        $new_other_fee = $existing_other_fee + $m_rate;
                        $this->db->update('accountancy', [
                            'other_fee'  => $new_other_fee,
                            'updated_on' => $datetime
                        ], ['id' => $acc_record['id']]);
                    } else {
                        $acc_data = [
                            'user_id'    => $user['id'],
                            'firm_id'    => $firm_id,
                            'year'       => $year,
                            'date'       => $month_start,
                            'turnover'   => 0,
                            'other_fee'  => $m_rate,
                            'due_date'   => date('Y-m-d', strtotime('+1 month', strtotime($month_start))),
                            'added_by'   => $user['id'],
                            'status'     => 1,
                            'added_on'   => $datetime,
                            'updated_on' => $datetime
                        ];
                        $this->service->saveturnover($acc_data);
                    }
                }

                // Generate combined invoice
                $firm_info = $this->customer->getfirms(['t1.id' => $firm_id], 'single');
                $inv_no = '';
                try {
                    $inv_result = $this->invoice->create_custom_invoice([
                        'user_id'        => $user['id'],
                        'firm_id'        => $firm_id,
                        'year'           => $year,
                        'invoice_date'   => $today,
                        'billing_name'   => !empty($customer['name'])   ? $customer['name']   : $user['name'],
                        'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                        'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                        'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                        'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                        'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                        'service_name'   => 'Account Work Monthly Renewal (' . implode(', ', $month_labels) . ')',
                        'type'           => 'Monthly',
                        'period_value'   => $year,
                        'subtotal'       => round($total_subtotal, 2),
                        'gst_rate'       => $gst_rate,
                        'gst_amount'     => round($total_gst_amount, 2),
                        'total_amount'   => $total_required_amount,
                    ]);
                    if (!empty($inv_result['status']) && $inv_result['status'] === true) {
                        $inv_no = $inv_result['invoice']['invoice_no'];
                    }
                } catch (Exception $e) {
                    log_message('error', 'Renewal invoice error: ' . $e->getMessage());
                }

                $m_desc = !empty($month_labels) ? implode(', ', $month_labels) : count($pkgs_to_renew) . ' month(s)';
                $msg = 'Renewal successful for ' . count($pkgs_to_renew) . ' month(s) [' . $m_desc . '] totaling ₹' . number_format($total_required_amount, 2) . '!';
                if ($inv_no) $msg .= ' Invoice: ' . $inv_no;

                echo json_encode(['status' => true, 'message' => $msg]);
                return;
            }

            // For Turnover type, recalculate bill_amount if needed
            if ($pkg_type == 'Turnover' && (empty($pkg['bill_amount']) || $pkg['bill_amount'] == 0)) {
                $dates = getfiscaldates(date('Y-m-d', strtotime($pkg['purchase_date'] ?? $pkg['added_on'])));
                $from = $dates['from'];
                $to = $dates['to'];
                $where2 = "t1.user_id='{$user['id']}' and t1.firm_id='{$firm_id}' and t1.date>='$from' and t1.date<='$to'";
                $accountancy = $this->service->getturnoverswithpayment($where2);
                $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
                $turnover = array_sum($turnovers);
                $total_turnover = $turnover * 100000; // multiplier
                
                $package_id = $pkg['package_id'];
                $name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                $package = $this->master->getpackages(['name' => $name, 'turnover>' => $turnover], 'single');
                
                if (!empty($package)) {
                    if (isset($name) && $name === 'Accountancy Prime' && $total_turnover > 10000000) {
                        $fees = 30000 + (floor(($total_turnover - 10000000) / 10000000) * 10000);
                    } elseif (isset($name) && $name === 'Accountancy Premium') {
                        if ($total_turnover <= 2500000) $fees = 15000;
                        elseif ($total_turnover <= 5000000) $fees = 24000;
                        elseif ($total_turnover <= 7500000) $fees = 30000;
                        elseif ($total_turnover <= 10000000) $fees = 36000;
                        else $fees = 36000 + (ceil(($total_turnover - 10000000) / 10000000) * 15000);
                    } else {
                        $fees = $total_turnover / $package['turnover'];
                        $fees *= $package['rate'];
                    }
                    $bill = (float)$fees;
                } else {
                    $package = $this->master->getpackages(['name' => $name], 'single');
                    $bill = !empty($package['rate']) ? (float)$package['rate'] : 0;
                }
            }
            
            // ── Check customer GST setting first to calculate total amount ──────────────────────────────────
            $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

            // Calculate GST - bill is base rate (stored without GST), GST is added on top
            $subtotal = $bill; // Base rate (e.g., 7000)
            $gst_rate = $gst_enabled ? 18.0 : 0.0;
            $gst_amount = $gst_enabled ? round(($bill * $gst_rate) / 100, 2) : 0; // 18% of base rate (e.g., 1260)
            $total_amount = $subtotal + $gst_amount; // Total = base + GST (e.g., 8260)

            // Check wallet balance first, fall back to credit limit if wallet balance is insufficient
            $wallet_balance = $this->wallet->getwalletbalance($user['id']);
            $credit_balance = $this->wallet->get_available_credit($user['id']);

            $is_credit_limit = false;
            if ($wallet_balance >= $total_amount) {
                $is_credit_limit = false;
            } elseif ($credit_balance >= $total_amount) {
                $is_credit_limit = true;
            } else {
                echo json_encode([
                    'status'   => false,
                    'message'  => 'Insufficient funds. Wallet Balance: ₹' . number_format($wallet_balance, 2) . ', Credit Limit: ₹' . number_format($credit_balance, 2) . '. Required: ₹' . number_format($total_amount, 2) . '.',
                    'redirect' => base_url('mywallet/')
                ]);
                return;
            }

            $insert_type = $is_credit_limit ? 'Credit limit' : $pkg_type;

            // For Monthly type, package_id is NULL/0, use generic name
            // For Turnover type, use package name
            if ($pkg_type == 'Monthly') {
                $service_name = 'Account Work Monthly';
            } else if (!empty($pkg['package_id'])) {
                $service_name = $pkg['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            } else {
                $service_name = 'Account Work';
            }

            // ── Create purchase row for Account Work ────────────────────────
            $this->db->insert('purchases', [
                'date'       => $today,
                'year'       => $year,
                'type'       => $insert_type,
                'user_id'    => $user['id'],
                'service_id' => 1, // Account Work
                'firm_id'    => $firm_id,
                'service'    => $service_name . ' (Account Work Renewal)',
                'rate'       => $subtotal, // Base rate
                'subtotal'   => $subtotal, // Base rate
                'gst_amount' => $gst_amount, // GST amount
                'gst_enabled' => $gst_enabled ? 1 : 0,
                'amount'     => $total_amount, // Total = base + GST
                'status'     => 0,
                'added_on'   => $datetime,
                'updated_on' => $datetime,
            ]);
        } else {
            // ── Resolve services for regular packages ─────────────────────────
            $s_ids    = array_filter(array_map('trim', explode(',', $pkg['service_ids'] ?? '')));
            $services = [];
            if (!empty($s_ids)) {
                $services = $this->master->getservices("status='1' AND id IN ('" . implode("','", $s_ids) . "')");
            }
            if (empty($services)) {
                echo json_encode(['status' => false, 'message' => 'No active services found in this package.']);
                return;
            }

            // Service option rates
            $opt_data = [];
            if (!empty($pkg['service_option_ids'])) {
                $opt_data = json_decode($pkg['service_option_ids'], true) ?: [];
            }

            // ── Check customer GST setting ──────────────────────────────────
            $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

            // ── Calculate total base rate and GST distribution ───────────────
            $total_base_rate = 0;
            $service_rates = [];
            foreach ($services as $svc) {
                $rate = (float)$svc['rate'];
                if (!empty($opt_data[$svc['id']])) {
                    $opt = $this->master->getserviceoptions(
                        ['id' => $opt_data[$svc['id']], 'status' => 1],
                        'single'
                    );
                    if (!empty($opt['rate'])) $rate = (float)$opt['rate'];
                }
                $service_rates[$svc['id']] = $rate;
                $total_base_rate += $rate;
            }

            // Calculate GST amounts if enabled - bill is base rate, GST is added on top
            $total_gst = 0;
            if ($gst_enabled && $total_base_rate > 0) {
                // GST is 18% of the base rate
                $total_gst = round(($bill * 18) / 100, 2);
            }

            $total_pkg_amount = $bill + $total_gst;
            $wallet_balance   = $this->wallet->getwalletbalance($user['id']);
            $credit_balance   = $this->wallet->get_available_credit($user['id']);

            $is_credit_limit = false;
            if ($wallet_balance >= $total_pkg_amount) {
                $is_credit_limit = false;
            } elseif ($credit_balance >= $total_pkg_amount) {
                $is_credit_limit = true;
            } else {
                echo json_encode([
                    'status'   => false,
                    'message'  => 'Insufficient funds. Wallet Balance: ₹' . number_format($wallet_balance, 2) . ', Credit Limit: ₹' . number_format($credit_balance, 2) . '. Required: ₹' . number_format($total_pkg_amount, 2) . '.',
                    'redirect' => base_url('mywallet/')
                ]);
                return;
            }

            $insert_type = $is_credit_limit ? 'Credit limit' : $pkg_type;

            // ── Create a purchase row per service (deducts from wallet or credit limit) ────
            foreach ($services as $svc) {
                $rate = $service_rates[$svc['id']];
                $subtotal = $rate;
                $gst_amount = 0;

                // Distribute GST proportionally across services
                if ($gst_enabled && $total_base_rate > 0 && $total_gst > 0) {
                    $gst_amount = round(($rate / $total_base_rate) * $total_gst, 2);
                }

                $amount = $subtotal + $gst_amount;

                $this->db->insert('purchases', [
                    'date'       => $today,
                    'year'       => $year,
                    'type'       => $insert_type,
                    'user_id'    => $user['id'],
                    'service_id' => $svc['id'],
                    'firm_id'    => $firm_id,
                    'service'    => $svc['name'] . ' (Package Renewal)',
                    'rate'       => $rate,
                    'subtotal'   => $subtotal,
                    'gst_amount' => $gst_amount,
                    'gst_enabled' => $gst_enabled ? 1 : 0,
                    'amount'     => $amount,
                    'status'     => 0,
                    'added_on'   => $datetime,
                    'updated_on' => $datetime,
                ]);
            }
        }

        // ── Calculate new expiry ───────────────────────────────────────
        $ts = strtotime($today);
        $new_expiry = null;
        
        if ($is_account_work && $pkg_type == 'Turnover') {
            // For Turnover type, use service debit_date if available
            $service = $this->master->getservices(['id' => 1], 'single');
            if (!empty($service['debit_date'])) {
                $dm = (int)date('m', strtotime($service['debit_date']));
                $dd = (int)date('d', strtotime($service['debit_date']));
                $cy = (int)date('Y', $ts);
                
                $candidate = sprintf('%04d-%02d-%02d', $cy, $dm, $dd);
                if (strtotime($candidate) <= $ts) {
                    $candidate = sprintf('%04d-%02d-%02d', $cy + 1, $dm, $dd);
                }
                $new_expiry = $candidate;
            } else {
                $new_expiry = date('Y-m-d', strtotime('+1 year', $ts));
            }
        } else {
            switch ($pkg_type) {
                case 'Monthly':
                    // For Monthly Account Work, use auto debit date (28th) of next month
                    if ($is_account_work) {
                        $service = $this->master->getservices(['id' => 1], 'single');
                        $debit_date = !empty($service['debit_date']) ? $service['debit_date'] : null;
                        if ($debit_date) {
                            $dd = (int)date('d', strtotime($debit_date)); // Day from debit_date (e.g., 28)
                            $next_month = strtotime('+1 month', $ts);
                            $nm = (int)date('m', $next_month);
                            $ny = (int)date('Y', $next_month);
                            // Handle months with fewer days
                            $candidate = sprintf('%04d-%02d-%02d', $ny, $nm, $dd);
                            if (!checkdate($nm, $dd, $ny)) {
                                // If date doesn't exist, use last day of month
                                $new_expiry = date('Y-m-t', $next_month);
                            } else {
                                $new_expiry = $candidate;
                            }
                        } else {
                            $new_expiry = date('Y-m-d', strtotime('+1 month', $ts));
                        }
                    } else {
                        $new_expiry = date('Y-m-d', strtotime('+1 month', $ts));
                    }
                    break;
                case 'Quarterly':
                    $new_expiry = date('Y-m-d', strtotime('+3 months', $ts));
                    break;
                case 'Turnover':
                case 'Once':
                case 'Yearly':
                default:
                    $new_expiry = date('Y-m-d', strtotime('+1 year',   $ts));
                    break;
            }
        }

        // ── Update expiry (keep payment_status=0 so next expiry triggers renewal again)
        $table_name = $is_account_work ? 'customer_packages' : 'service_packages';
        $update_data = [
            'payment_status' => 0,
            'purchase_date'  => $today,
            'expiry_date'    => $new_expiry,
            'updated_on'     => $datetime,
        ];
        
        // For Account Work Turnover type, update bill_amount
        if ($is_account_work && $pkg_type == 'Turnover') {
            $update_data['bill_amount'] = $bill;
        }
        
        $this->db->update($table_name, $update_data, ['id' => $package_id]);

        // ── For Monthly Account Work type, record monthly amount in accountancy other_fee ───────────────────────────────────────────
        if ($is_account_work && $pkg_type == 'Monthly') {
            // Get current month's first day (e.g., 2024-04-01)
            $current_month_start = date('Y-m-01');
            
            // Check if accountancy record exists for this month
            $acc_record = $this->db->get_where('accountancy', [
                'user_id' => $user['id'],
                'firm_id' => $firm_id,
                'date' => $current_month_start
            ])->unbuffered_row('array');
            
            if (!empty($acc_record)) {
                // Update existing record - add monthly amount to other_fee
                $existing_other_fee = (float)($acc_record['other_fee'] ?? 0);
                $new_other_fee = $existing_other_fee + $subtotal; // Use base rate (without GST)
                $this->db->update('accountancy', [
                    'other_fee' => $new_other_fee,
                    'updated_on' => $datetime
                ], [
                    'id' => $acc_record['id']
                ]);
            } else {
                // Create new accountancy record for this month
                $acc_data = [
                    'user_id' => $user['id'],
                    'firm_id' => $firm_id,
                    'year' => $year,
                    'date' => $current_month_start,
                    'turnover' => 0,
                    'other_fee' => $subtotal, // Use base rate (without GST)
                    'due_date' => date('Y-m-d', strtotime('+1 month', strtotime($current_month_start))), // Due date is next month
                    'added_by' => $user['id'],
                    'status' => 1,
                    'added_on' => $datetime,
                    'updated_on' => $datetime
                ];
                $this->service->saveturnover($acc_data);
            }
        }

        // ── Generate invoice ───────────────────────────────────────────
        $customer  = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        $firm_info = $this->customer->getfirms(['t1.id' => $firm_id], 'single');
        $gst_on    = !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        
        // Calculate GST - bill is base rate, GST is added on top
        $subtotal  = $bill; // Base rate (e.g., 5000)
        $gst_rate = $gst_on ? 18.0 : 0.0;
        $gst_amt   = $gst_on ? round(($bill * $gst_rate) / 100, 2) : 0; // 18% of base rate (e.g., 900)
        $total_amt = $subtotal + $gst_amt; // Total = base + GST (e.g., 5900)

        if ($is_account_work) {
            // For Monthly type, use generic name
            // For Turnover type, use package name
            if ($pkg_type == 'Monthly') {
                $service_name = 'Account Work Monthly';
            } else {
                $service_name = $pkg['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            }
            $svc_names = ['Account Work' . ($pkg_type == 'Monthly' ? ' Monthly' : ' (' . $service_name . ')')];
        } else {
            $svc_names = array_column($services, 'name');
        }
        
        $inv_no    = '';
        try {
            $inv_result = $this->invoice->create_custom_invoice([
                'user_id'        => $user['id'],
                'firm_id'        => $firm_id,
                'year'           => $year,
                'invoice_date'   => $today,
                'billing_name'   => !empty($customer['name'])   ? $customer['name']   : $user['name'],
                'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                'service_name'   => implode(', ', $svc_names) . ($is_account_work ? ' (Account Work Renewal)' : ' (Package Renewal)'),
                'type'           => $pkg_type,
                'period_value'   => $year,
                'subtotal'       => $subtotal,
                'gst_rate'       => $gst_rate,
                'gst_amount'     => $gst_amt,
                'total_amount'   => $total_amt,
            ]);
            if (!empty($inv_result['status']) && $inv_result['status'] === true) {
                $inv_no = $inv_result['invoice']['invoice_no'];
            }
        } catch (Exception $e) {
            log_message('error', 'Package manual renewal invoice error: ' . $e->getMessage());
        }

        $msg = 'Package renewed successfully! ₹' . number_format($bill, 2) . ' deducted. Next expiry: ' . date('d-m-Y', strtotime($new_expiry)) . '.';
        if ($inv_no) $msg .= ' Invoice: ' . $inv_no;

        echo json_encode(['status' => true, 'message' => $msg]);
    }

    public function monthlyservices($slug = NULL)
    {
        $where = "status='1' and slug ='$slug'";
        $service = $this->master->getservices($where, 'single');
        if (empty($service)) {
            redirect('services/purchasedservices/');
        }
        $data = ['title' => $service['name']];
        $data['breadcrumb'] = array('services/purchasedservices' => 'Purchased Services', "active" => $service['name']);
        $year = $this->session->year;
        $firm_id = $this->session->firm;
        $user = getuser();
        $data['user'] = $user;
        $years = getyearmonthvalues($year);
        $from = $years['year1'] . '-04-01';
        $to = $years['year2'] . '-03-31';
        $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id' and t1.year='$year' and t1.service_id='$service[id]'";
        $services = $this->service->getpurchasedservices($where);
        if (!empty($services)) {
            foreach ($services as $key => $service) {
                $name = "<span class=\"text-danger\">Pending</span>";
                if ($service['status'] == 0) {
                    $link = 'services/openform/' . $service['service_slug'] . '/' . $service['id'];
                } else {
                    $link = 'services/previewform/' . $service['service_slug'] . '/' . $service['id'];
                }
                if ($service['purchased_type'] == 'Yearly') {
                    $slug = date('Y', strtotime($service['date']));
                    redirect($link);
                } elseif ($service['purchased_type'] == 'Monthly') {
                    // First check period_value from purchase record
                    if (!empty($service['period_value'])) {
                        $period_meta = $this->normalizePeriodValue($service['period_value'], 'Monthly', $year);
                        if (!empty($period_meta['label'])) {
                            $name = $period_meta['label'];
                        }
                    }
                    // Fall back to formdata if period_value not available
                    if ($name == "<span class=\"text-danger\">Pending</span>") {
                        $documents = $this->service->getuploadeddocuments(['a.order_id' => $service['id']]);
                        $doc_names = !empty($documents) ? array_column($documents, 'display_name') : array();
                        if (!empty($doc_names)) {
                            $index = array_search('Month', $doc_names);
                            if ($index !== false) {
                                $name = $documents[$index]['formvalue'];
                            }
                        }
                    }
                } elseif ($service['purchased_type'] == 'Quarterly') {
                    // First check period_value from purchase record
                    if (!empty($service['period_value'])) {
                        $period_meta = $this->normalizePeriodValue($service['period_value'], 'Quarterly', $year);
                        if (!empty($period_meta['label'])) {
                            $name = $period_meta['label'];
                        }
                    }
                    // Fall back to formdata if period_value not available
                    if ($name == "<span class=\"text-danger\">Pending</span>") {
                        $documents = $this->service->getuploadeddocuments(['a.order_id' => $service['id']]);
                        $doc_names = !empty($documents) ? array_column($documents, 'display_name') : array();
                        if (!empty($doc_names)) {
                            $index = array_search('Quarter', $doc_names);
                            if ($index !== false) {
                                $name = $documents[$index]['formvalue'];
                            }
                        }
                    }
                } else {
                    $slug = date('F-Y', strtotime($service['date']));
                }
                $services[$key]['name'] = $name;
                $services[$key]['count'] = '';
                $services[$key]['link'] = $link;
            }
        }
        $data['services'] = $services;
        //print_pre($data,true);
        $data['datatable'] = true;
        //$data['styles']=array('file'=>'includes/custom/folder.css');
        //$data['folders']=$folders;
        //$this->template->load('pages','folder-view',$data);
        $this->template->load('services', 'monthlyservices', $data);
    }

    public function openform($slug = NULL, $order_id = NULL)
    {
        $where = "status='1' and slug ='$slug'";
        $service = $this->master->getservices($where, 'single');
        if (empty($service)) {
            redirect('services/purchasedservices/');
        }
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;

        $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
        if (!empty($kyc)) {
            $where = "t1.user_id='$user[id]' and t1.service_id='$service[id]' and t1.status=0";
            $purchasedservice = $this->service->getpurchasedservices($where, 'single');
            if (!empty($purchasedservice)) {
                $documents = $this->master->getservicedocuments(['t1.service_id' => $service['id']]);
                //print_pre($documents,true);
                $finaldocuments = array();
                if (!empty($documents)) {
                    foreach ($documents as $key => $field) {
                        $value = '';
                        $editable = true;
                        if ($field['document_id'] == 1) {
                            $value = $user['mobile'];
                            $editable = false;
                        } elseif ($field['document_id'] == 2) {
                            $value = $user['email'];
                            $editable = false;
                        } elseif ($field['document_id'] == 3) {
                            $value = $kyc['pan'];
                            $documents[$key]['file'] = 0;
                            $editable = false;
                        } elseif ($field['document_id'] == 4) {
                            $value = $kyc['aadhar'];
                            $documents[$key]['file'] = 0;
                            $editable = false;
                        } elseif ($field['document_id'] == 3 || $field['document_id'] == 4) {
                            unset($documents[$key]);
                            continue;
                        }
                        $documents[$key]['field_value'] = $value;
                        $documents[$key]['editable'] = $editable;
                        $finaldocuments[] = $documents[$key];
                    }
                    $type = !empty($purchasedservice['purchased_type']) ? $purchasedservice['purchased_type'] : '';
                    $data = ['title' => 'Service Form'];
                    $data['breadcrumb'] = array('services/purchasedservices' => 'Purchased Services', 'services/monthlyservices/' . $service['slug'] => $service['name'], "active" => "Service Form");
                    $data['user'] = $user;
                    $data['finaldocuments'] = $finaldocuments;
                    $where = "t1.user_id='$user[id]' and t1.id='$order_id' and t1.firm_id='$firm_id'";
                    $data['order'] = $this->service->getpurchasedservices($where, 'single');
                    if (empty($data['order'])) {
                        $message = "Service order not found for the selected firm. Please switch firm or try another order.";
                        $this->session->set_flashdata("err_msg", $message);
                        redirect('services/purchasedservices/');
                    }

                    // Get period_value (quarter/month) for pre-selecting dropdown
                    $data['selected_period'] = '';
                    if (!empty($data['order']['period_value'])) {
                        $period_meta = $this->normalizePeriodValue($data['order']['period_value'], $type, $year);
                        $data['selected_period'] = !empty($period_meta['id']) ? $period_meta['id'] : $data['order']['period_value'];
                    }

                    $data['firm'] = $this->customer->getfirms(array("t1.id" => $firm_id), "single");
                    $data['kyc'] = $kyc;
                    $this->template->load('services', 'serviceform', $data);
                } else {
                    $message = "Required Documents Not Added! Please Try Again Later!";
                    $this->session->set_flashdata("err_msg", $message);
                    redirect($_SERVER['HTTP_REFERER']);
                }
            } else {
                $message = "Service Not Purchased!";
                $this->session->set_flashdata("err_msg", $message);
                redirect($_SERVER['HTTP_REFERER']);
            }
        } else {
            $message = "KYC Not Uploaded! Please Upload KYC before Submitting this Form!";
            $this->session->set_flashdata("err_msg", $message);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function previewform($slug = NULL, $order_id = NULL)
    {
        $where = "status='1' and slug ='$slug'";
        $service = $this->master->getservices($where, 'single');
        if (empty($service)) {
            redirect('services/purchasedservices/');
        }
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;

        $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
        if (!empty($kyc)) {
            $where = "t1.user_id='$user[id]' and t1.id='$order_id' and t1.firm_id='$firm_id'";
            $order = $this->service->getpurchasedservices($where, 'single');
            if (!empty($order)) {
                $data = ['title' => 'Service Form Preview'];
                $data['breadcrumb'] = array('services/purchasedservices' => 'Purchased Services', 'services/monthlyservices/' . $service['slug'] => $service['name'], "active" => "Service Form Preview");
                $data['user'] = $user;
                $data['firm'] = $this->customer->getfirms(array("t1.id" => $firm_id), "single");
                $data['kyc'] = $kyc;
                $order['name'] = $user['name'];
                $data['order'] = $order;
                if ($order['status'] == 0) {
                    $message = "Form not Submitted!";
                    $this->session->set_flashdata("err_msg", $message);
                    redirect($_SERVER['HTTP_REFERER']);
                } else {
                    $documents = $this->service->getuploadeddocuments(['a.order_id' => $order['id']]);
                    if (!empty($documents)) {
                        foreach ($documents as $key => $value) {
                            if (strpos($value['formvalue'], '/assets/') === 0) {
                                $documents[$key]['formvalue'] = file_url($value['formvalue']);
                            }
                        }
                        $data['documents'] = $documents;
                        // Get assessment - check for status=1 (completed) first, then status=0 (pending) as fallback
                        $getassessment = $this->db->get_where('assessments', ['order_id' => $order['id'], 'status' => 1]);
                        $assessment = array();
                        if ($getassessment->num_rows() > 0) {
                            $assessment = $getassessment->unbuffered_row('array');
                        } else {
                            // Fallback to status=0 for backward compatibility
                            $getassessment = $this->db->get_where('assessments', ['order_id' => $order['id'], 'status' => 0]);
                            if ($getassessment->num_rows() > 0) {
                                $assessment = $getassessment->unbuffered_row('array');
                            }
                        }
                        $data['assessment'] = $assessment;
                        //print_pre($data,true);
                        $this->template->load('services', 'formpreview', $data);
                    } else {
                        $message = "Form not Submitted!";
                        $this->session->set_flashdata("err_msg", $message);
                        redirect($_SERVER['HTTP_REFERER']);
                    }
                }
            } else {
                $message = "Service Not Purchased!";
                $this->session->set_flashdata("err_msg", $message);
                redirect($_SERVER['HTTP_REFERER']);
            }
        } else {
            $message = "KYC Not Uploaded! Please Upload KYC before Submitting this Form!";
            $this->session->set_flashdata("err_msg", $message);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function saveformdata()
    {
        if ($this->input->post('saveformdata') !== NULL) {
            $data = $this->input->post();
            $formdata = $data['formdata'] ?? array();
            if (!empty($data['month'])) {
                $month = $data['month'];
            }
            //print_pre($data,true);
            $where = "status='1' and slug ='$data[slug]'";
            $service = $this->master->getservices($where, 'single');
            //print_pre($service,true);
            if (empty($service)) {
                $this->session->set_flashdata("err_msg", "Please Try Again!");
                redirect($_SERVER['HTTP_REFERER']);
            }
            $user = getuser();
            $year = $this->session->year;
            $firm_id = $this->session->firm;

            $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
            if (!empty($kyc)) {
                $where = "t1.user_id='$user[id]' and t1.id='$data[order_id]' and t1.firm_id='$firm_id'";
                $order = $this->service->getpurchasedservices($where, 'single');
                if (!empty($order)) {
                    $documents = $this->master->getservicedocuments(['t1.service_id' => $order['service_id']]);
                    if (!empty($documents)) {
                        $message = array();
                        $data = array();
                        $date = date('Y-m-d');
                        foreach ($documents as $document) {
                            $slug = $document['slug'];
                            $single = array();
                            if (
                                $document['value'] == 1 && isset($formdata[$slug]) && $document['document_id'] != 3 &&
                                $document['document_id'] != 4
                            ) {
                                $single[] = array(
                                    'date' => $date,
                                    'user_id' => $user['id'],
                                    'order_id' => $order['id'],
                                    'service_id' => $order['service_id'],
                                    'field' => $slug,
                                    'field_id' => $document['id'],
                                    'value' => $formdata[$slug]
                                );
                            }
                            if ($document['document_id'] == 3) {
                                $single[] = array(
                                    'date' => $date,
                                    'user_id' => $user['id'],
                                    'order_id' => $order['id'],
                                    'service_id' => $order['service_id'],
                                    'field' => $slug,
                                    'field_id' => $document['id'],
                                    'value' => $kyc['pan']
                                );
                            } elseif ($document['document_id'] == 4) {
                                $single[] = array(
                                    'date' => $date,
                                    'user_id' => $user['id'],
                                    'order_id' => $order['id'],
                                    'service_id' => $order['service_id'],
                                    'field' => $slug,
                                    'field_id' => $document['id'],
                                    'value' => $kyc['aadhar']
                                );
                            }
                            $newslug = '';
                            if ($document['file'] > 0) {
                                $upload_path = './assets/service/documents/';
                                // Ensure PDF, JPG, JPEG, PNG, CSV, XLSX are always allowed
                                $allowed_types = 'pdf|jpg|jpeg|png|csv|xlsx';
                                // If document has specific file types, merge them with required types
                                if (!empty($document['file_type'])) {
                                    $db_types = explode('|', $document['file_type']);
                                    $required_types = ['pdf', 'jpg', 'jpeg', 'png', 'csv', 'xlsx'];
                                    $merged_types = array_unique(array_merge($required_types, $db_types));
                                    $allowed_types = implode('|', $merged_types);
                                }
                                if ($document['file'] == 1) {
                                    $newslug = $slug . '-file';
                                    if (isset($_FILES[$newslug]['tmp_name'])) {
                                        $upload = upload_file($newslug, $upload_path, $allowed_types, $newslug);
                                        if ($upload['status'] === true) {
                                            $single[] = array(
                                                'date' => $date,
                                                'user_id' => $user['id'],
                                                'order_id' => $order['id'],

                                                'service_id' => $order['service_id'],
                                                'field' => $newslug,
                                                'field_id' => $document['id'],
                                                'value' => $upload['path']
                                            );
                                        } else {
                                            $message[] = $document['display_name'] . ' File.' . $upload['msg'];
                                        }
                                    } elseif ($document['document_id'] == 3) {
                                        $single[] = array(
                                            'date' => $date,
                                            'user_id' => $user['id'],
                                            'order_id' => $order['id'],
                                            'service_id' => $order['service_id'],
                                            'field' => $newslug,
                                            'field_id' => $document['id'],
                                            'value' => str_replace(file_url(), '', $kyc['pan_image'])
                                        );
                                    } elseif ($document['document_id'] == 4) {
                                        $single[] = array(
                                            'date' => $date,
                                            'user_id' => $user['id'],
                                            'order_id' => $order['id'],
                                            'service_id' => $order['service_id'],
                                            'field' => $newslug,
                                            'field_id' => $document['id'],
                                            'value' => str_replace(file_url(), '', $kyc['aadhar_image'])
                                        );
                                    } else {
                                        $message[] = $document['display_name'] . ' File';
                                    }
                                } elseif ($document['file'] == 2) {
                                    for ($i = 1; $i <= 2; $i++) {
                                        $newslug = $slug . '-file-' . $i;
                                        if (isset($_FILES[$newslug]['tmp_name'])) {
                                            $upload = upload_file($newslug, $upload_path, $allowed_types, $newslug);
                                            if ($upload['status'] === true) {
                                                $single[] = array(
                                                    'date' => $date,
                                                    'user_id' => $user['id'],
                                                    'order_id' => $order['id'],
                                                    'service_id' => $order['service_id'],
                                                    'field' => $newslug,
                                                    'field_id' => $document['id'],
                                                    'value' => $upload['path']
                                                );
                                            }
                                        } elseif ($document['document_id'] == 4) {
                                            $image = $i == 1 ? str_replace(file_url(), '', $kyc['aadhar_image']) : str_replace(file_url(), '', $kyc['aadhar_back']);
                                            $single[] = array(
                                                'date' => $date,
                                                'user_id' => $user['id'],
                                                'order_id' => $order['id'],
                                                'service_id' => $order['service_id'],
                                                'field' => $slug,
                                                'field_id' => $document['id'],
                                                'value' => $image
                                            );
                                        } else {
                                            $message[] = $document['display_name'] . ' Image ' . $i;
                                        }
                                    }
                                }
                            }
                            if (empty($single) && $document['value'] == 1) {
                                $message[] = $document['display_name'];
                            } else {
                                $data = array_merge($data, $single);
                            }
                        }
                        $documents[] = $formdata;
                        //print_pre($data,true);
                        if (!empty($data) && empty($message)) {
                            if (!empty($year)) {
                                $data[] = array(
                                    'date' => $date,
                                    'user_id' => $user['id'],
                                    'order_id' => $order['id'],
                                    'service_id' => $order['service_id'],
                                    'field' => $order['service_slug'] . '-year',
                                    'field_id' => 0,
                                    'value' => $year
                                );
                            }
                            if (!empty($month)) {
                                $data[] = array(
                                    'date' => $date,
                                    'user_id' => $user['id'],
                                    'order_id' => $order['id'],
                                    'service_id' => $order['service_id'],
                                    'field' => $order['service_slug'] . '-month',
                                    'field_id' => 0,
                                    'value' => $month
                                );
                            }
                            foreach ($data as $key => $value) {
                                $data[$key]['firm_id'] = $firm_id;
                            }
                            //print_pre($data,true);
                            $result = $this->service->saveformdata($data);
                            if ($result['status'] === true) {
                                $notifydata = array(
                                    "type" => "form",
                                    "user_id" => $user['id'],
                                    'order_id' => $order['id'],
                                    'message' => 'Documents submitted for "' . $order['service_name'] . '". We will review shortly.',
                                    'added_on' => date('Y-m-d H:i:s'),
                                    'updated_on' => date('Y-m-d H:i:s')
                                );
                                $this->common->savenotification($notifydata);
                                $this->session->set_flashdata("msg", "Formdata Saved Successfully!");
                                redirect('services/monthlyservices/' . $slug);
                            } else {
                                $this->session->set_flashdata("err_msg", $result['message']);
                            }
                        } else {
                            $message = implode(',', $message);
                            $message = "You have not provided " . $message;
                            /*$this->response([
                                'status' => false,
                                'message' => $message,
                                'documents' => $documents,'data'=>$data,'newslug'=>$newslug], RestController::HTTP_OK);*/
                            $this->session->set_flashdata("err_msg", $message);
                        }
                    } else {
                        $message = "Required Documents Not Added! Please Try Again Later!";
                        $this->session->set_flashdata("err_msg", $message);
                    }
                } else {
                    $message = "Service Not Purchased!";
                    $this->session->set_flashdata("err_msg", $message);
                }
            } else {
                $message = "KYC Not Uploaded! Please Upload KYC before Submitting this Form!";
                $this->session->set_flashdata("err_msg", $message);
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function buyservice()
    {
        $url = '';
        $user = getuser();
        $firm_id = $this->session->firm;
        $year = $this->session->year;
        $service_id = $this->input->post('id');
        $package_id = $this->input->post('package_id');
        $type = $this->input->post('type');
        $payment_method = $this->input->post('payment_method');
        $amount = $this->input->post('amount');
        $service_option = $this->input->post('service_option'); // Generic parameter for all services with options
        $period_value = $this->input->post('period_value'); // Period value for Monthly/Quarterly/Yearly
        $month_val = $this->input->post('month'); // Selected month (end month) for Monthly Account Work
        $start_month_val = $this->input->post('start_month'); // Selected start month for Monthly Account Work
        $where = array('t1.id' => $firm_id, "t1.user_id" => $user['id']);
        $firm = $this->customer->getfirms($where, 'single');
        if (!empty($firm)) {
            $firm_id = $firm['id'];
            if (!$this->hasFirmKycForPurchase($user['id'], $firm_id)) {
                $this->session->set_flashdata("err_msg", "Approved firm-wise KYC is mandatory before purchase. Please complete KYC and wait for admin approval.");
                return false;
            }
            $where = "status='1' and id ='$service_id'";
            $service = $this->master->getservices($where, 'single');
            if (!empty($service)) {
                $service_for = $service['service_for'];
                $types = explode(',', $service['type']);
                $status = true;
                $message = "";

                // Check if this service has dynamic options (generic for all services)
                $has_service_options = false;
                $service_options_pricing = array();
                $service_options_display_names = array();
                $selected_option_display = '';

                // Check if this service has dynamic options
                $service_options = $this->master->getserviceoptionspricing($service_id);
                if (!empty($service_options['pricing']) && !empty($service_option)) {
                    $service_options_pricing = $service_options['pricing'];
                    $service_options_display_names = $service_options['display_names'];

                    // Validate selected option exists
                    if (in_array($service_option, array_keys($service_options_pricing))) {
                        $has_service_options = true;
                        // Get pricing for selected option
                        $amount = $service_options_pricing[$service_option];
                        // Get display name for selected option
                        $selected_option_display = isset($service_options_display_names[$service_option]) ?
                            $service_options_display_names[$service_option] :
                            ucfirst(str_replace('-', ' ', $service_option));
                        // Update service name to include the option
                        $service['name'] = trim($service['name']) . ' - ' . $selected_option_display;
                        // For services with options, default to Yearly type if not specified
                        if (empty($type) || !in_array($type, $types)) {
                            $type = in_array('Yearly', $types) ? 'Yearly' : (count($types) > 0 ? $types[0] : 'Yearly');
                        }
                    } else {
                        $status = false;
                        $message = "Invalid option selected for " . $service['name'];
                    }
                }

                // Check if service is already included in ANY of the user's service packages (skip for service_id=1 / accountancy)
                if ($service_id != 1) {
                    $all_user_packages = $this->customer->getservicepackage(
                        ['t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year],
                        'all'
                    );
                    if (!empty($all_user_packages)) {
                        foreach ($all_user_packages as $_pkg) {
                            if (!empty($_pkg['service_ids'])) {
                                $pkg_ids = array_filter(array_map('trim', explode(',', $_pkg['service_ids'])));
                                if (in_array((string)$service_id, $pkg_ids)) {
                                    $ptype = !empty($_pkg['package_type']) ? $_pkg['package_type'] : '';
                                    $this->session->set_flashdata(
                                        "err_msg",
                                        $service['name'] . " is already included in your " .
                                            ($ptype ? $ptype . ' ' : '') .
                                            "service package. Please manage it from the <a href=\"" .
                                            base_url('package/') . "\">Package page</a>."
                                    );
                                    redirect($_SERVER['HTTP_REFERER']);
                                    return;
                                }
                            }
                        }
                    }
                }

                if ($service_id == 1) {
                    $autodebit = 1;
                    $status = true;
                    $message = "";
                    
                    // For Monthly type, package_id is not required
                    if ($type == "Monthly") {
                        if (empty($amount)) {
                            $status = false;
                            $message = "Enter Monthly Debit Amount!";
                        } else {
                            // Check if user already has an Account Work package for this firm/year
                            $where = array('user_id' => $user['id'], 'status' => 1, 'firm_id' => $firm_id, 'year' => $year);
                            $query = $this->db->get_where('customer_packages', $where);
                            if ($query->num_rows() > 0) {
                                $cpackage = $query->unbuffered_row('array');
                                $pkg_type_existing = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';
                                $status = false;
                                $message = "You have already selected Account Work package (" . $pkg_type_existing . " type)!";
                            } else {
                                $message = "Account Work Monthly package selected successfully!";
                                // For Monthly type, set a default package_id (can be 1 or null)
                                // Since package selection is not shown, use default value
                                $package_id = 1; // Default to Accountancy Prime
                            }
                        }
                    } else {
                        // For Turnover type, package_id is required
                        if (empty($package_id)) {
                            $status = false;
                            $message = "Select Package to Activate " . $service['name'];
                        } else {
                            $package_id = $package_id == 'accountancy-prime' ? 1 : 2;
                            $name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                            $package = $this->master->getpackages(['name' => $name]);
                            $where = array('user_id' => $user['id'], 'status' => 1, 'firm_id' => $firm_id, 'year' => $year);
                            $query = $this->db->get_where('customer_packages', $where);
                            $status = true;
                            $message = $name . " Selected Successfully!";
                            if ($query->num_rows() > 0) {
                                $cpackage = $query->unbuffered_row('array');
                                $p = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                                $status = false;
                                $message = "You have already selected " . $p . "!";
                            }
                        }
                    }
                    if ($status) {
                        $datetime = date('Y-m-d H:i:s');
                        $purchase_date = date('Y-m-d');
                        if ($type == "Monthly" && !empty($month_val)) {
                            $years = getyearmonthvalues($year);
                            $month_int = (int)$month_val;
                            $actual_year = ($month_int >= 4) ? $years['year1'] : $years['year2'];
                            $purchase_date = sprintf('%04d-%02d-01', $actual_year, $month_int);
                        }
                        
                        // Calculate expiry date based on type
                        $expiry_date = null;
                        $package_type = $type; // Turnover, Monthly, etc.
                        $bill_amount = 0;
                        
                        if ($type == "Monthly") {
                            // Monthly: expiry = next month's auto debit date (28th of next month)
                            // Always use next month's debit date, regardless of purchase date
                            // Get Account Work service to get debit_date
                            $debit_date = !empty($service['debit_date']) ? $service['debit_date'] : null;
                            if ($debit_date) {
                                $dd = (int)date('d', strtotime($debit_date)); // Day from debit_date (e.g., 28)
                                // Always use next month's debit date
                                $next_month = strtotime('+1 month', strtotime($purchase_date));
                                $nm = (int)date('m', $next_month);
                                $ny = (int)date('Y', $next_month);
                                // Handle months with fewer days (e.g., Feb 28 -> Mar 28, not Feb 31)
                                $expiry_date = sprintf('%04d-%02d-%02d', $ny, $nm, $dd);
                                // Validate date exists (e.g., Feb 30 doesn't exist)
                                if (!checkdate($nm, $dd, $ny)) {
                                    // If date doesn't exist, use last day of month
                                    $expiry_date = date('Y-m-t', $next_month);
                                }
                            } else {
                                // Fallback: if no debit_date, use purchase_date + 1 month
                                $expiry_date = date('Y-m-d', strtotime('+1 month', strtotime($purchase_date)));
                            }
                            // For Monthly type, calculate amount based on range from start_month to month_val
                            $bill_amount = (float)$amount;
                            if (!empty($month_val)) {
                                $end_m = (int)$month_val;
                                $start_m = !empty($start_month_val) ? (int)$start_month_val : 4;
                                
                                $start_idx = ($start_m >= 4) ? ($start_m - 3) : ($start_m + 9);
                                $end_idx   = ($end_m >= 4)   ? ($end_m - 3)   : ($end_m + 9);
                                
                                if ($start_idx > $end_idx) {
                                    $start_idx = 1;
                                }
                                $multiplier = ($end_idx - $start_idx) + 1;
                                $bill_amount = $bill_amount * $multiplier;
                            }
                        } else {
                            // Turnover/Yearly: expiry = next year's debit_date or +1 year
                            $debit_date = !empty($service['debit_date']) ? $service['debit_date'] : null;
                            if ($debit_date) {
                                $dm = (int)date('m', strtotime($debit_date));
                                $dd = (int)date('d', strtotime($debit_date));
                                $cy = (int)date('Y', strtotime($purchase_date));
                                
                                $candidate = sprintf('%04d-%02d-%02d', $cy, $dm, $dd);
                                if (strtotime($candidate) <= strtotime($purchase_date)) {
                                    $candidate = sprintf('%04d-%02d-%02d', $cy + 1, $dm, $dd);
                                }
                                $expiry_date = $candidate;
                            } else {
                                $expiry_date = date('Y-m-d', strtotime('+1 year', strtotime($purchase_date)));
                            }
                            $package_type = 'Turnover';
                            // For turnover-based, bill_amount will be calculated on renewal based on actual turnover
                            // Set to 0 initially, will be calculated when expired
                            $bill_amount = 0;
                        }
                        
                        // Note: GST is NOT calculated at purchase time
                        // GST will be calculated only when creating purchase record at renewal/payment time
                        
                        $data = array(
                            'user_id' => $user['id'],
                            'firm_id' => $firm_id,
                            'year' => $year,
                            'status' => 1,
                            'expiry_date' => $expiry_date,
                            'payment_status' => 0,
                            'purchase_date' => $purchase_date,
                            'bill_amount' => $bill_amount,
                            'package_type' => $package_type,
                            'added_on' => $datetime,
                            'updated_on' => $datetime
                        );

                        // For Monthly type, don't store package_id (set to 0)
                        // For Turnover type, store the selected package_id
                        if ($type == "Monthly") {
                            $data['amount'] = $amount;
                            $data['package_id'] = 0; // No package selection for Monthly type
                        } else {
                            $data['package_id'] = $package_id; // Store package_id for Turnover type
                        }
                        if (!empty($autodebit) && $autodebit != 0) {
                            $data['autodebit'] = 1;
                        }
                        $result = $this->db->insert("customer_packages", $data);
                        if ($result) {
                            // Ensure accountancy records exist for historical + current months of the financial year
                            ensure_accountancy_fy_months($user['id'], $firm_id, $year, $data['package_id']);

                            if ($type == "Monthly") {
                                // --- NEW FEATURE: BACKFILL PENDING MONTHS ---
                                if (!empty($month_val)) {
                                    $years = getyearmonthvalues($year);
                                    $year1 = (int)$years['year1'];
                                    
                                    $end_m = (int)$month_val;
                                    $start_m = !empty($start_month_val) ? (int)$start_month_val : 4;
                                    
                                    $start_idx = ($start_m >= 4) ? ($start_m - 3) : ($start_m + 9);
                                    $end_idx   = ($end_m >= 4)   ? ($end_m - 3)   : ($end_m + 9);
                                    
                                    if ($start_idx > $end_idx) {
                                        $start_idx = 1;
                                    }
                                    
                                    $months_to_create = [];
                                    for ($i = $start_idx; $i < $end_idx; $i++) {
                                        if ($i <= 9) {
                                            $iter_m = $i + 3;
                                            $iter_y = $year1;
                                        } else {
                                            $iter_m = $i - 9;
                                            $iter_y = $year1 + 1;
                                        }
                                        $months_to_create[] = ['m' => $iter_m, 'y' => $iter_y];
                                    }
                                    
                                    foreach ($months_to_create as $mi) {
                                        $iter_m = $mi['m'];
                                        $iter_y = $mi['y'];
                                        
                                        $iter_purchase_date = sprintf('%04d-%02d-01', $iter_y, $iter_m);
                                        
                                        // Calculate expiry date (28th of next month)
                                        $next_m = $iter_m + 1;
                                        $next_y = $iter_y;
                                        if ($next_m > 12) {
                                            $next_m = 1;
                                            $next_y++;
                                        }
                                        $debit_date = !empty($service['debit_date']) ? $service['debit_date'] : null;
                                        if ($debit_date) {
                                            $dd = (int)date('d', strtotime($debit_date));
                                            $iter_expiry_date = sprintf('%04d-%02d-%02d', $next_y, $next_m, $dd);
                                            if (!checkdate($next_m, $dd, $next_y)) {
                                                $iter_expiry_date = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $next_y, $next_m)));
                                            }
                                        } else {
                                            $iter_expiry_date = date('Y-m-d', strtotime('+1 month', strtotime($iter_purchase_date)));
                                        }
                                        
                                        $iter_bill_amount = (float)$amount; // Single month amount
                                        
                                        // Check for duplicate
                                        $check_sql = "SELECT id FROM " . $this->db->dbprefix('customer_packages') . " 
                                                      WHERE user_id = {$user['id']} AND firm_id = {$firm_id} AND year = '{$year}' 
                                                      AND package_type = 'Monthly' AND MONTH(purchase_date) = {$iter_m} AND YEAR(purchase_date) = {$iter_y}";
                                        $exists = $this->db->query($check_sql)->num_rows() > 0;
                                        
                                        if (!$exists) {
                                            $iter_data = array(
                                                'user_id' => $user['id'],
                                                'firm_id' => $firm_id,
                                                'year' => $year,
                                                'status' => 1,
                                                'expiry_date' => $iter_expiry_date,
                                                'payment_status' => 0, // Unpaid
                                                'purchase_date' => $iter_purchase_date,
                                                'bill_amount' => $iter_bill_amount,
                                                'package_type' => 'Monthly',
                                                'added_on' => $datetime,
                                                'updated_on' => $datetime,
                                                'amount' => $amount,
                                                'package_id' => 0
                                            );
                                            if (!empty($autodebit) && $autodebit != 0) {
                                                $iter_data['autodebit'] = 1;
                                            }
                                            $this->db->insert("customer_packages", $iter_data);
                                        }
                                    }
                                }
                                // --------------------------------------------
                                
                                // Immediately process pending auto debits using Wallet / Credit Limit
                                $this->load->library('auto_debit_service');
                                $this->auto_debit_service->process_user_pending_auto_debits($user['id']);

                                $this->session->set_flashdata("msg", "Account Work Monthly package selected successfully! Package expires on " . date('d-m-Y', strtotime($expiry_date)) . ". Amount ₹" . number_format($amount, 2) . " will be auto-debited monthly.");
                            } else {
                                $name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                                $this->load->library('auto_debit_service');
                                $this->auto_debit_service->process_user_pending_auto_debits($user['id']);
                                $this->session->set_flashdata("msg", $name . " Selected Successfully! Package expires on " . date('d-m-Y', strtotime($expiry_date)) . ".");
                            }
                        } else {
                            $error = $this->db->error();
                            $this->session->set_flashdata("err_msg", $error['message']);
                        }
                    } else {
                        $this->session->set_flashdata("err_msg", $message);
                    }
                    return false;
                } elseif (!$has_service_options && !in_array($type, $types)) {
                    $status = false;
                    $message = $type . " option not available for " . $service['name'];
                } elseif ($has_service_options && !empty($service_option)) {
                    // Handle services with dynamic options - check for duplicate purchase of same option
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    // If period_value is provided, also check for it
                    if (!empty($period_value)) {
                        $where2 .= " and t1.period_value='$period_value'";
                    }
                    $purchases = $this->service->getpurchases($where2);
                    if (!empty($purchases)) {
                        // Check if this specific option was already purchased
                        foreach ($purchases as $purchase) {
                            // First check service_option column (most reliable)
                            if (!empty($purchase['service_option']) && $purchase['service_option'] == $service_option) {
                                $status = false;
                                $years = getyearmonthvalues($year);
                                $period_msg = '';
                                if (!empty($period_value)) {
                                    $period_info = getyearmonthvalues($period_value);
                                    $period_msg = ' for ' . $period_info['value'];
                                } else {
                                    $period_msg = ' for ' . $years['value'];
                                }
                                $message = "You have already Purchased " . $service['name'] . " (" . $selected_option_display . ")" . $period_msg . "!";
                                break;
                            }
                            // Fallback: check service name for the option
                            $search_keyword = strtolower($selected_option_display);
                            $purchase_service_name = strtolower(!empty($purchase['service']) ? $purchase['service'] : (!empty($purchase['service_name']) ? $purchase['service_name'] : ''));
                            if (strpos($purchase_service_name, $search_keyword) !== false) {
                                $status = false;
                                $years = getyearmonthvalues($year);
                                $period_msg = '';
                                if (!empty($period_value)) {
                                    $period_info = getyearmonthvalues($period_value);
                                    $period_msg = ' for ' . $period_info['value'];
                                } else {
                                    $period_msg = ' for ' . $years['value'];
                                }
                                $message = "You have already Purchased " . $service['name'] . " (" . $selected_option_display . ")" . $period_msg . "!";
                                break;
                            }
                        }
                    }
                } elseif ($service['type'] == 'Once') {
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    $purchases = $this->service->getpurchases($where2);
                    if (!empty($purchases)) {
                        $status = false;
                        $message = "You have already Purchased " . $service['name'] . "!";
                    }
                } elseif ($types[0] == 'Yearly' && count($types) == 1) {
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    // If period_value is provided, also check for it
                    if (!empty($period_value)) {
                        $where2 .= " and t1.period_value='$period_value'";
                    }
                    // For services with options, duplicate check already handled above
                    if (!$has_service_options) {
                        $purchases = $this->service->getpurchases($where2);
                        if (!empty($purchases)) {
                            $status = false;
                            if (!empty($period_value)) {
                                $period_info = getyearmonthvalues($period_value);
                                $message = "You have already Purchased " . $service['name'] . " for " . $period_info['value'] . "!";
                            } else {
                                $years = getyearmonthvalues($year);
                                $message = "You have already Purchased " . $service['name'] . " for " . $years['value'] . "!";
                            }
                        }
                    }
                } elseif ($types[0] == 'Yearly' && count($types) > 1) {
                    /*$where2="t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year'";
                    if($service_for=='Firm'){
                        $where2.=" and t1.firm_id='$firm_id'";
                    }
                    $purchases=$this->service->getpurchases($where2);
                    if(!empty($purchases)){
                        $status=false;
                        $message="You have already Purchased this Service!";
                    }*/
                } elseif ($type == 'Monthly') {
                    // Check annual limit: Maximum 12 monthly purchases per year
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year' and t1.type='Monthly'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    $purchases = $this->service->getpurchases($where2);

                    // Check if annual limit (12 months) is reached
                    if (!empty($purchases) && count($purchases) >= 12) {
                        $status = false;
                        $years = getyearmonthvalues($year);
                        $message = "You have reached the annual limit! You can purchase monthly services maximum 12 times per year for " . $years['value'] . "!";
                    } elseif (!empty($period_value)) {
                        // Check for duplicate purchase of specific month
                        $where3 = $where2 . " and t1.period_value='$period_value'";
                        $existing = $this->service->getpurchases($where3);
                        if (!empty($existing)) {
                            $status = false;
                            $period_info = getyearmonthvalues($period_value);
                            $message = "You have already Purchased " . $service['name'] . " for " . $period_info['value'] . "!";
                        }
                    }
                } elseif ($type == 'Quarterly') {
                    // Check annual limit: Maximum 4 quarterly purchases per year
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year' and t1.type='Quarterly'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    $purchases = $this->service->getpurchases($where2);

                    // Check if annual limit (4 quarters) is reached
                    if (!empty($purchases) && count($purchases) >= 4) {
                        $status = false;
                        $years = getyearmonthvalues($year);
                        $message = "You have reached the annual limit! You can purchase quarterly services maximum 4 times per year for " . $years['value'] . "!";
                    } elseif (!empty($period_value)) {
                        // Check for duplicate purchase of specific quarter
                        $where3 = $where2 . " and t1.period_value='$period_value'";
                        $existing = $this->service->getpurchases($where3);
                        if (!empty($existing)) {
                            $status = false;
                            $period_info = getyearmonthvalues($period_value);
                            $message = "You have already Purchased " . $service['name'] . " for " . $period_info['value'] . "!";
                        }
                    }
                } elseif ($type == 'Yearly') {
                    // Check annual limit: Maximum 1 yearly purchase per year
                    $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year' and t1.type='Yearly'";
                    // Always check firm_id if provided (purchases always have firm_id)
                    if (!empty($firm_id)) {
                        $where2 .= " and t1.firm_id='$firm_id'";
                    }
                    $purchases = $this->service->getpurchases($where2);

                    // Check if annual limit (1 yearly) is reached
                    if (!empty($purchases) && count($purchases) >= 1) {
                        $status = false;
                        $years = getyearmonthvalues($year);
                        $message = "You have reached the annual limit! You can purchase yearly services maximum 1 time per year for " . $years['value'] . "!";
                    } elseif (!empty($period_value)) {
                        // Check for duplicate purchase of specific year period
                        $where3 = $where2 . " and t1.period_value='$period_value'";
                        $existing = $this->service->getpurchases($where3);
                        if (!empty($existing)) {
                            $status = false;
                            $period_info = getyearmonthvalues($period_value);
                            $message = "You have already Purchased " . $service['name'] . " for " . $period_info['value'] . "!";
                        }
                    }
                }
                if ($status) {
                    // Use custom amount for services with options, otherwise use service rate
                    if ($has_service_options && !empty($amount)) {
                        $subtotal = floatval($amount);
                        $service['rate'] = $subtotal; // Update rate for display
                    } else {
                        $service['rate'] = $service['rate'];
                        $subtotal = $service['rate'];
                    }

                    if (!$has_service_options && !empty($types) && count($types) > 1) {
                        if ($type == 'Monthly') {
                            $subtotal = $service['rate'];
                        } elseif ($type == 'Quarterly') {
                            if (in_array("Monthly", $types)) {
                                $subtotal = $service['rate'] * 3;
                            } else {
                                $subtotal = $service['rate'];
                            }
                        } elseif ($type == 'Yearly') {
                            if (in_array("Monthly", $types) && !in_array("Quarterly", $types)) {
                                $subtotal = $service['rate'] * 12;
                            } elseif (!in_array("Monthly", $types) && in_array("Quarterly", $types)) {
                                $subtotal = $service['rate'] * 4;
                            } else {
                                $subtotal = $service['rate'];
                            }
                        }
                    }

                    // Check if GST is enabled for this customer
                    $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                    $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                    $gst_amount = 0;
                    $total = $subtotal;

                    if ($gst_enabled) {
                        // Calculate 18% GST
                        $gst_amount = round(($subtotal * 18) / 100, 2);
                        $total = $subtotal + $gst_amount;
                    }

                    $where = array('user_id' => $user['id'], 'status' => 1);
                    $single = array(
                        'date' => date('Y-m-d'),
                        'year' => $year,
                        'type' => $type,
                        'user_id' => $user['id'],
                        'service_id' => $service['id'],
                        'firm_id' => $firm['id'],
                        'service' => $service['name'],
                        'rate' => $service['rate'],
                        'subtotal' => $subtotal,
                        'gst_amount' => $gst_amount,
                        'gst_enabled' => $gst_enabled ? 1 : 0,
                        'amount' => $total
                    );

                    // Store period value for Monthly/Quarterly/Yearly purchases
                    if (!empty($period_value) && ($type == 'Monthly' || $type == 'Quarterly' || $type == 'Yearly')) {
                        $single['period_value'] = $period_value;
                    }

                    // Store service option if applicable (generic for all services with options)
                    if ($has_service_options && !empty($service_option)) {
                        $single['service_option'] = $service_option;
                        $single['service_option_display'] = $selected_option_display;
                    }
                    $is_credit_limit = ($payment_method === 'Credit Limit');
                    
                    if ($is_credit_limit) {
                        $single['type'] = 'Credit limit'; // Save in DB as Credit limit for Wallet_model and auto-debit
                        $balance = $this->wallet->get_available_credit($user['id']);
                    } else {
                        $balance = $this->wallet->getwalletbalance($user['id']);
                    }

                    if ($balance >= $total) {
                        $data = array($single);
                        $data['name'] = $user['name'];
                        //print_pre($data,true);
                        $result = $this->service->purchaseservices($data);
                        //print_pre($result);
                        if ($result['status'] == true) {
                            // Create invoice for this purchase (parent order)
                            if (!empty($result['order_id'])) {
                                $order = $this->service->getpurchases(['t1.id' => $result['order_id']], 'single');
                                if (!empty($order)) {
                                    $invoice_result = $this->invoice->create_for_order($order);
                                    if ($invoice_result['status'] === true) {
                                        if (strpos($invoice_result['message'], 'already exists') === false) {
                                            $this->common->savenotification(array(
                                                'user_id' => (int) $user['id'],
                                                'type' => 'invoice',
                                                'order_id' => (int) $result['order_id'],
                                                'message' => 'Invoice ' . $invoice_result['invoice']['invoice_no'] . ' generated for your order.',
                                            ));
                                        }
                                        $this->session->set_flashdata("msg", $result['message'] . ' Invoice No: ' . $invoice_result['invoice']['invoice_no']);
                                    } else {
                                        // If invoice fails, still keep purchase but log error
                                        log_message('error', 'Invoice creation failed for order ' . $result['order_id'] . ': ' . $invoice_result['message']);
                                        $this->session->set_flashdata("msg", $result['message']);
                                    }
                                } else {
                                    $this->session->set_flashdata("msg", $result['message']);
                                }
                            } else {
                                $this->session->set_flashdata("msg", $result['message']);
                            }
                        } else {
                            $this->session->set_flashdata("err_msg", $result['message']);
                        }
                    } else {
                        if ($is_credit_limit) {
                            $this->session->set_flashdata("err_msg", "Insufficient Credit Limit. You need ₹" . number_format($total - $balance, 2) . " more.");
                            $url = '';
                        } else {
                            $remaining = $total - $balance;
                            $this->session->set_userdata('tobuy', true);
                            $this->session->set_flashdata("remaining", $remaining);
                            $url = base_url('mywallet/');
                        }
                    }
                } else {
                    $this->session->set_flashdata("err_msg", $message);
                }
            } else {
                $this->session->set_flashdata("err_msg", "No Service Selected!");
            }
        } else {
            $this->session->set_flashdata("err_msg", "Firm not Selected!");
        }
        echo $url;
    }
}
