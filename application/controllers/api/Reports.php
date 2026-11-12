<?php
defined('BASEPATH') or exit('No direct script access allowed');
//include Rest Controller library
use chriskacerguis\RestServer\RestController;

class Reports extends RestController
{
    function __construct()
    {
        parent::__construct();
        logrequest();
    }

    public function getpurchasedservices_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $years = getyearmonthvalues($year);
                    $from = $years['year1'] . '-04-01';
                    $to = $years['year2'] . '-03-31';
                    $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id' and t1.year='$year'";
                    // Use group_by_service flag to match web version behavior
                    $services = $this->service->getpurchasedservices($where, 'all', true);
                    if (!empty($services)) {
                        $this->response([
                            'status' => true,
                            'services' => $services
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "No Purchased Services Available!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Firm not Selected!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getworkreport_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $years = getyearmonthvalues($year);
                $from = $years['year1'] . '-04-01';
                $to = $years['year2'] . '-03-31';
                $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id' and t1.year='$year'";
                $this->db->group_by('t1.service_id');
                $services = $this->service->getpurchasedservices($where);
                if (!empty($services)) {
                    $this->response([
                        'status' => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Purchased Services Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getworkreports_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Get completed assessments (purchases with status=4 - Assessment Report Uploaded and assessment status=1 - Completed)
                $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id' and t1.status=4";
                if (!empty($year)) {
                    $where .= " and t1.year='$year'";
                }

                // Join with assessments table to get the assessment file (only completed assessments)
                $this->db->select("t1.*, t2.name as service_name, t2.slug as service_slug, t3.file as assessment_file, t3.date as assessment_date, t3.remarks as assessment_remarks");
                $this->db->from('purchases t1');
                $this->db->join('services t2', 't1.service_id=t2.id', 'left');
                $this->db->join('assessments t3', 't1.id=t3.order_id and t3.status=1', 'left');
                $this->db->where($where);
                $this->db->order_by('t3.date', 'DESC');
                $query = $this->db->get();
                $workreports = $query->result_array();

                // Convert file paths to full URLs for mobile app
                if (!empty($workreports)) {
                    foreach ($workreports as $key => $report) {
                        if (!empty($report['assessment_file'])) {
                            $workreports[$key]['assessment_file_url'] = file_url($report['assessment_file']);
                        }
                    }
                    $this->response([
                        'status' => true,
                        'workreports' => $workreports
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Work Reports Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    /**
     * Pending services: same logic as web Services::pendingservices()
     * Requires year + firm; returns only pending (status=0) services that are in the customer's service package.
     */
    public function getpendingservices_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');
        if (!empty($token) && !empty($firm_id) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {

                // ── 1. Current year service package ────────────────────────
                $service_package = $this->customer->getservicepackage([
                    't1.user_id' => $user['id'],
                    't1.firm_id' => $firm_id,
                    't1.year'    => $year
                ], 'single');

                $current_service_ids = array();
                if (!empty($service_package) && !empty($service_package['service_ids'])) {
                    $current_service_ids = array_filter(array_map('trim', explode(',', $service_package['service_ids'])));
                }

                // ── 2. Expired packages (all other years for this user/firm) ─
                $user_id_escaped  = $this->db->escape($user['id']);
                $firm_id_escaped  = $this->db->escape($firm_id);
                $year_escaped     = $this->db->escape($year);

                $expired_packages = $this->db->query(
                    "SELECT t1.* FROM tf_service_packages t1
                     WHERE t1.user_id={$user_id_escaped}
                       AND t1.firm_id={$firm_id_escaped}
                       AND t1.year!={$year_escaped}"
                )->result_array();

                // ── 3. Collect ALL service IDs (current + expired) ──────────
                $all_package_service_ids = $current_service_ids;
                foreach ($expired_packages as $ep) {
                    if (!empty($ep['service_ids'])) {
                        $all_package_service_ids = array_merge(
                            $all_package_service_ids,
                            array_filter(array_map('trim', explode(',', $ep['service_ids'])))
                        );
                    }
                }
                $all_package_service_ids = array_unique(array_filter($all_package_service_ids));

                // ── 4. Pending purchases from current year ───────────────────
                $services = array();
                if (!empty($all_package_service_ids)) {
                    $service_ids_str = implode(',', array_map('intval', $all_package_service_ids));
                    $where = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.year={$year_escaped} AND t1.status='0' AND t1.service_id IN ($service_ids_str)";
                    $services = $this->service->getpurchasedservices($where, 'all', true);
                    if (empty($services)) $services = array();

                    // ── 5. Pending purchases from expired years ──────────────
                    foreach ($expired_packages as $ep) {
                        if (empty($ep['service_ids'])) continue;
                        $exp_ids     = array_filter(array_map('intval', explode(',', $ep['service_ids'])));
                        $exp_ids_str = implode(',', $exp_ids);
                        $exp_year    = $this->db->escape($ep['year']);
                        $where_exp   = "t1.user_id={$user_id_escaped} AND t1.firm_id={$firm_id_escaped} AND t1.year={$exp_year} AND t1.status='0' AND t1.service_id IN ($exp_ids_str)";
                        $exp_svcs    = $this->service->getpurchasedservices($where_exp, 'all', true);
                        if (!empty($exp_svcs)) {
                            foreach ($exp_svcs as &$es) {
                                $es['expired_package'] = true;
                                $es['expired_year']    = $ep['year'];
                            }
                            unset($es);
                            $services = array_merge($services, $exp_svcs);
                        }
                    }
                }

                // ── 6. Renewal candidates ────────────────────────────────────
                // Services in ANY expired package but NOT in the current year package
                $renewals = array();
                foreach ($expired_packages as $ep) {
                    if (empty($ep['service_ids'])) continue;
                    $ep_ids = array_filter(array_map('trim', explode(',', $ep['service_ids'])));
                    foreach ($ep_ids as $sid) {
                        if ($sid === '') continue;
                        if (in_array($sid, $current_service_ids, true)) continue; // already renewed
                        if (isset($renewals[$sid])) continue;                     // deduplicate

                        $svc = $this->master->getservices(array('id' => $sid, 'status' => 1), 'single');
                        if (empty($svc)) continue;

                        $renewals[$sid] = array(
                            'id'           => 0,
                            'service_id'   => (int)$sid,
                            'service_name' => $svc['name'],
                            'service_slug' => isset($svc['slug']) ? $svc['slug'] : '',
                            'month'        => '',
                            'amount'       => $svc['rate'],
                            'is_renewal'   => 1,
                            'expired_year' => $ep['year'],
                        );
                    }
                }

                if (!empty($renewals)) {
                    $services = array_merge($services, array_values($renewals));
                }

                // ── 7. Expired Account Work packages (customer_packages) ────────────
                $expired_account_work = array();
                $account_work_pkgs = $this->db->get_where('customer_packages', [
                    'user_id' => $user['id'],
                    'firm_id' => $firm_id,
                    'status' => 1
                ])->result_array();

                if (!empty($account_work_pkgs)) {
                    foreach ($account_work_pkgs as $_acpkg) {
                        $exp = !empty($_acpkg['expiry_date']) ? strtotime($_acpkg['expiry_date']) : 0;
                        $is_unpaid = empty($_acpkg['payment_status']) || $_acpkg['payment_status'] == 0;
                        if ($exp && $exp <= time() && $is_unpaid) {
                            // Calculate bill_amount
                            if (empty($_acpkg['bill_amount']) || $_acpkg['bill_amount'] == 0) {
                                $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                                $_acpkg['bill_amount'] = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
                            }

                            $package_id = $_acpkg['package_id'];
                            $service_name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';

                            // Add as expired Account Work package
                            $expired_account_work[] = array(
                                'id'           => $_acpkg['id'],
                                'service_id'   => 1, // Account Work service ID
                                'service_name' => 'Account Work (' . $service_name . ')',
                                'service_slug' => 'account-work',
                                'month'        => '',
                                'amount'       => $_acpkg['bill_amount'],
                                'is_renewal'   => 1,
                                'expired_year' => $_acpkg['year'],
                                'package_source' => 'customer_packages', // Mark as Account Work
                                'package_id'   => $_acpkg['id'], // Store package ID for renewal
                            );
                        }
                    }
                }

                if (!empty($expired_account_work)) {
                    $services = array_merge($services, $expired_account_work);
                }

                if (!empty($services)) {
                    $this->response([
                        'status'   => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status'  => false,
                        'message' => "No Pending Services!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status'  => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status'  => false,
                'message' => "Please provide token, firm_id and year!"
            ], RestController::HTTP_OK);
        }
    }

    public function renewservice_post()
    {
        $token      = $this->post('token');
        $firm_id    = $this->post('firm_id');
        $year       = $this->post('year');
        $service_id = $this->post('service_id');

        if (empty($token) || empty($firm_id) || empty($year) || empty($service_id)) {
            $this->response([
                'status'  => false,
                'message' => "Please provide token, firm_id, year and service_id!"
            ], RestController::HTTP_OK);
            return;
        }

        $user = $this->account->verify_token($token);
        if (empty($user) || !is_array($user) || $user['role'] != 'customer') {
            $this->response([
                'status'  => false,
                'message' => "User Not Logged In!"
            ], RestController::HTTP_OK);
            return;
        }

        if (!is_numeric($service_id)) {
            $this->response(['status' => false, 'message' => 'Invalid service selected.'], RestController::HTTP_OK);
            return;
        }

        // Verify service exists and is active
        $service = $this->master->getservices(array('id' => $service_id, 'status' => 1), 'single');
        if (empty($service)) {
            $this->response(['status' => false, 'message' => 'Service not found or inactive.'], RestController::HTTP_OK);
            return;
        }

        // Verify service was in at least one expired package for this user/firm
        $user_id_escaped  = $this->db->escape($user['id']);
        $firm_id_escaped  = $this->db->escape($firm_id);
        $year_escaped     = $this->db->escape($year);
        $service_id_int   = (int)$service_id;

        $expired_check = $this->db->query(
            "SELECT id FROM tf_service_packages
             WHERE user_id={$user_id_escaped} AND firm_id={$firm_id_escaped}
               AND year!={$year_escaped}
               AND FIND_IN_SET('{$service_id_int}', REPLACE(service_ids,' ','')) > 0
             LIMIT 1"
        )->row_array();

        if (empty($expired_check)) {
            $this->response(['status' => false, 'message' => 'This service is not eligible for renewal.'], RestController::HTTP_OK);
            return;
        }

        // Check if service is already in current year's package
        $current_package = $this->customer->getservicepackage([
            't1.user_id' => $user['id'],
            't1.firm_id' => $firm_id,
            't1.year'    => $year
        ], 'single');

        if (!empty($current_package)) {
            $current_ids = array_filter(array_map('trim', explode(',', $current_package['service_ids'] ?? '')));
            if (in_array((string)$service_id_int, $current_ids, true)) {
                $this->response(['status' => false, 'message' => 'Service is already in your current package.'], RestController::HTTP_OK);
                return;
            }
            // Append to existing package
            $current_ids[] = (string)$service_id_int;
            $new_service_ids = implode(',', array_unique(array_filter($current_ids)));
            $this->db->where('id', $current_package['id']);
            $this->db->update('tf_service_packages', ['service_ids' => $new_service_ids, 'updated_on' => date('Y-m-d H:i:s')]);
        } else {
            // Create new service package for current year
            $this->db->insert('tf_service_packages', [
                'user_id'     => $user['id'],
                'firm_id'     => $firm_id,
                'year'        => $year,
                'service_ids' => (string)$service_id_int,
                'added_on'    => date('Y-m-d H:i:s'),
            ]);
        }

        $notify_data = array(
            'user_id' => $user['id'],
            'type'    => 'package',
            'message' => 'Your service "' . $service['name'] . '" has been renewed for year ' . $year . '.',
        );
        $this->common->savenotification($notify_data);

        // Generate invoice for renewal
        $invoice_no = '';
        $invoice_id = 0;
        $this->load->model('Invoice_model', 'invoice_model');
        $firm_row = $this->customer->getfirms(['t1.user_id' => $user['id'], 't1.id' => $firm_id], 'single');
        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
        $subtotal    = !empty($service['rate']) ? floatval($service['rate']) : 0.0;
        $gst_amount  = 0;
        $total       = $subtotal;
        if ($gst_enabled && $subtotal > 0) {
            $gst_amount = round(($subtotal * 18) / 100, 2);
            $total = $subtotal + $gst_amount;
        }
        $inv_data = [
            'user_id'        => $user['id'],
            'firm_id'        => $firm_id,
            'year'           => $year,
            'invoice_date'   => date('Y-m-d'),
            'billing_name'   => $user['name'],
            'billing_email'  => !empty($user['email']) ? $user['email'] : '',
            'billing_mobile' => !empty($user['mobile']) ? $user['mobile'] : '',
            'firm_name'      => !empty($firm_row['name']) ? $firm_row['name'] : '',
            'firm_gstin'     => !empty($firm_row['gstin']) ? $firm_row['gstin'] : '',
            'firm_pan'       => !empty($firm_row['pan']) ? $firm_row['pan'] : '',
            'service_name'   => $service['name'],
            'type'           => 'Renewal',
            'period_value'   => $year,
            'subtotal'       => $subtotal,
            'gst_rate'       => $gst_enabled ? 18 : 0,
            'gst_amount'     => $gst_amount,
            'total_amount'   => $total,
        ];
        $inv_result = $this->invoice_model->create_custom_invoice($inv_data);
        if ($inv_result['status'] && !empty($inv_result['invoice'])) {
            $invoice_no = $inv_result['invoice']['invoice_no'];
            $invoice_id = (int)$inv_result['invoice']['id'];
            if (strpos($inv_result['message'], 'already exists') === false) {
                $this->common->savenotification(array(
                    'user_id' => (int) $user['id'],
                    'type' => 'invoice',
                    'message' => 'Invoice ' . $invoice_no . ' generated for your renewal.',
                ));
            }
        }

        $this->response([
            'status'     => true,
            'message'    => '"' . $service['name'] . '" renewed successfully for year ' . $year . '!',
            'invoice_no' => $invoice_no,
            'invoice_id' => $invoice_id,
        ], RestController::HTTP_OK);
    }

    public function getmonthlyservices_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $service_id = $this->post('service_id');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($service_id) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $years = getyearmonthvalues($year);
                $from = $years['year1'] . '-04-01';
                $to = $years['year2'] . '-03-31';
                $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id' and t1.year='$year' and t1.service_id='$service_id'";
                $services = $this->service->getpurchasedservices($where);
                if (!empty($services)) {
                    foreach ($services as $key => $service) {
                        if ($service['purchased_type'] == 'Yearly') {
                            $services[$key]['service_slug'] = date('Y', strtotime($service['date']));
                        } elseif ($service['purchased_type'] == 'Monthly') {
                            if ($service['month'] != '') {
                                $month = getyearmonthvalues($service['month']);
                                $services[$key]['service_slug'] = $month['value'];
                            } else {
                                $services[$key]['service_slug'] = date('F-Y', strtotime($service['date']));
                            }
                        } else {
                            $services[$key]['service_slug'] = date('F-Y', strtotime($service['date']));
                        }
                    }
                    $this->response([
                        'status' => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Purchased Services Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getreportgroups_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $quarter = $this->post('quarter');
        $month = $this->post('month');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = "t1.user_id='$user[id]' and t1.status in (1,4)";
                if (!empty($firm_id)) {
                    $where .= " and t3.firm_id='$firm_id'";
                }
                $services = $this->service->getreportgroups($where, 'all', $year, $quarter, $month);
                if (!empty($services)) {
                    $this->response([
                        'status' => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Reports Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getreports_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $quarter = $this->post('quarter');
        $month = $this->post('month');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = "t1.user_id='$user[id]' and t1.status in (1,4) and t1.firm_id='$firm_id'";
                $services = $this->service->getservicereports($where, 'all', $year, $quarter, $month);
                if (!empty($services)) {
                    $this->response([
                        'status' => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Reports Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }


    public function getotherfeereport_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $years = getyearmonthvalues($year);
                $where = array('t1.user_id' => $user['id'], 't1.date>=' => $years['year1'] . '-04-01', 't1.date<=' => $years['year2'] . '-03-31');
                $purchases = $this->service->getpurchases($where);
                $services = $this->master->getservices();
                $data = array();
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

                $data[] = $row;

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
                        $data[] = $row;

                        $total_amount += $total;
                    }
                }
                $row = array('service' => 'Total');
                for ($i = 0; $i < 12; $i++) {
                    $m = date('F', strtotime($start . " +$i month"));
                    $row[$m] = !empty($month[$i]) ? $this->amount->toDecimal($month[$i], false) : 0;
                }
                $row['total'] = $this->amount->toDecimal($total_amount, false);
                $data[] = $row;


                if (!empty($data)) {
                    $this->response([
                        'status' => true,
                        'data' => $data
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Reports Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function oldgetotherfeereport_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $years = getyearmonthvalues($year);
                $where = array('t1.user_id' => $user['id'], 't1.date>=' => $years['year1'] . '-04-01', 't1.date<=' => $years['year2'] . '-03-31');
                $purchases = $this->service->getpurchases($where);
                $services = $this->master->getservices();
                $data = array();
                $row = array('Service');
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
                    $row[] = date('F-Y', strtotime($start . " +$i month"));
                }
                $row[] = "Total";

                $data[] = $row;

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
                        $row[] = $single['name'];
                        for ($i = 0; $i < 12; $i++) {
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
                            $row[] = $text;
                        }
                        $row[] = $this->amount->toDecimal($total, false);
                        $data[] = $row;

                        $total_amount += $total;
                    }
                }
                $row = array('Total');
                for ($i = 0; $i < 12; $i++) {
                    $row[] = !empty($month[$i]) ? $this->amount->toDecimal($month[$i], false) : 0;
                }
                $row[] = $this->amount->toDecimal($total_amount, false);
                $data[] = $row;


                if (!empty($data)) {
                    $this->response([
                        'status' => true,
                        'data' => $data
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Reports Available!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getaccountancyreports_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $user_id = $user['id'];
                $yearval = getyearmonthvalues($year);
                $year1 = $yearval['year1'];
                $year2 = $yearval['year2'];
                $from = "$year1-04-01";
                $to = "$year2-03-31";
                $data = array();
                $where = array('user_id' => $user_id, 'status' => 1);
                $query = $this->db->get_where('customer_packages', $where);
                if ($query->num_rows() > 0) {
                    $cpackage = $query->unbuffered_row('array');
                    $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

                    // Accounting Fee is only for Turnover type packages
                    // Monthly type packages don't use turnover-based fee calculation
                    if ($pkg_type == 'Monthly') {
                        $this->response([
                            'status' => false,
                            'message' => 'Accounting Fee is not applicable for Monthly Account Work packages.'
                        ], RestController::HTTP_OK);
                        return;
                    }
                    $where2 = "t1.user_id='$user_id' and t1.firm_id='$firm_id' and t1.date>='$from' and t1.date<='$to'";
                    $accountancy = $this->service->getturnoverswithpayment($where2);
                    $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
                    $turnover = array_sum($turnovers);
                    $total_turnover = $turnover;
                    $cpackage = $query->unbuffered_row('array');
                    $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                    $package = $this->master->getpackages(['name' => $name, 'turnover>' => $turnover], 'single');
                    $date = date('Y-m-d');
                    $percent = 2 / 100;
                    $report = array();
                    if (!empty($accountancy)) {
                        $total_fees = $total_other = $total_paid = $total_penalty = $total_days = 0;
                        $outstanding = $total = $balance = 0;
                        $total_sum = 0;
                        $customer_row = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                        $gst_enabled_api = !empty($customer_row) && !empty($customer_row['gst_enabled']) && $customer_row['gst_enabled'] == 1;
                        $turnover_calc = calculate_turnover_monthly_fees($accountancy, $name, $gst_enabled_api);
                        $monthly_fees = $turnover_calc['monthly_fees'];
                        if (!empty($turnover_calc['package'])) {
                            $package = $turnover_calc['package'];
                        }

                        foreach ($accountancy as $idx => $single) {
                            $days = $paid = $penalty = 0;
                            $paid = !empty($single['paid']) ? (float)$single['paid'] : 0;

                            // Outstanding should be the previous month's balance (unpaid amount)
                            $outstanding = $balance;

                            $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;
                            $other_fee = $single['other_fee'] ?? 0;
                            $total_other += $other_fee;
                            $balance = $outstanding + $acc_fees + $other_fee;
                            $is_confirmed = (($single['auto_debit_status'] ?? '') === 'Confirmed');
                            if ($is_confirmed) {
                                $balance = 0;
                            } elseif ($single['due_date'] < $date && $paid < $balance) {
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
                            $total_sum += $total;
                            $total_fees += $acc_fees;
                            $total_paid += $paid;

                            $isPastMonth = false;

                            $renewalMethod = 'AUTO_WALLET';

                            $auto_debit_status_label = $single['auto_debit_status'] ?? 'Pending';


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
                            $due_date = $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--';
                            $row = array('month' => $month,
                                'outstanding' => round($outstanding, 2),
                                'gto' => round($single['turnover'], 2),
                                'acc_fees' => round($acc_fees, 2),
                                'other_fee' => round($other_fee, 2),
                                'paid' => round($paid, 2),
                                'balance' => round($balance, 2),
                                'due_date' => $due_date,
                                'penalty' => round($penalty, 2),
                                'total' => round($total, 2),
                                'due_days' => $days,

                                'renewal_method' => $renewalMethod,

                                'auto_debit_status_label' => $auto_debit_status_label

                            );

                            $report[] = $row;
                        }
                        $row = array(
                            'month' => 'Total',
                            'outstanding' => 0,
                            'gto' => round($total_turnover, 2),
                            'acc_fees' => round($total_fees, 2),
                            'other_fee' => round($total_other, 2),
                            'paid' => round($total_paid, 2),
                            'balance' => 0,
                            'due_date' => '',
                            'penalty' => round($total_penalty, 2),
                            'total' => round($total_sum, 2), // Use sum of all row totals instead of calculation
                            'due_days' => $total_days
                        );

                        $report[] = $row;
                        $this->response([
                            'status' => true,
                            'report' => $report,
                            'package_name' => $name
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "No Data Found!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Package Selected!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    /**
     * Get payment report data with unpaid months and GST calculation
     * Similar to Reports::payment() but returns JSON for mobile app
     */
    public function getpaymentreport_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $user_id = $user['id'];
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
                    $this->response([
                        'status' => false,
                        'message' => "No Data Found!"
                    ], RestController::HTTP_OK);
                    return;
                }

                $where = array('user_id' => $user_id, 'status' => 1);
                $query = $this->db->get_where('customer_packages', $where);
                if ($query->num_rows() == 0) {
                    $this->response([
                        'status' => false,
                        'message' => "Package not Active!"
                    ], RestController::HTTP_OK);
                    return;
                }

                $cpackage = $query->unbuffered_row('array');
                $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

                // Accounting Pay is only for Turnover type packages
                // Monthly type packages don't use turnover-based payment calculation
                if ($pkg_type == 'Monthly') {
                    $this->response([
                        'status' => false,
                        'message' => 'Accounting Pay is not applicable for Monthly Account Work packages.'
                    ], RestController::HTTP_OK);
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
                $total_paid = 0;
                $outstanding = $total = 0;

                $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                $turnover_calc = calculate_turnover_monthly_fees($accountancy, $name, $gst_enabled);
                $monthly_fees = $turnover_calc['monthly_fees'];
                if (!empty($turnover_calc['package'])) {
                    $package = $turnover_calc['package'];
                }

                foreach ($accountancy as $idx => $single) {
                    $days = $paid = $penalty = 0;
                    $paid = !empty($single['paid']) ? (float)$single['paid'] : 0;
                    $outstanding = $total;
                    $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;
                    $other_fee = $single['other_fee'] ?? 0;
                    $balance = $outstanding + $acc_fees + $other_fee;
                    $is_confirmed = (($single['auto_debit_status'] ?? '') === 'Confirmed');

                    if ($is_confirmed) {
                        $balance = 0;
                    } elseif ($single['due_date'] < $date && $paid < $balance) {
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
                    if ($balance < 0) {
                        $balance = 0;
                    }
                    $total = $balance + $penalty;

                    // Only include unpaid months
                    if ($balance > 0) {
                        $unpaid_months[] = array(
                            'id' => $single['id'],
                            'date' => $single['date'],
                            'month' => $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--',
                            'due_date' => $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--',
                            'outstanding' => round($outstanding, 2),
                            'acc_fees' => round($acc_fees, 2),
                            'penalty' => round($penalty, 2),
                            'balance' => round($balance, 2),
                            'total' => round($total, 2),
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

                // Calculate GST if enabled
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

                $this->response([
                    'status' => true,
                    'unpaid_months' => $unpaid_months,
                    'last_month_balance' => round($last_month_balance, 2),
                    'payment_month_range' => $payment_month_range,
                    'gst_enabled' => $gst_enabled,
                    'gst_rate' => $gst_rate,
                    'gst_amount' => round($gst_amount, 2),
                    'total_with_gst' => round($total_with_gst, 2),
                    'sgst_amount' => round($sgst_amount, 2),
                    'cgst_amount' => round($cgst_amount, 2),
                    'igst_amount' => round($igst_amount, 2),
                    'states_match' => $states_match,
                    'package' => $package
                ], RestController::HTTP_OK);
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User Not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    /**
     * Process accountancy payment for all unpaid months
     * Matches Reports::processpayment() logic - processes all unpaid months with incremental amounts
     */
    public function processaccountancypayment_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        $amount = $this->post('amount'); // Total amount including GST

        if (!empty($token) && !empty($year) && !empty($firm_id) && !empty($amount)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $user_id = $user['id'];
                $amount = floatval($amount);

                if (empty($year) || empty($firm_id) || empty($amount) || $amount <= 0) {
                    $this->response([
                        'status' => false,
                        'message' => 'Invalid payment data!'
                    ], RestController::HTTP_OK);
                    return;
                }

                // Get unpaid months data (same logic as getpaymentreport)
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
                    $this->response([
                        'status' => false,
                        'message' => 'No Data Found!'
                    ], RestController::HTTP_OK);
                    return;
                }

                $where = array('user_id' => $user_id, 'status' => 1);
                $query = $this->db->get_where('customer_packages', $where);
                if ($query->num_rows() == 0) {
                    $this->response([
                        'status' => false,
                        'message' => 'Package not Active!'
                    ], RestController::HTTP_OK);
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

                $customer_check = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                $gst_enabled_check = !empty($customer_check) && !empty($customer_check['gst_enabled']) && $customer_check['gst_enabled'] == 1;
                $turnover_calc = calculate_turnover_monthly_fees($accountancy, $name, $gst_enabled_check);
                $monthly_fees = $turnover_calc['monthly_fees'];

                foreach ($accountancy as $idx => $single) {
                    $days = $paid = $penalty = 0;
                    $paid = !empty($single['paid']) ? (float)$single['paid'] : 0;
                    $outstanding = $total;
                    $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;
                    $other_fee = $single['other_fee'] ?? 0;
                    $balance = $outstanding + $acc_fees + $other_fee;
                    $is_confirmed = (($single['auto_debit_status'] ?? '') === 'Confirmed');

                    if ($is_confirmed) {
                        $balance = 0;
                    } elseif ($single['due_date'] < $date && $paid < $balance) {
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
                    if ($balance < 0) {
                        $balance = 0;
                    }
                    $total = $balance + $penalty;

                    if ($balance > 0) {
                        $unpaid_months[] = array(
                            'id' => $single['id'],
                            'date' => $single['date'],
                            'due_date' => $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--',
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
                    $total_paid += $paid;
                }

                // Get last month's balance for validation
                $last_month_balance = 0;
                $last_month_data = null;
                if (!empty($unpaid_months)) {
                    $last_month_data = end($unpaid_months);
                    $last_month_balance = $last_month_data['balance'];
                }

                // Check customer GST setting to validate payment amount
                $gst_amount_check = $gst_enabled_check ? round(($last_month_balance * 18) / 100, 2) : 0;
                $expected_total = $last_month_balance + $gst_amount_check;

                if (abs($amount - $expected_total) > 0.01) {
                    $this->response([
                        'status' => false,
                        'message' => 'Payment amount does not match the expected total! Expected: ₹' . number_format($expected_total, 2) . ($gst_enabled_check ? ' (including GST)' : '')
                    ], RestController::HTTP_OK);
                    return;
                }

                // Check wallet balance
                $this->load->model('Wallet_model', 'wallet');
                $wallet_balance = $this->wallet->getwalletbalance($user_id);

                if ($wallet_balance <= 0) {
                    $this->response([
                        'status' => false,
                        'message' => 'Insufficient wallet balance! Your wallet balance is ₹' . number_format($wallet_balance, 2) . '. Please add funds to your wallet first.'
                    ], RestController::HTTP_OK);
                    return;
                }

                $total_amount_needed = $expected_total;

                if ($wallet_balance < $total_amount_needed) {
                    $needed = round($total_amount_needed - $wallet_balance, 2);
                    $this->response([
                        'status' => false,
                        'message' => 'Insufficient wallet balance! You need ₹' . number_format($needed, 2) . ' more. Current balance: ₹' . number_format(round($wallet_balance, 2), 2) . ', Required: ₹' . number_format($total_amount_needed, 2) . ($gst_enabled_check ? ' (including GST)' : '')
                    ], RestController::HTTP_OK);
                    return;
                }

                // Process payment for ALL unpaid months
                if (!empty($unpaid_months)) {
                    $payment_success = true;
                    $payment_errors = array();
                    $previous_balance = 0;

                    foreach ($unpaid_months as $month_data) {
                        // Calculate incremental amount for this month
                        $month_payment_amount = $month_data['balance'] - $previous_balance;

                        if ($month_payment_amount <= 0) {
                            continue;
                        }

                        $previous_balance = $month_data['balance'];

                        // Convert due_date format
                        $payment_date = date('Y-m-d');
                        if (!empty($month_data['due_date']) && $month_data['due_date'] != '--') {
                            $due_date_parts = explode('-', $month_data['due_date']);
                            if (count($due_date_parts) == 3) {
                                $payment_date = $due_date_parts[2] . '-' . $due_date_parts[1] . '-' . $due_date_parts[0];
                            }
                        }

                        $acc_date = !empty($month_data['date']) ? $month_data['date'] : date('Y-m-d');

                        // Calculate GST for this month
                        $month_gst = $gst_enabled_check ? round(($month_payment_amount * 18) / 100, 2) : 0;
                        $month_total = round($month_payment_amount + $month_gst, 2);

                        $payment_data = array(
                            'user_id' => $user_id,
                            'firm_id' => $firm_id,
                            'year' => $year,
                            'acc_date' => $acc_date,
                            'date' => $payment_date,
                            'amount' => $month_total,
                            'acc_fee' => $month_payment_amount,
                            'added_by' => $user['id']
                        );

                        $result = $this->wallet->makeaccountancypayment($payment_data);
                        if ($result['status'] !== true) {
                            $payment_success = false;
                            $payment_errors[] = $result['message'] . ' (Month: ' . (!empty($month_data['date']) ? date('F Y', strtotime($month_data['date'])) : 'N/A') . ', Amount: ₹' . number_format($month_payment_amount, 2) . ')';
                        } else {
                            $this->db->where('user_id', $user_id)
                                     ->where('firm_id', $firm_id)
                                     ->where('date', $acc_date)
                                     ->update('accountancy', ['auto_debit_status' => 'Confirmed']);
                        }
                    }

                    if (!$payment_success) {
                        $this->response([
                            'status' => false,
                            'message' => 'Payment partially failed: ' . implode(', ', $payment_errors)
                        ], RestController::HTTP_OK);
                        return;
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => 'No unpaid month found!'
                    ], RestController::HTTP_OK);
                    return;
                }

                // Generate invoice
                $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                $firm = $this->db->get_where('firms', ['id' => $firm_id])->row_array();

                $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                $subtotal = $last_month_balance;
                $gst_rate = $gst_enabled ? 18.0 : 0.0;
                $gst_amount = $gst_enabled ? round(($last_month_balance * $gst_rate) / 100, 2) : 0;
                $total_amount = $subtotal + $gst_amount;

                // Get period value
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
                }

                $this->load->model('Invoice_model', 'invoice');
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

                $this->common->savenotification(array(
                    'user_id' => $user_id,
                    'type' => 'payment',
                    'message' => $payment_msg,
                ));

                $this->response([
                    'status' => true,
                    'message' => $payment_msg,
                    'invoice_no' => $invoice_no
                ], RestController::HTTP_OK);
            } else {
                $this->response([
                    'status' => false,
                    'message' => 'User Not Logged In!'
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => 'Please provide all Details!'
            ], RestController::HTTP_OK);
        }
    }
}
