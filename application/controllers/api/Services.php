<?php
defined('BASEPATH') or exit('No direct script access allowed');
//include Rest Controller library
use chriskacerguis\RestServer\RestController;

class Services extends RestController
{
    function __construct()
    {
        parent::__construct();
        logrequest();
    }

    public function buyservice_post()
    {
        $token = $this->post('token');
        $service_id = $this->post('service_ids');
        $firm_id = $this->post('firm_id');
        $amount = $this->post('amount');
        $year = $this->post('year');
        $type = empty($this->post('type')) ? '' : $this->post('type');
        $service_option = $this->post('service_option'); // Service option for dynamic pricing
        $period_value = $this->post('period_value'); // Period value for Monthly/Quarterly/Yearly

        if (!empty($token) && !empty($service_id) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $service_ids = explode(',', $service_id);
                $where = array('t1.id' => $firm_id, "t1.user_id" => $user['id']);
                $firm = $this->customer->getfirms($where, 'single');
                if (count($service_ids) > 1) {
                    $this->response([
                        'status' => false,
                        'message' => "Select Only One Service!"
                    ], RestController::HTTP_OK);
                } elseif (!empty($firm)) {
                    $where = "status='1' and id ='$service_id'";
                    $service = $this->master->getservices($where, 'single');
                    if (!empty($service)) {
                        $service_for = $service['service_for'];
                        $types = explode(',', $service['type']);
                        $status = true;
                        $message = "";

                        // Check if this service has dynamic options
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

                        if ($service_id == 1) {
                            $status = false;
                            $message = "Select Package to Activate " . $service['name'];
                            if ($type == 'Monthly') {
                                $message = "Select Package and enter Monthly Debit Amount to Activate " . $service['name'];
                            }
                        } elseif (!$has_service_options && !in_array($type, $types)) {
                            $status = false;
                            $message = $type . " option not available for " . $service['name'];
                        } elseif ($has_service_options && !empty($service_option)) {
                            // Handle services with dynamic options - check for duplicate purchase of same option
                            $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year'";
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
                                }
                            }
                        } elseif ($service['type'] == 'Once') {
                            $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id'";
                            if ($service_for == 'Firm') {
                                $where2 .= " and t1.firm_id='$firm_id'";
                            }
                            $purchases = $this->service->getpurchases($where2);
                            if (!empty($purchases)) {
                                $status = false;
                                $message = "You have already Purchased " . $service['name'] . "!";
                            }
                        } elseif ($types[0] == 'Yearly' && count($types) == 1) {
                            $where2 = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.year='$year'";
                            if ($service_for == 'Firm') {
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
                                $service['rate'] = $service_id == 1 ? $amount : $service['rate'];
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

                            // Store service option if applicable
                            if ($has_service_options && !empty($service_option)) {
                                $single['service_option'] = $service_option;
                                $single['service_option_display'] = $selected_option_display;
                            }

                            $balance = $this->wallet->getwalletbalance($user['id']);

                            if ($balance >= $total) {
                                $data = array($single);
                                $data['name'] = $user['name'];
                                //print_pre($data,true);
                                $result = $this->service->purchaseservices($data);
                                // Generate invoice for the purchase
                                $invoice_no = '';
                                $invoice_id = 0;
                                if ($result['status'] == true && !empty($result['order_id'])) {
                                    $this->load->model('Invoice_model', 'invoice_model');
                                    $order = $this->service->getpurchases(['t1.id' => $result['order_id']], 'single');
                                    if (!empty($order)) {
                                        $inv_result = $this->invoice_model->create_for_order($order);
                                        if ($inv_result['status'] && !empty($inv_result['invoice'])) {
                                            $invoice_no = $inv_result['invoice']['invoice_no'];
                                            $invoice_id = (int)$inv_result['invoice']['id'];
                                            if (strpos($inv_result['message'], 'already exists') === false) {
                                                $this->common->savenotification(array(
                                                    'user_id' => (int) $user['id'],
                                                    'type' => 'invoice',
                                                    'order_id' => (int) $result['order_id'],
                                                    'message' => 'Invoice ' . $invoice_no . ' generated for your order.',
                                                ));
                                            }
                                        }
                                    }
                                }
                                if ($result['status'] == true) {
                                    $this->response([
                                        'status'     => true,
                                        'message'    => $result['message'],
                                        'invoice_no' => $invoice_no,
                                        'invoice_id' => $invoice_id,
                                    ], RestController::HTTP_OK);
                                } else {
                                    $this->response([
                                        'status'  => true,
                                        'message' => $result['message']
                                    ], RestController::HTTP_OK);
                                }
                            } else {
                                $remaining = $total - $balance;
                                $this->response([
                                    'status' => false,
                                    'amount' => $remaining,
                                    'message' => "Add to Wallet"
                                ], RestController::HTTP_OK);
                            }
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => $message
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "No Service Selected!"
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
    public function getservicetypes_post()
    {
        $token = $this->post('token');
        $service_id = $this->post('service_id');
        if (!empty($token) && !empty($service_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = "status='1' and id ='$service_id'";
                $service = $this->master->getservices($where, 'single');
                if (!empty($service)) {
                    $types = explode(',', $service['type']);
                    $this->response([
                        'status' => true,
                        'types' => $types
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Service Not Available!"
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

    public function getserviceoptions_post()
    {
        $token = $this->post('token');
        $service_id = $this->post('service_id');
        if (!empty($token) && !empty($service_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $options = $this->master->get_active_service_options($service_id);
                $pricing = array();
                $display_names = array();
                if (!empty($options)) {
                    foreach ($options as $option) {
                        $pricing[$option['option_key']] = $option['rate'];
                        $display_names[$option['option_key']] = $option['display_name'];
                    }
                    $this->response([
                        'status' => true,
                        'pricing' => $pricing,
                        'display_names' => $display_names,
                        'options' => $options
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Options Available for this Service!"
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

    public function selectpackage_post()
    {
        $token = $this->post('token');
        $package_id = $this->post('package_id');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        $type = $this->post('type');
        $amount = $this->post('amount');
        $autodebit = $this->post('autodebit');
        // For Monthly type, package_id should be 0 (will be set to 0 in DB)
        // For Turnover type, package_id is required (must be 1 or 2)
        $is_monthly = !empty($type) && $type == 'Monthly';
        if ($is_monthly) {
            // Monthly type: package_id should be 0 (or empty, will be set to 0)
            // Accept 0 or empty for Monthly type
            $valid = !empty($token) && !empty($year) && !empty($firm_id) && (empty($package_id) || $package_id == 0 || $package_id == '0');
            $name = 'Accountancy Prime'; // Default name (will be ignored for Monthly type)
            // Ensure package_id is 0 for Monthly type
            $package_id = 0;
        } else {
            // Turnover type: package_id is required and must be 1 or 2
            $valid = !empty($token) && !empty($year) && !empty($firm_id) && !empty($package_id) && ($package_id == 1 || $package_id == 2);
            $name = !empty($package_id) && $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
        }

        if ($valid) {
            $autodebit = 1;
            $package = $this->master->getpackages(['name' => $name]);
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
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
                    if ($type == "Monthly" && empty($amount)) {
                        $status = false;
                        $message = "Enter Monthly Debit Amount to Select Package!";
                    }
                    if ($status) {
                        $datetime = date('Y-m-d H:i:s');
                        $purchase_date = date('Y-m-d');

                        // Calculate expiry date based on type (matching web's buyservice logic)
                        $expiry_date = null;
                        $package_type = $type; // Turnover, Monthly, etc.
                        $bill_amount = 0;

                        if ($type == "Monthly") {
                            // Monthly: expiry = next month's auto debit date (28th of next month)
                            // Always use next month's debit date, regardless of purchase date
                            // Get Account Work service to get debit_date
                            $service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
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
                            $bill_amount = !empty($amount) ? (float)$amount : 0;
                        } else {
                            // Turnover/Yearly: expiry = next year's debit_date or +1 year
                            // Get Account Work service (id=1) to get debit_date
                            $service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
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
                        // The entered amount is stored as-is in both 'amount' and 'bill_amount' fields

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
                        // Generate invoice ONLY for Monthly type with amount > 0 (matching web behavior)
                        // Web does NOT generate invoice for Turnover type Account Work packages
                        $invoice_no = '';
                        $invoice_id = 0;
                        if ($result) {
                            // Only generate invoice for Monthly type with amount
                            if ($type == 'Monthly' && !empty($amount) && floatval($amount) > 0) {
                                // Build customer/firm info for invoice
                                $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                                $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                                $pkg_amount  = floatval($amount);
                                $subtotal    = $pkg_amount;
                                $gst_amount_pkg = 0;
                                $total_pkg   = $subtotal;
                                if ($gst_enabled && $subtotal > 0) {
                                    $gst_amount_pkg = round(($subtotal * 18) / 100, 2);
                                    $total_pkg = $subtotal + $gst_amount_pkg;
                                }
                                $this->load->model('Invoice_model', 'invoice_model');
                                $inv_data = [
                                    'user_id'        => $user['id'],
                                    'firm_id'        => $firm['id'],
                                    'year'           => $year,
                                    'invoice_date'   => date('Y-m-d'),
                                    'billing_name'   => $user['name'],
                                    'billing_email'  => !empty($user['email']) ? $user['email'] : '',
                                    'billing_mobile' => !empty($user['mobile']) ? $user['mobile'] : '',
                                    'firm_name'      => !empty($firm['name']) ? $firm['name'] : '',
                                    'firm_gstin'     => !empty($firm['gstin']) ? $firm['gstin'] : '',
                                    'firm_pan'       => !empty($firm['pan']) ? $firm['pan'] : '',
                                    'service_name'   => $name,
                                    'type'           => $type,
                                    'period_value'   => $year,
                                    'subtotal'       => $subtotal,
                                    'gst_rate'       => $gst_enabled ? 18 : 0,
                                    'gst_amount'     => $gst_amount_pkg,
                                    'total_amount'   => $total_pkg,
                                ];
                                $inv_result = $this->invoice_model->create_custom_invoice($inv_data);
                                if ($inv_result['status'] && !empty($inv_result['invoice'])) {
                                    $invoice_no = $inv_result['invoice']['invoice_no'];
                                    $invoice_id = (int)$inv_result['invoice']['id'];
                                }
                            }

                            // Build success message with expiry date (matching web)
                            $expiry_msg = '';
                            if (!empty($expiry_date)) {
                                $expiry_msg = ' Package expires on ' . date('d-m-Y', strtotime($expiry_date)) . '.';
                            }

                            $this->response([
                                'status'     => true,
                                'message'    => $message . $expiry_msg,
                                'invoice_no' => $invoice_no,
                                'invoice_id' => $invoice_id,
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status'  => true,
                                'message' => $message
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => true,
                            'message' => $message
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

    public function switchpackage_post()
    {
        $token = $this->post('token');
        $package_id = $this->post('package_id');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($firm_id) && !empty($package_id)) {
            $name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            $package = $this->master->getpackages(['name' => $name]);
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array('user_id' => $user['id'], 'firm_id' => $firm_id, 'status' => 1);
                $query = $this->db->get_where('customer_packages', $where);
                $status = false;
                $message = "No Package selected previously!";
                if ($query->num_rows() > 0) {
                    $cpackage = $query->unbuffered_row('array');
                    if ($cpackage['package_id'] == $package_id) {
                        $status = false;
                        $message = "Package Already Selected!";
                    } else {
                        $check = $this->db->get_where('customer_packages', [
                            'user_id' => $user['id'],
                            'package_id' => $package_id,
                            'firm_id' => $firm_id,
                            'status' => 2
                        ])->num_rows();
                        if ($check == 0) {
                            $status = true;
                            $message = "Package change Request Saved Successfully!";
                        } else {
                            $status = false;
                            $message = "Package change Request Already Saved!";
                        }
                    }
                }
                if ($status) {
                    $datetime = date('Y-m-d H:i:s');
                    $data = array(
                        'user_id' => $user['id'],
                        'package_id' => $package_id,
                        'firm_id' => $firm_id,
                        'status' => 2,
                        'added_on' => $datetime,
                        'updated_on' => $datetime
                    );
                    if (!empty($autodebit) && $autodebit != 0) {
                        $data['autodebit'] = 1;
                    }
                    $result = $this->db->insert("customer_packages", $data);
                    if ($result) {
                        $this->response([
                            'status' => true,
                            'message' => $message
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => true,
                            'message' => $message
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => true,
                        'message' => $message
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

    public function myservices_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id'";
                    // Use group_by_service flag to match web version behavior (group by service_id)
                    $services = $this->service->getpurchasedservices($where, 'all', true);
                    if (!empty($services)) {
                        $this->response([
                            'status' => true,
                            'services' => $services
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "No Service Purchased!"
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

    public function mypackage_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array('user_id' => $user['id'], 'status' => 1);
                $query = $this->db->get_where('customer_packages', $where);
                if ($query->num_rows() > 0) {
                    $cpackage = $query->unbuffered_row('array');
                    $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';

                    // For Monthly type, return "Account Work Monthly" as package name
                    // For Turnover type, return package name (Prime/Premium)
                    if ($pkg_type == 'Monthly') {
                        $name = 'Account Work Monthly';
                        $package = []; // No rate chart for Monthly type
                    } else {
                        $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                        $package = $this->master->getpackages(['name' => $name]);
                    }
                    $status = true;
                    $this->response([
                        'status' => true,
                        'name' => $name,
                        'package_id' => $cpackage['package_id'],
                        'package_name' => $name,
                        'autodebit' => $cpackage['autodebit'],
                        'package' => $package
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Package selected!"
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

    public function getaccountworkpackagedetails_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && !empty($firm_id) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Get Account Work package for this user/firm/year
                $where = array(
                    'user_id' => $user['id'],
                    'firm_id' => $firm_id,
                    'year' => $year,
                    'status' => 1
                );
                $query = $this->db->get_where('customer_packages', $where);

                if ($query->num_rows() > 0) {
                    $pkg = $query->unbuffered_row('array');

                    // Calculate bill_amount for expired packages if needed
                    $expiry_ts = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
                    $is_expired = $expiry_ts && $expiry_ts <= time();
                    $is_unpaid = empty($pkg['payment_status']) || $pkg['payment_status'] == 0;
                    $pkg_type = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Turnover';

                    // If expired and bill_amount is 0, use the service rate (always show ₹5,000)
                    if ($is_expired && $is_unpaid && (empty($pkg['bill_amount']) || $pkg['bill_amount'] == 0)) {
                        $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                        $pkg['bill_amount'] = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
                    }

                    // Get customer GST status to calculate total amount with GST
                    $this->load->model('Customer_model', 'customer');
                    $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                    $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                    // Calculate total amount (bill_amount + GST) for display
                    $this->load->helper('dropdown');
                    $bill_amount = !empty($pkg['bill_amount']) ? (float)$pkg['bill_amount'] : 0;
                    $gst_rate = $gst_enabled ? get_gst_rate() : 0.0;
                    $gst_amount = $gst_enabled ? round(($bill_amount * $gst_rate) / 100, 2) : 0;
                    $total_amount = $bill_amount + $gst_amount;

                    // Add GST info to response
                    $pkg['gst_enabled'] = $gst_enabled ? 1 : 0;
                    $pkg['gst_rate'] = $gst_rate;
                    $pkg['gst_amount'] = $gst_amount;
                    $pkg['total_amount'] = $total_amount; // Total amount with GST (what user will actually pay)

                    $this->response([
                        'status' => true,
                        'package' => $pkg
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => 'Account Work package not found for this firm and year'
                    ], RestController::HTTP_OK);
                }
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

    public function renewpackage_post()
    {
        $token = $this->post('token');
        $package_id = $this->post('package_id');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && !empty($package_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
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
                    $this->response([
                        'status' => false,
                        'message' => 'Package not found.'
                    ], RestController::HTTP_OK);
                    return;
                }

                // Must be expired to renew
                $exp = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
                if (!$exp || $exp > time()) {
                    $this->response([
                        'status' => false,
                        'message' => 'This package has not expired yet.'
                    ], RestController::HTTP_OK);
                    return;
                }

                $bill = (float)($pkg['bill_amount'] ?? 0);
                if ($bill <= 0) {
                    $this->response([
                        'status' => false,
                        'message' => 'No bill amount for this package.'
                    ], RestController::HTTP_OK);
                    return;
                }

                // Check wallet balance
                $this->load->model('Wallet_model', 'wallet');
                $balance = $this->wallet->getwalletbalance($user['id']);
                if ($balance < $bill) {
                    $needed = $bill - $balance;
                    $this->response([
                        'status'   => false,
                        'message'  => 'Insufficient wallet balance. Please add ₹' . number_format($needed, 2) . ' to your wallet first.',
                        'redirect' => base_url('mywallet/')
                    ], RestController::HTTP_OK);
                    return;
                }

                $pkg_type = !empty($pkg['package_type']) ? $pkg['package_type'] : ($is_account_work ? 'Turnover' : 'Yearly');
                $firm_id_pkg = $pkg['firm_id'];
                $year_pkg = $pkg['year'];
                $today = date('Y-m-d');
                $datetime = date('Y-m-d H:i:s');

                // ── Handle Account Work packages ────────────────────────────────
                if ($is_account_work) {
                    // For Turnover type, recalculate bill_amount if needed
                    if ($pkg_type == 'Turnover' && (empty($pkg['bill_amount']) || $pkg['bill_amount'] == 0)) {
                        $dates = getfiscaldates(date('Y-m-d', strtotime($pkg['purchase_date'] ?? $pkg['added_on'])));
                        $from = $dates['from'];
                        $to = $dates['to'];
                        $where2 = "t1.user_id='{$user['id']}' and t1.firm_id='{$firm_id_pkg}' and t1.date>='$from' and t1.date<='$to'";
                        $accountancy = $this->service->getturnoverswithpayment($where2);
                        $turnovers = !empty($accountancy) ? array_column($accountancy, 'turnover') : array(0);
                        $turnover = array_sum($turnovers);
                        $total_turnover = $turnover * 100000; // multiplier

                        $package_id_val = $pkg['package_id'];
                        $name = $package_id_val == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
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

                    // ── Check customer GST setting ──────────────────────────────────
                    $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                    $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                    // Calculate GST - bill is base rate, GST is added on top
                    $this->load->helper('dropdown');
                    $subtotal = $bill; // Base rate (e.g., 5000)
                    $gst_rate = $gst_enabled ? get_gst_rate() : 0.0;
                    $gst_amount = $gst_enabled ? round(($bill * $gst_rate) / 100, 2) : 0;
                    $total_amount = $subtotal + $gst_amount; // Total = base + GST (e.g., 5900)

                    // For Monthly type, use generic name
                    // For Turnover type, use package name
                    if ($pkg_type == 'Monthly') {
                        $service_name = 'Account Work Monthly';
                    } else {
                        $service_name = $pkg['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                    }

                    // ── Create purchase row for Account Work ────────────────────────
                    $this->db->insert('purchases', [
                        'date'       => $today,
                        'year'       => $year_pkg,
                        'type'       => $pkg_type,
                        'user_id'    => $user['id'],
                        'service_id' => 1, // Account Work
                        'firm_id'    => $firm_id_pkg,
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
                        $this->response([
                            'status' => false,
                            'message' => 'No active services found in this package.'
                        ], RestController::HTTP_OK);
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

                    // ── Create a purchase row per service (deducts from wallet) ────
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
                            'year'       => $year_pkg,
                            'type'       => $pkg_type,
                            'user_id'    => $user['id'],
                            'service_id' => $svc['id'],
                            'firm_id'    => $firm_id_pkg,
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
                        'firm_id' => $firm_id_pkg,
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
                        $this->load->model('Service_model', 'service');
                        $acc_data = [
                            'user_id' => $user['id'],
                            'firm_id' => $firm_id_pkg,
                            'year' => $year_pkg,
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
                $this->load->model('Invoice_model', 'invoice');
                $customer  = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                $firm_info = $this->customer->getfirms(['t1.id' => $firm_id_pkg], 'single');
                $gst_on    = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                // Calculate GST - bill is base rate, GST is added on top
                $this->load->helper('dropdown');
                $subtotal  = $bill; // Base rate (e.g., 5000)
                $gst_rate = $gst_on ? get_gst_rate() : 0.0;
                $gst_amt   = $gst_on ? round(($bill * $gst_rate) / 100, 2) : 0;
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
                        'firm_id'        => $firm_id_pkg,
                        'year'           => $year_pkg,
                        'invoice_date'   => $today,
                        'billing_name'   => !empty($customer['name'])   ? $customer['name']   : $user['name'],
                        'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                        'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                        'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                        'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                        'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                        'service_name'   => implode(', ', $svc_names) . ($is_account_work ? ' (Account Work Renewal)' : ' (Package Renewal)'),
                        'type'           => $pkg_type,
                        'period_value'   => $year_pkg,
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

                $this->response([
                    'status' => true,
                    'message' => $msg,
                    'invoice_no' => $inv_no
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

    public function getservicefields_post()
    {
        $token = $this->post('token');
        $service_id = $this->post('service_id');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
                if (!empty($kyc)) {
                    $where = "t1.user_id='$user[id]' and t1.service_id='$service_id' and t1.status=0";
                    $service = $this->service->getpurchasedservices($where, 'single');
                    if (!empty($service)) {
                        $documents = $this->master->getservicedocuments(['t1.service_id' => $service_id]);
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
                            $type = !empty($service['purchased_type']) ? $service['purchased_type'] : '';
                            $this->response([
                                'status' => true,
                                'documents' => $finaldocuments,
                                'type' => $type
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Required Documents Not Added! Please Try Again Later!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Service Not Purchased!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "KYC Not Uploaded! Please Upload KYC before Submitting this Form!"
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

    public function getservicefieldsfororder_post()
    {
        $token = $this->post('token');
        $order_id = $this->post('order_id');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($order_id) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
                if (!empty($kyc)) {
                    $where = "t1.user_id='$user[id]' and t1.id='$order_id' and t1.firm_id='$firm_id'";
                    $order = $this->service->getpurchasedservices($where, 'single');
                    if (!empty($order)) {
                        $documents = $this->master->getservicedocuments(['t1.service_id' => $order['service_id']]);
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
                            $type = !empty($order['purchased_type']) ? $order['purchased_type'] : '';
                            $this->response([
                                'status' => true,
                                'documents' => $finaldocuments,
                                'type' => $type,
                                'order' => $order
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Required Documents Not Added! Please Try Again Later!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Order Not Found!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "KYC Not Uploaded! Please Upload KYC before Submitting this Form!"
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

    public function saveformdata_post()
    {
        $token = $this->post('token');
        $order_id = $this->post('order_id');
        $year = $this->post('year');
        $month = $this->post('month');
        $firm_id = $this->post('firm_id');
        $formdata = $this->post('formdata');
        $formdata = json_decode($formdata, true);
        if (!empty($token) && !empty($order_id) && !empty($firm_id) && ((!empty($formdata) && is_array($formdata)) || !empty($_FILES))) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $kyc = $this->account->getkyc(['t1.user_id' => $user['id']], 'single');
                if (!empty($kyc)) {
                    $where = "t1.user_id='$user[id]' and t1.id='$order_id' and t1.firm_id='$firm_id'";
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
                                    // Ensure these formats are always allowed for mobile uploads
                                    $allowed_types = 'pdf|jpg|jpeg|png|csv|xlsx';
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
                                    $this->response([
                                        'status' => true,
                                        'message' => $result['message']
                                    ], RestController::HTTP_OK);
                                } else {
                                    $this->response([
                                        'status' => false,
                                        'message' => $result['message']
                                    ], RestController::HTTP_OK);
                                }
                            } else {
                                $message = implode(',', $message);
                                $message = "You have not provided " . $message;
                                $this->response([
                                    'status' => false,
                                    'message' => $message,
                                    'documents' => $documents,
                                    'data' => $data,
                                    'newslug' => $newslug
                                ], RestController::HTTP_OK);
                            }
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Required Documents Not Added! Please Try Again Later!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Service Not Purchased!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "KYC Not Uploaded! Please Upload KYC before Submitting this Form!"
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

    public function formpreview_post()
    {
        $token = $this->post('token');
        $order_id = $this->post('order_id');
        $firm_id = $this->post('firm_id');
        if (!empty($token) && !empty($order_id) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = "t1.user_id='$user[id]' and t1.id='$order_id' and t1.firm_id='$firm_id'";
                $order = $this->service->getpurchasedservices($where, 'single');
                if (!empty($order)) {
                    if ($order['status'] == 0) {
                        $this->response([
                            'status' => false,
                            'message' => "Form not Submitted!"
                        ], RestController::HTTP_OK);
                    } else {
                        $data = $this->service->getuploadeddocuments(['a.order_id' => $order['id']]);
                        if (!empty($data)) {
                            foreach ($data as $key => $value) {
                                if (strpos($value['formvalue'], '/assets/') === 0) {
                                    $data[$key]['formvalue'] = file_url($value['formvalue']);
                                }
                            }
                            $this->response([
                                'status' => true,
                                'data' => $data
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Form not Submitted!"
                            ], RestController::HTTP_OK);
                        }
                        /*if(!empty($documents)){
                            $message=array();
                            $data=array();
                            $date=date('Y-m-d');
                            foreach($documents as $document){
                                $slug=$document['slug'];
                                $single=array();
                                if($document['value']==1 && isset($formdata[$slug])){
                                    $single[]=array('date'=>$date,'user_id'=>$user['id'],'order_id'=>$order['id'],
                                                  'service_id'=>$order['service_id'],'field'=>$slug,'field_id'=>$document['id'],
                                                 'value'=>$formdata[$slug]);
                                }
                                if($document['file']>0){
                                    $upload_path='./assets/service/documents/';
                                    $allowed_types=$document['file_type'];
                                    if($document['file']==1){
                                        $slug.='-file';
                                        if(isset($_FILES[$slug]['tmp_name'])){
                                            $upload=upload_file($slug,$upload_path,$allowed_types,$slug);
                                            if($upload['status']===true){
                                                $single[]=array('date'=>$date,'user_id'=>$user['id'],'order_id'=>$order['id'],
                                                  'service_id'=>$order['service_id'],'field'=>$slug,'field_id'=>$document['id'],
                                                 'value'=>$upload['path']);
                                            }
                                            else{
                                                $message[]=$document['display_name'].' File';
                                            }
                                        }
                                        else{
                                            $message[]=$document['display_name'].' File';
                                        }
                                    }
                                    elseif($document['file']==2){
                                        for($i=1;$i<=2;$i++){
                                            $newslug=$slug.'-file-'.$i;
                                            if(isset($_FILES[$newslug]['tmp_name'])){
                                                $upload=upload_file($newslug,$upload_path,$allowed_types,$newslug);
                                                if($upload['status']===true){
                                                    $single[]=array('date'=>$date,'user_id'=>$user['id'],'order_id'=>$order['id'],
                                                      'service_id'=>$order['service_id'],'field'=>$newslug,'field_id'=>$document['id'],
                                                     'value'=>$upload['path']);
                                                }
                                            }
                                            else{
                                                $message[]=$document['display_name'].' Image '.$i;
                                            }
                                        }
                                    }
                                }
                                if(empty($single) && $document['value']==1){
                                    $message[]=$document['display_name'];
                                }
                                else{
                                    $data=array_merge($data,$single);
                                }
                            }
                            $documents[]=$formdata;
                            if(!empty($data) && empty($message)){
                                $result=$this->service->saveformdata($data);
                                if($result['status']===true){
                                    $this->response([
                                        'status' => true,
                                        'message' => $result['message']], RestController::HTTP_OK);
                                }
                                else{
                                    $this->response([
                                        'status' => false,
                                        'message' => $result['message']], RestController::HTTP_OK);
                                }
                            }
                            else{
                                $message=implode(',',$message);
                                $message="You have not provided ".$message;
                                $this->response([
                                    'status' => false,
                                    'message' => $message,
                                    'documents' => $documents], RestController::HTTP_OK);
                            }
                        }
                        else{
                            $this->response([
                                'status' => false,
                                'message' => "Required Documents Not Added! Please Try Again Later!"], RestController::HTTP_OK);
                        }*/
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Service Not Purchased!"
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
}
