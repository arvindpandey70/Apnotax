<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Package extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        logrequest();
        checklogin();
        if ($this->session->role != 'customer') {
            redirect('/');
        }
        $this->load->model('Invoice_model', 'invoice');
        $this->load->model('Account_model', 'account');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Detect the primary package type from an array of service rows.
     * All services in one package must share the same primary type.
     * Priority: Yearly > Quarterly > Monthly > Once > Turnover
     */
    private function detectPackageType(array $services)
    {
        foreach ($services as $svc) {
            $types = array_map('trim', explode(',', $svc['type']));
            if (in_array('Yearly', $types))    return 'Yearly';
            if (in_array('Quarterly', $types)) return 'Quarterly';
            if (in_array('Monthly', $types))   return 'Monthly';
            if (in_array('Once', $types))      return 'Once';
        }
        return 'Yearly';
    }

    /**
     * Calculate expiry date for a package.
     *
     * Monthly  → purchase_date + 1 month
     * Quarterly → purchase_date + 3 months
     * Once     → purchase_date + 1 year
     * Yearly   → next occurrence of the service's debit_date
     *             (earliest across all services; defaults to +1 year)
     */
    private function calculateExpiryDate($package_type, array $services, $purchase_date)
    {
        $pts = strtotime($purchase_date);

        switch ($package_type) {
            case 'Monthly':
                return date('Y-m-d', strtotime('+1 month', $pts));

            case 'Quarterly':
                return date('Y-m-d', strtotime('+3 months', $pts));

            case 'Once':
                return date('Y-m-d', strtotime('+1 year', $pts));

            case 'Yearly':
            default:
                $earliest = null;
                foreach ($services as $svc) {
                    if (empty($svc['debit_date'])) continue;
                    $dm = (int)date('m', strtotime($svc['debit_date']));
                    $dd = (int)date('d', strtotime($svc['debit_date']));
                    $cy = (int)date('Y', $pts);

                    // Build candidate date in current year
                    $candidate = sprintf('%04d-%02d-%02d', $cy, $dm, $dd);
                    if (strtotime($candidate) <= $pts) {
                        // Already passed this year → use next year
                        $candidate = sprintf('%04d-%02d-%02d', $cy + 1, $dm, $dd);
                    }
                    if ($earliest === null || strtotime($candidate) < strtotime($earliest)) {
                        $earliest = $candidate;
                    }
                }
                return $earliest !== null ? $earliest : date('Y-m-d', strtotime('+1 year', $pts));
        }
    }

    /**
     * Firm-wise KYC gate for package creation.
     */
    private function hasFirmKycForPurchase($user_id, $firm_id)
    {
        $kyc = $this->account->getkyc(['t1.user_id' => $user_id, 't1.firm_id' => $firm_id], 'single');
        if (empty($kyc)) {
            return false;
        }
        return !empty($kyc) && isset($kyc['status']) && (int)$kyc['status'] === 1;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $data = ['title' => 'My Package'];
        $data['breadcrumb'] = array("active" => "My Package");
        $data['alertify'] = true;

        $user    = getuser();
        $year    = $this->session->year;
        $firm_id = $this->session->firm;

        // Old-style accountancy package (service_id=1)
        $acctpkg = $this->db->get_where('customer_packages', ['user_id' => $user['id'], 'firm_id' => $firm_id, 'year' => $year, 'status' => 1]);
        $data['package'] = ($acctpkg->num_rows() > 0) ? $acctpkg->unbuffered_row('array') : null;
        $data['has_account_work_package'] = !empty($data['package']);

        // Calculate bill_amount for expired Account Work packages if needed
        if (!empty($data['package'])) {
            $pkg = $data['package'];
            $expiry_ts = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
            $is_expired = $expiry_ts && $expiry_ts <= time();
            $is_unpaid = empty($pkg['payment_status']) || $pkg['payment_status'] == 0;
            $pkg_type = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Turnover';

            // If expired and bill_amount is 0, use the service rate (always show ₹5,000)
            if ($is_expired && $is_unpaid && (empty($pkg['bill_amount']) || $pkg['bill_amount'] == 0)) {
                // Always use the base service rate from services table
                $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                $data['package']['bill_amount'] = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
            }
        }

        // Pass user and firm_id to view
        $data['user'] = $user;
        $data['firm_id'] = $firm_id;

        // Accountancy master packages (Prime / Premium) for the selection UI
        $data['accountancy_packages'] = $this->master->getpackages();

        // ALL service packages for this user/firm/year
        $where_pkg = array('t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year);
        $data['service_packages'] = $this->customer->getservicepackage($where_pkg, 'all');

        // Backward-compat single
        $data['service_package'] = !empty($data['service_packages']) ? $data['service_packages'][0] : null;

        // All services (exclude id=1 / account-work) with options
        $all_services = $this->master->getservices(['status' => 1, 'id>' => 1]);
        $services_with_options = array();
        if (!empty($all_services)) {
            foreach ($all_services as $svc) {
                $opts = $this->master->getserviceoptions(['service_id' => $svc['id'], 'status' => 1], 'all');
                $services_with_options[$svc['id']] = ['service' => $svc, 'options' => $opts];
            }
        }
        $data['all_services']          = $all_services;
        $data['services_with_options'] = $services_with_options;

        // Collect all service_ids already in ANY active package for this firm/year
        $data['packaged_service_ids'] = array();
        if (!empty($data['service_packages'])) {
            foreach ($data['service_packages'] as $pkg) {
                if (!empty($pkg['service_ids'])) {
                    $ids = array_filter(array_map('trim', explode(',', $pkg['service_ids'])));
                    $data['packaged_service_ids'] = array_unique(
                        array_merge($data['packaged_service_ids'], $ids)
                    );
                }
            }
        }

        // Collect service_ids already purchased individually (direct purchase from /services/)
        $data['purchased_service_ids'] = array();
        if (!empty($user['id']) && !empty($firm_id) && !empty($year)) {
            $direct_purchases = $this->service->getpurchases(
                "t1.user_id='{$user['id']}' AND t1.firm_id='{$firm_id}' AND t1.year='{$year}' AND t1.service_id != 1"
            );
            if (!empty($direct_purchases)) {
                foreach ($direct_purchases as $dp) {
                    if (!empty($dp['service_id'])) {
                        $data['purchased_service_ids'][] = (string)$dp['service_id'];
                    }
                }
                $data['purchased_service_ids'] = array_unique($data['purchased_service_ids']);
            }
        }

        $this->template->load('package', 'mypackage', $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SAVE PACKAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function savepackage()
    {
        if ($this->input->post('savepackage') === NULL) {
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        $post    = $this->input->post();
        $user    = getuser();
        $firm_id = $this->session->firm;
        $year    = $this->session->year;

        // Prerequisite: Account Work package must be active before creating service packages
        $account_work_pkg = $this->db->get_where('customer_packages', [
            'user_id' => $user['id'],
            'firm_id' => $firm_id,
            'year'    => $year,
            'status'  => 1
        ])->unbuffered_row('array');
        if (empty($account_work_pkg)) {
            $this->session->set_flashdata('err_msg', 'Please activate Account Work package first. Then you can create Service Package.');
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // Validate firm
        $firm = $this->customer->getfirms(
            array("t1.user_id" => $user['id'], 't1.id' => $firm_id, 't1.status' => 1),
            'single'
        );
        if (empty($firm)) {
            $this->session->set_flashdata('err_msg', 'Firm not selected!');
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        if (!$this->hasFirmKycForPurchase($user['id'], $firm_id)) {
            $this->session->set_flashdata('err_msg', 'Approved firm-wise KYC is mandatory before buying package. Please complete KYC and wait for admin approval.');
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // Service IDs selected via checkboxes
        $service_id = isset($post['service_id']) ? (array)$post['service_id'] : array();
        $service_id = array_filter(array_map('intval', $service_id));
        if (empty($service_id)) {
            $this->session->set_flashdata('err_msg', 'Please select at least one service!');
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // Load selected service rows
        $s_ids_str = implode(',', $service_id);
        $services  = $this->master->getservices("status='1' AND id IN (" . $s_ids_str . ")");
        if (empty($services)) {
            $this->session->set_flashdata('err_msg', 'Selected services not available!');
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // ── Validate same-type constraint ──────────────────────────────────
        $type_groups = array();
        foreach ($services as $svc) {
            $types = array_map('trim', explode(',', $svc['type']));
            // Primary type: Yearly > Quarterly > Monthly > Once
            $primary = 'Yearly';
            if (in_array('Once',      $types)) $primary = 'Once';
            if (in_array('Monthly',   $types)) $primary = 'Monthly';
            if (in_array('Quarterly', $types)) $primary = 'Quarterly';
            if (in_array('Yearly',    $types)) $primary = 'Yearly';
            $type_groups[$primary][] = $svc['name'];
        }
        if (count($type_groups) > 1) {
            $detail = '';
            foreach ($type_groups as $t => $names) {
                $detail .= $t . ': ' . implode(', ', $names) . '; ';
            }
            $this->session->set_flashdata(
                'err_msg',
                'A package can only contain services of the same billing type. ' .
                    'Found mixed types – ' . rtrim($detail, '; ')
            );
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        $package_type = array_key_first($type_groups);

        // ── Check if any service is already in a DIFFERENT type package ────
        $conflict = array();
        foreach ($service_id as $sid) {
            $check = $this->customer->getservicepackage(
                ['t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year],
                'all'
            );
            if (!empty($check)) {
                foreach ($check as $pkg) {
                    // Skip if same type (user is editing/extending this package type)
                    if (!empty($pkg['package_type']) && $pkg['package_type'] === $package_type) continue;
                    if (!empty($pkg['service_ids'])) {
                        $existing_ids = array_filter(array_map('trim', explode(',', $pkg['service_ids'])));
                        if (in_array((string)$sid, $existing_ids)) {
                            $svc = $this->master->getservices(['id' => $sid], 'single');
                            $conflict[] = (!empty($svc['name']) ? $svc['name'] : "Service #$sid") .
                                " (already in {$pkg['package_type']} package)";
                        }
                    }
                }
            }
        }
        if (!empty($conflict)) {
            $this->session->set_flashdata(
                'err_msg',
                'Some services are already in another package type: ' . implode(', ', $conflict)
            );
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // ── Check if any service was already purchased directly ────────────
        $conflict_direct = array();
        foreach ($service_id as $sid) {
            $existing_purchase = $this->service->getpurchases(
                "t1.user_id='{$user['id']}' AND t1.firm_id='{$firm_id}' AND t1.year='{$year}' AND t1.service_id='{$sid}'"
            );
            if (!empty($existing_purchase)) {
                $svc = $this->master->getservices(['id' => $sid], 'single');
                $conflict_direct[] = !empty($svc['name']) ? $svc['name'] : "Service #$sid";
            }
        }
        if (!empty($conflict_direct)) {
            $this->session->set_flashdata(
                'err_msg',
                'The following service(s) are already purchased directly and cannot be added to a package: ' .
                    implode(', ', $conflict_direct)
            );
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // ── Service options ────────────────────────────────────────────────
        $service_option_ids = array();
        if (!empty($post['service_option']) && is_array($post['service_option'])) {
            foreach ($post['service_option'] as $sid => $opt_id) {
                if (!empty($sid) && !empty($opt_id)) {
                    $service_option_ids[$sid] = $opt_id;
                }
            }
        }

        // ── Append to existing package if one exists ───────────────────────
        $existing_pkg = $this->db->get_where('service_packages', [
            'user_id'      => $user['id'],
            'firm_id'      => $firm_id,
            'year'         => $year,
            'package_type' => $package_type
        ])->unbuffered_row('array');

        if (!empty($existing_pkg)) {
            $existing_sids = !empty($existing_pkg['service_ids']) ? array_filter(array_map('trim', explode(',', $existing_pkg['service_ids']))) : [];
            $existing_opts = !empty($existing_pkg['service_option_ids']) ? json_decode($existing_pkg['service_option_ids'], true) : [];
            if (!is_array($existing_opts)) {
                $existing_opts = [];
            }
            
            // Merge IDs
            $service_id = array_unique(array_merge($existing_sids, $service_id));
            $s_ids_str  = implode(',', $service_id);
            
            // Merge options
            foreach ($service_option_ids as $sid => $opt_id) {
                $existing_opts[$sid] = $opt_id;
            }
            $service_option_ids = $existing_opts;
            
            // Reload services to calculate the combined bill amount
            $services = $this->master->getservices("status='1' AND id IN (" . $s_ids_str . ")");
        }

        $service_option_ids_json = !empty($service_option_ids) ? json_encode($service_option_ids) : null;

        // ── Calculate bill amount & expiry ─────────────────────────────────
        $subtotal     = 0.0;
        foreach ($services as $svc) {
            $rate = isset($svc['rate']) ? (float)$svc['rate'] : 0.0;
            // If service has options and an option was chosen, use option rate
            if (!empty($service_option_ids[$svc['id']])) {
                $opt = $this->master->getserviceoptions(['id' => $service_option_ids[$svc['id']], 'status' => 1], 'single');
                if (!empty($opt['rate'])) $rate = (float)$opt['rate'];
            }
            $subtotal += $rate;
        }
        $customer   = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        $gst_on     = !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $gst_amount = $gst_on ? round($subtotal * 18 / 100, 2) : 0.0;
        $total      = $subtotal + $gst_amount;

        $purchase_date = date('Y-m-d');
        $expiry_date   = $this->calculateExpiryDate($package_type, $services, $purchase_date);

        // ── Persist package ────────────────────────────────────────────────
        $pkg_data = array(
            'user_id'           => $user['id'],
            'firm_id'           => $firm_id,
            'year'              => $year,
            'service_ids'       => $s_ids_str,
            'service_option_ids' => $service_option_ids_json,
            'package_type'      => $package_type,
            'purchase_date'     => $purchase_date,
            'expiry_date'       => $expiry_date,
            'payment_status'    => 0,   // bill generated, not yet deducted
            'bill_amount'       => $total,
        );

        $result = $this->customer->createpackage($pkg_data);

        if ($result['status'] !== true) {
            $this->session->set_flashdata('err_msg', $result['message']);
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // No invoice at purchase time — invoice is only generated when the
        // package expires and gets renewed (auto or manual).
        $this->session->set_flashdata(
            'msg',
            $result['message'] . ' | ₹' . number_format($total, 2) .
                ' will be billed when the package expires on ' .
                date('d-m-Y', strtotime($expiry_date)) . '.'
        );

        redirect($_SERVER['HTTP_REFERER']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAY PACKAGE BILL  (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function paybill()
    {
        header('Content-Type: application/json');
        $package_id = (int)$this->input->post('package_id');
        $user       = getuser();

        if (empty($package_id)) {
            echo json_encode(['status' => false, 'message' => 'Invalid package.']);
            return;
        }

        // Fetch package owned by this user
        $pkg = $this->db->get_where(
            'service_packages',
            ['id' => $package_id, 'user_id' => $user['id']]
        )->unbuffered_row('array');

        if (empty($pkg)) {
            echo json_encode(['status' => false, 'message' => 'Package not found!']);
            return;
        }

        // Must be expired to pay/renew
        $exp = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
        if (!$exp || $exp > time()) {
            echo json_encode(['status' => false, 'message' => 'This package has not expired yet.']);
            return;
        }

        $bill_amount = (float)($pkg['bill_amount'] ?? 0);
        if ($bill_amount <= 0) {
            echo json_encode(['status' => false, 'message' => 'No bill amount found for this package.']);
            return;
        }

        $balance = $this->wallet->getwalletbalance($user['id']);

        if ($balance < $bill_amount) {
            $needed = $bill_amount - $balance;
            echo json_encode([
                'status'    => false,
                'message'   => 'Insufficient wallet balance. Please add ₹' . number_format($needed, 2) . ' to your wallet first.',
                'remaining' => $needed,
                'redirect'  => base_url('mywallet/')
            ]);
            return;
        }

        // ── Deduct from wallet via purchases table (same mechanism as buyservice) ──
        $services_str = $pkg['service_ids'] ?? '';
        $s_ids = array_filter(array_map('trim', explode(',', $services_str)));
        $services = array();
        if (!empty($s_ids)) {
            $services = $this->master->getservices("status='1' AND id IN ('" . implode("','", $s_ids) . "')");
        }

        $datetime    = date('Y-m-d H:i:s');
        $today       = date('Y-m-d');
        $firm_id     = $pkg['firm_id'];
        $year        = $pkg['year'];
        $package_type = $pkg['package_type'] ?? 'Yearly';

        // ── Check customer GST setting ──────────────────────────────────
        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

        // ── Calculate total base rate and GST distribution ───────────────
        $total_base_rate = 0;
        $service_rates = [];
        $opt_data = array();
        if (!empty($pkg['service_option_ids'])) {
            $opt_data = json_decode($pkg['service_option_ids'], true) ?: array();
        }
        foreach ($services as $svc) {
            $rate = (float)$svc['rate'];
            if (!empty($opt_data[$svc['id']])) {
                $opt = $this->master->getserviceoptions(['id' => $opt_data[$svc['id']], 'status' => 1], 'single');
                if (!empty($opt['rate'])) $rate = (float)$opt['rate'];
            }
            $service_rates[$svc['id']] = $rate;
            $total_base_rate += $rate;
        }

        // Calculate GST amounts if enabled - bill_amount is base rate, GST is added on top
        $total_gst = 0;
        if ($gst_enabled && $total_base_rate > 0) {
            // GST is 18% of the base rate
            $total_gst = round(($bill_amount * 18) / 100, 2);
        }

        // Insert a purchase record per service so the wallet balance reduces automatically
        // (wallet balance = wallet credits − sum(purchases.amount) − acc_payment)
        $order_ids = array();
        foreach ($services as $svc) {
            $rate = $service_rates[$svc['id']];
            $subtotal = $rate;
            $gst_amount = 0;

            // Distribute GST proportionally across services
            if ($gst_enabled && $total_base_rate > 0 && $total_gst > 0) {
                $gst_amount = round(($rate / $total_base_rate) * $total_gst, 2);
            }

            $amount = $subtotal + $gst_amount;

            $purchase = array(
                'date'       => $today,
                'year'       => $year,
                'type'       => $package_type,
                'user_id'    => $user['id'],
                'service_id' => $svc['id'],
                'firm_id'    => $firm_id,
                'service'    => $svc['name'] . ' (Package)',
                'rate'       => $rate,
                'subtotal'   => $subtotal,
                'gst_amount' => $gst_amount,
                'gst_enabled' => $gst_enabled ? 1 : 0,
                'amount'     => $amount,
                'status'     => 0,   // pending – admin will mark complete
                'added_on'   => $datetime,
                'updated_on' => $datetime,
            );
            if ($this->db->insert('purchases', $purchase)) {
                $order_ids[] = $this->db->insert_id();
            }
        }

        // ── Recalculate expiry ─────────────────────────────────────────────
        $new_expiry = $this->calculateExpiryDate($package_type, $services, $today);

        // ── Update expiry (keep payment_status=0 so next expiry triggers renewal again)
        $this->db->update('service_packages', [
            'payment_status' => 0,
            'purchase_date'  => $today,
            'expiry_date'    => $new_expiry,
            'updated_on'     => $datetime,
        ], ['id' => $package_id]);

        // ── Generate renewal invoice ───────────────────────────────────────
        $customer   = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        $firm_info  = $this->customer->getfirms(['t1.id' => $firm_id], 'single');
        $gst_on     = !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $subtotal   = $bill_amount / ($gst_on ? 1.18 : 1);
        $gst_amount = $gst_on ? ($bill_amount - $subtotal) : 0;

        $svc_names = array_column($services, 'name');
        try {
            $inv = $this->invoice->create_custom_invoice([
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
                'service_name'   => implode(', ', $svc_names) . ' (Package Renewal)',
                'type'           => $package_type,
                'period_value'   => $year,
                'subtotal'       => round($subtotal, 2),
                'gst_rate'       => $gst_on ? 18.0 : 0.0,
                'gst_amount'     => round($gst_amount, 2),
                'total_amount'   => $bill_amount,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Package renewal invoice error: ' . $e->getMessage());
        }

        echo json_encode([
            'status'  => true,
            'message' => 'Payment of ₹' . number_format($bill_amount, 2) . ' successful! Package renewed until ' . date('d-m-Y', strtotime($new_expiry)) . '.'
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REQUEST DELETE
    // ─────────────────────────────────────────────────────────────────────────

    public function requestdelete()
    {
        // Check column exists
        if ($this->db->query("SHOW COLUMNS FROM `tf_service_packages` LIKE 'request'")->num_rows() == 0) {
            $this->session->set_flashdata("err_msg", "Delete request feature is not available. Please contact administrator.");
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        $user    = getuser();
        $firm_id = $this->session->firm;
        $year    = $this->session->year;

        // Support deleting a specific package_id via POST, or fall back to first for firm/year
        $package_id = (int)$this->input->post('package_id');

        if ($package_id > 0) {
            $service_package = $this->db->get_where(
                'service_packages',
                ['id' => $package_id, 'user_id' => $user['id']]
            )->unbuffered_row('array');
        } else {
            $where = array('t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year);
            $service_package = $this->customer->getservicepackage($where, 'single');
        }

        if (!empty($service_package)) {
            if (!isset($service_package['request'])) {
                $service_package['request'] = 0;
            }
            if ($service_package['request'] == 0 || $service_package['request'] == 2) {
                if ($this->db->update('service_packages', ['request' => 1], ['id' => $service_package['id']])) {
                    $msg = $service_package['request'] == 2
                        ? "Package Delete Request Resubmitted! Admin will review your request."
                        : "Package Delete Request Saved! Admin will review your request.";
                    $this->session->set_flashdata("msg", $msg);
                } else {
                    $this->session->set_flashdata("err_msg", "Failed to save delete request!");
                }
            } else {
                $this->session->set_flashdata("err_msg", "Delete Request already submitted!");
            }
        } else {
            $this->session->set_flashdata("err_msg", "Package not found!");
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REQUEST DELETE ACCOUNT WORK PACKAGE
    // ─────────────────────────────────────────────────────────────────────────

    public function requestdeleteaccountwork()
    {
        // Check column exists
        if ($this->db->query("SHOW COLUMNS FROM `tf_customer_packages` LIKE 'request'")->num_rows() == 0) {
            $this->session->set_flashdata("err_msg", "Delete request feature is not available. Please contact administrator.");
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        $user    = getuser();
        $firm_id = $this->session->firm;
        $year    = $this->session->year;

        // Sync behavior with website rule:
        // Account Work can be deleted only if user has NO service packages
        // for the same firm/year.
        $where_pkg = array(
            't1.user_id' => $user['id'],
            't1.firm_id' => $firm_id,
            't1.year'    => $year
        );
        $existing_service_packages = $this->customer->getservicepackage($where_pkg, 'all');
        if (!empty($existing_service_packages)) {
            $this->session->set_flashdata(
                "err_msg",
                "You already have a Service Package. Delete/resolve your Service Packages first, then you can request Account Work deletion."
            );
            redirect($_SERVER['HTTP_REFERER']);
            return;
        }

        // Support deleting a specific package_id via POST, or fall back to first for firm/year
        $package_id = (int)$this->input->post('package_id');

        if ($package_id > 0) {
            $account_work_package = $this->db->get_where(
                'customer_packages',
                ['id' => $package_id, 'user_id' => $user['id'], 'firm_id' => $firm_id, 'year' => $year, 'status' => 1]
            )->unbuffered_row('array');
        } else {
            $account_work_package = $this->db->get_where(
                'customer_packages',
                ['user_id' => $user['id'], 'firm_id' => $firm_id, 'year' => $year, 'status' => 1]
            )->unbuffered_row('array');
        }

        if (!empty($account_work_package)) {
            if (!isset($account_work_package['request'])) {
                $account_work_package['request'] = 0;
            }
            if ($account_work_package['request'] == 0 || $account_work_package['request'] == 2) {
                if ($this->db->update('customer_packages', ['request' => 1], ['id' => $account_work_package['id']])) {
                    $msg = $account_work_package['request'] == 2
                        ? "Account Work Package Delete Request Resubmitted! Admin will review your request."
                        : "Account Work Package Delete Request Saved! Admin will review your request.";
                    $this->session->set_flashdata("msg", $msg);
                } else {
                    $this->session->set_flashdata("err_msg", "Failed to save delete request!");
                }
            } else {
                $this->session->set_flashdata("err_msg", "Delete Request already submitted!");
            }
        } else {
            $this->session->set_flashdata("err_msg", "Account Work Package not found!");
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LIST PACKAGES
    // ─────────────────────────────────────────────────────────────────────────

    public function list()
    {
        $data = ['title' => 'Package list'];
        $data['breadcrumb'] = array("package" => "My Package", "active" => "Package list");
        $data['alertify'] = true;

        $user    = getuser();
        $year    = $this->session->year;
        $firm_id = $this->session->firm;

        $data['user'] = $user;
        $data['firm_id'] = $firm_id;

        // ALL service packages for this user/firm/year
        $where_pkg = array('t1.user_id' => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year);
        $data['service_packages'] = $this->customer->getservicepackage($where_pkg, 'all');

        // All services (exclude id=1 / account-work) with options
        $all_services = $this->master->getservices(['status' => 1, 'id>' => 1]);
        $services_with_options = array();
        if (!empty($all_services)) {
            foreach ($all_services as $svc) {
                $opts = $this->master->getserviceoptions(['service_id' => $svc['id'], 'status' => 1], 'all');
                $services_with_options[$svc['id']] = ['service' => $svc, 'options' => $opts];
            }
        }
        $data['all_services']          = $all_services;
        $data['services_with_options'] = $services_with_options;

        $this->template->load('package', 'packagelist', $data);
    }
}
