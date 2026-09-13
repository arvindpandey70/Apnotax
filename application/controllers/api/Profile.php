<?php
defined('BASEPATH') or exit('No direct script access allowed');
//include Rest Controller library
use chriskacerguis\RestServer\RestController;

class Profile extends RestController
{
    function __construct()
    {
        parent::__construct();
        logrequest();
    }

    public function getprofile_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $firstLetter = !empty($user['name']) ? strtoupper(substr($user['name'], 0, 1)) : 'T';
                $photo = !empty($user['photo']) ? file_url($user['photo']) : base_url('profileimage/?letter=' . $firstLetter);

                // Get customer details
                $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                $address = $this->account->getaddress(['t1.user_id' => $user['id']], 'single');

                $result = array(
                    'name' => $user['name'],
                    'mobile' => $user['mobile'],
                    'email' => $user['email'],
                    'photo' => $photo,
                    'customer' => $customer,
                    'address' => $address
                );
                $this->response([
                    'status' => true,
                    'profile' => $result
                ], RestController::HTTP_OK);
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function updateprofile_post()
    {
        $token = $this->post('token');
        $name = $this->post('name');
        $mobile = $this->post('mobile');
        $email = $this->post('email');

        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array();

                // Handle photo upload (optional)
                if (!empty($_FILES['photo']) && isset($_FILES['photo']['tmp_name']) && !empty($_FILES['photo']['tmp_name'])) {
                    $upload_path = './assets/images/profile/';
                    $allowed_types = 'gif|jpg|jpeg|png|svg';
                    $upload = upload_file('photo', $upload_path, $allowed_types, generate_slug($user['name']));
                    if ($upload['status'] === true) {
                        try {
                            $this->load->library('imager');
                            $path = $this->imager->processimage(upload_disk_path($upload['path']), 'cropscale', 80, ['width' => 300, 'height' => 300]);
                            $data['photo'] = upload_path_for_db($path);
                        } catch (Exception $e) {
                            $data['photo'] = $upload['path'];
                        }
                    }
                }

                if (!empty($name)) {
                    $data['name'] = $name;
                }
                if (!empty($email)) {
                    $data['email'] = $email;
                }
                if (!empty($mobile)) {
                    $data['mobile'] = $mobile;
                }
                if (!empty($data)) {
                    $result = $this->account->updateuser($data, array("id" => $user['id']));
                    if ($result['status'] === true) {
                        $data['name'] = isset($data['name']) ? $data['name'] : $user['name'];
                        $data['email'] = isset($data['email']) ? $data['email'] : $user['email'];
                        $data['mobile'] = isset($data['mobile']) ? $data['mobile'] : $user['mobile'];
                        $data['photo'] = isset($data['photo']) ? file_url($data['photo']) : file_url($user['photo']);
                        $this->response([
                            'status' => true,
                            'message' => $result['message'],
                            'profile' => $data
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => $result['message']
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Please Try Again!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function saveaddress_post()
    {
        $token = $this->post('token');
        $address = $this->post('address');
        $state_id = $this->post('state_id');
        $district_id = $this->post('district_id');
        $pincode = $this->post('pincode');

        if (!empty($token) && !empty($address) && !empty($state_id) && !empty($district_id) && !empty($pincode)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array(
                    "user_id" => $user['id'],
                    "address" => $address,
                    "parent_id" => $state_id,
                    "area_id" => $district_id,
                    "pincode" => $pincode
                );
                $getstate = $this->db->get_where("area", array("id" => $data['parent_id'], 'type' => 'State'));
                $getdistrict = $this->db->get_where("area", array("id" => $data['area_id'], "parent_id" => $data['parent_id'], 'type' => 'District'));
                if ($getdistrict->num_rows() == 1 && $getstate->num_rows() == 1) {
                    $data['state'] = $getstate->unbuffered_row()->name;
                    $data['district'] = $getdistrict->unbuffered_row()->name;

                    //print_pre($data,true);
                    $result = $this->account->saveaddress($data);
                    if ($result['status'] === true) {
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
                    $message = $getstate->num_rows() == 0 ? 'State' : 'District';
                    $message .= " not found!";
                    $this->response([
                        'status' => false,
                        'message' => $message
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getaddress_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $address = $this->account->getaddress(['t1.user_id' => $user['id']], 'single');
                if (!empty($address)) {
                    $this->response([
                        'status' => true,
                        'address' => $address
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Address not added!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function savekyc_post()
    {
        $token = $this->post('token');
        $pan = $this->post('pan');
        $aadhar = $this->post('aadhar');
        $firm_id = $this->post('firm_id'); // Optional firm_id for per-firm KYC

        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Validate PAN (optional - validate format only if provided)
                if (!empty($pan)) {
                    $checkpan = preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $pan);
                    if (!$checkpan) {
                        $this->response([
                            'status' => false,
                            'message' => "Enter Valid PAN!"
                        ], RestController::HTTP_OK);
                        return;
                    }
                }

                // Aadhar is optional - validate only if provided
                if (!empty($aadhar)) {
                    $checkaadhar = preg_match('/[0-9]{12}$/', $aadhar);
                    if (!$checkaadhar) {
                        $this->response([
                            'status' => false,
                            'message' => "Enter Valid Aadhar No!"
                        ], RestController::HTTP_OK);
                        return;
                    }
                }

                $data = array(
                    "user_id" => $user['id'],
                    "pan" => !empty($pan) ? $pan : '',
                    "aadhar" => !empty($aadhar) ? $aadhar : '',
                    // Match website flow: every customer KYC submission goes for admin approval.
                    "status" => 0
                );

                // Add firm_id if provided
                if (!empty($firm_id)) {
                    $data['firm_id'] = (int)$firm_id;
                }

                $status = true;
                $message = array();
                $upload_path = './assets/images/profile/kyc/';
                $allowed_types = 'gif|jpg|jpeg|png|svg';

                // PAN image (optional - only upload if provided)
                if (!empty($_FILES['pan_image']['name'])) {
                    $upload = upload_file('pan_image', $upload_path, $allowed_types, generate_slug($user['name'] . '-pan-' . ($firm_id ? $firm_id : 'user')));
                    if ($upload['status'] === true) {
                        $data['pan_image'] = $upload['path'];
                    } else {
                        $status = false;
                        $message[] = "PAN- " . trim($upload['msg']);
                    }
                }

                // Aadhar images (optional - only upload if provided)
                if (!empty($_FILES['aadhar_image']['name'])) {
                    $upload = upload_file('aadhar_image', $upload_path, $allowed_types, generate_slug($user['name'] . '-aadhar-' . ($firm_id ? $firm_id : 'user')));
                    if ($upload['status'] === true) {
                        $data['aadhar_image'] = $upload['path'];
                    } else {
                        $status = false;
                        $message[] = "Aadhar Front- " . trim($upload['msg']);
                    }
                }

                if (!empty($_FILES['aadhar_back']['name'])) {
                    $upload = upload_file('aadhar_back', $upload_path, $allowed_types, generate_slug($user['name'] . '-aadhar-back-' . ($firm_id ? $firm_id : 'user')));
                    if ($upload['status'] === true) {
                        $data['aadhar_back'] = $upload['path'];
                    } else {
                        $status = false;
                        $message[] = "Aadhar Back- " . trim($upload['msg']);
                    }
                }

                if ($status) {
                    $result = $this->account->savekyc($data);
                    if ($result['status'] === true) {
                        $this->common->savenotification(array(
                            'user_id' => (int) $user['id'],
                            'type' => 'kyc',
                            'message' => 'Your KYC documents were submitted successfully. Verification may take some time.',
                        ));
                        $this->common->notify_admins_kyc_pending_submission((int) $user['id'], !empty($firm_id) ? (int) $firm_id : null);
                        $this->response([
                            'status' => true,
                            'message' => 'KYC submitted successfully and is pending admin approval.'
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => $result['message']
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $message = implode('; ', $message);
                    $this->response([
                        'status' => false,
                        'message' => $message
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Token is required!"
            ], RestController::HTTP_OK);
        }
    }

    public function getkyc_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id'); // Optional firm_id for per-firm KYC
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Build where clause - if firm_id provided, get firm-specific KYC, otherwise user-level
                $where = ['t1.user_id' => $user['id']];
                if (!empty($firm_id)) {
                    $where['t1.firm_id'] = (int)$firm_id;
                } else {
                    // Get user-level KYC (where firm_id is NULL)
                    $where['t1.firm_id IS NULL'] = null;
                }
                $kyc = $this->account->getkyc($where, 'single');
                if (!empty($kyc)) {
                    $this->response([
                        'status' => true,
                        'kyc' => $kyc
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "KYC not Uploaded!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "User not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function addfirm_post()
    {
        $token = $this->post('token');
        $name = trim($this->post('name'));
        $gstin = trim($this->post('gstin'));
        $id = $this->post('id'); // For update operations

        // Validate required fields
        if (empty($token)) {
            $this->response([
                'status' => false,
                'message' => "Token is required!"
            ], RestController::HTTP_OK);
            return;
        }

        if (empty($name)) {
            $this->response([
                'status' => false,
                'message' => "Firm name is required!"
            ], RestController::HTTP_OK);
            return;
        }

        if (!empty($token) && !empty($name)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Check if this is an update operation
                if (!empty($id)) {
                    // Verify that the firm belongs to the user
                    $where = array("t1.id" => $id, "t1.user_id" => $user['id']);
                    $existingFirm = $this->customer->getfirms($where, 'single');
                    if (empty($existingFirm)) {
                        $this->response([
                            'status' => false,
                            'message' => "Firm not found or unauthorized!"
                        ], RestController::HTTP_OK);
                        return;
                    }

                    // Update firm
                    $data = array("id" => $id, "name" => $name, "gstin" => $gstin);
                    $result = $this->customer->updatefirm($data);
                    if ($result['status'] === true) {
                        // Get the updated firm
                        $where = array("t1.id" => $id, "t1.user_id" => $user['id']);
                        $firm = $this->customer->getfirms($where, 'single');
                        $this->response([
                            'status' => true,
                            'message' => $result['message'],
                            'response' => $firm
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => $result['message']
                        ], RestController::HTTP_OK);
                    }
                } else {
                    // Add new firm
                    $data = array("user_id" => $user['id'], "name" => $name, "gstin" => $gstin);
                    $result = $this->customer->addfirm($data);
                    if ($result['status'] === true) {
                        // Get the newly created firm to return it using the firm_id from result
                        $firm_id = isset($result['firm_id']) ? $result['firm_id'] : null;
                        if ($firm_id) {
                            $where = array("t1.id" => $firm_id, "t1.user_id" => $user['id']);
                        } else {
                            // Fallback: get by name and user_id (most recent)
                            $where = array("t1.user_id" => $user['id'], "t1.name" => $name, "t1.request" => 0);
                        }
                        $firm = $this->customer->getfirms($where, 'single');

                        $this->response([
                            'status' => true,
                            'message' => $result['message'],
                            'response' => $firm
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => $result['message']
                        ], RestController::HTTP_OK);
                    }
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details! (Token and Firm Name are required)"
            ], RestController::HTTP_OK);
        }
    }

    public function myfirms_post()
    {
        $token = $this->post('token');

        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.status' => 1, 't1.request!=' => 1);
                $firms = $this->customer->getfirms($data);
                if (!empty($firms)) {
                    // Add can_request_delete: false if firm has package or purchases (matches web checkfirmservice)
                    foreach ($firms as &$firm) {
                        $firm['can_request_delete'] = !checkfirmservice($user, $firm['id']);
                    }
                    unset($firm);
                    $this->response([
                        'status' => true,
                        'response' => $firms,
                        'message' => 'Firms retrieved successfully'
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'response' => [],
                        'message' => "No Firm Added!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'response' => [],
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'response' => [],
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function deletefirm_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');

        if (!empty($token) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    if (checkfirmservice($user, $firm['id'])) {
                        $this->response([
                            'status' => false,
                            'message' => "Cannot request deletion - firm has active package or purchases!"
                        ], RestController::HTTP_OK);
                        return;
                    }
                    if ($firm['request'] == 0) {
                        $this->db->update('firms', ['request' => 1], ['id' => $firm['id']]);
                        $this->response([
                            'status' => true,
                            'message' => "Firm delete request saved successfully!"
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Delete Request already saved!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Firm not Added!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function createpackage_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $service_ids = $this->post('service_ids');
        $service_option_ids = $this->post('service_option_ids'); // JSON format: {"service_id": ["option_id1", "option_id2"], ...}
        $year = $this->post('year');
        if (!empty($token) && !empty($service_ids) && !empty($year) && !empty($firm_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    // Prerequisite: Account Work package must be active before creating service package
                    $account_work_pkg = $this->db->get_where('customer_packages', [
                        'user_id' => $user['id'],
                        'firm_id' => $firm_id,
                        'year'    => $year,
                        'status'  => 1
                    ])->unbuffered_row('array');
                    if (empty($account_work_pkg)) {
                        $this->response([
                            'status' => false,
                            'message' => 'Please activate Account Work package first. Then you can create Service Package.'
                        ], RestController::HTTP_OK);
                        return;
                    }

                    $s_ids = explode(',', $service_ids);
                    $where = "status='1' and id in ('" . implode("','", $s_ids) . "')";
                    $services = $this->master->getservices($where);
                    if (!empty($services)) {
                        // Process service options - convert to JSON if provided
                        $service_option_ids_json = NULL;
                        $service_option_ids_map = array();
                        if (!empty($service_option_ids)) {
                            // If it's already a JSON string, validate it; otherwise try to decode
                            if (is_string($service_option_ids)) {
                                $decoded = json_decode($service_option_ids, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $service_option_ids_json = $service_option_ids;
                                    $service_option_ids_map = $decoded;
                                } else {
                                    // If not valid JSON, try to parse as array
                                    $service_option_ids_json = json_encode($service_option_ids);
                                    $service_option_ids_map = is_array($service_option_ids) ? $service_option_ids : array();
                                }
                            } else {
                                $service_option_ids_json = json_encode($service_option_ids);
                                $service_option_ids_map = is_array($service_option_ids) ? $service_option_ids : array();
                            }
                        }

                        // ── Determine package_type from services ─────────────────────────────
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
                        $package_type = !empty($type_groups) ? array_key_first($type_groups) : 'Yearly';

                        // ── Validate same-type constraint ──────────────────────────────────
                        if (count($type_groups) > 1) {
                            $detail = '';
                            foreach ($type_groups as $t => $names) {
                                $detail .= $t . ': ' . implode(', ', $names) . '; ';
                            }
                            $this->response([
                                'status' => false,
                                'message' => 'A package can only contain services of the same billing type. Found mixed types – ' . rtrim($detail, '; ')
                            ], RestController::HTTP_OK);
                            return;
                        }

                        // ── Check if any service is already in a DIFFERENT type package ────
                        $conflict = array();
                        foreach ($s_ids as $sid) {
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
                            $this->response([
                                'status' => false,
                                'message' => 'Some services are already in another package type: ' . implode(', ', $conflict)
                            ], RestController::HTTP_OK);
                            return;
                        }

                        // ── Check if any service was already purchased directly ────────────
                        $this->load->model('Service_model', 'service');
                        $conflict_direct = array();
                        foreach ($s_ids as $sid) {
                            $existing_purchase = $this->service->getpurchases(
                                "t1.user_id='{$user['id']}' AND t1.firm_id='{$firm_id}' AND t1.year='{$year}' AND t1.service_id='{$sid}'"
                            );
                            if (!empty($existing_purchase)) {
                                $svc = $this->master->getservices(['id' => $sid], 'single');
                                $conflict_direct[] = !empty($svc['name']) ? $svc['name'] : "Service #$sid";
                            }
                        }
                        if (!empty($conflict_direct)) {
                            $this->response([
                                'status' => false,
                                'message' => 'The following service(s) are already purchased directly and cannot be added to a package: ' .
                                    implode(', ', $conflict_direct)
                            ], RestController::HTTP_OK);
                            return;
                        }

                        // ── Calculate bill amount ────────────────────────────────────────────
                        $subtotal = 0.0;
                        foreach ($services as $svc) {
                            $rate = isset($svc['rate']) ? (float)$svc['rate'] : 0.0;
                            // If service has options and an option was chosen, use option rate
                            if (!empty($service_option_ids_map[$svc['id']])) {
                                $opt = $this->master->getserviceoptions(['id' => $service_option_ids_map[$svc['id']], 'status' => 1], 'single');
                                if (!empty($opt['rate'])) $rate = (float)$opt['rate'];
                            }
                            $subtotal += $rate;
                        }
                        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                        $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;
                        $this->load->helper('dropdown');
                        $gst_rate = $gst_enabled ? get_gst_rate() : 0.0;
                        $gst_amount = $gst_enabled ? round($subtotal * $gst_rate / 100, 2) : 0.0;
                        $total = $subtotal + $gst_amount;

                        // ── Calculate expiry date ────────────────────────────────────────────
                        $purchase_date = date('Y-m-d');
                        $pts = strtotime($purchase_date);
                        $expiry_date = null;

                        switch ($package_type) {
                            case 'Monthly':
                                $expiry_date = date('Y-m-d', strtotime('+1 month', $pts));
                                break;
                            case 'Quarterly':
                                $expiry_date = date('Y-m-d', strtotime('+3 months', $pts));
                                break;
                            case 'Once':
                                $expiry_date = date('Y-m-d', strtotime('+1 year', $pts));
                                break;
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
                                $expiry_date = $earliest !== null ? $earliest : date('Y-m-d', strtotime('+1 year', $pts));
                                break;
                        }

                        // ── Prepare package data ────────────────────────────────────────────
                        $data = array(
                            'user_id' => $user['id'],
                            'firm_id' => $firm_id,
                            'year' => $year,
                            'service_ids' => $service_ids,
                            'service_option_ids' => $service_option_ids_json,
                            'package_type' => $package_type,
                            'purchase_date' => $purchase_date,
                            'expiry_date' => $expiry_date,
                            'payment_status' => 0,   // bill generated, not yet deducted
                            'bill_amount' => $total,
                        );
                        $result = $this->customer->createpackage($data);
                        if ($result['status'] === true) {
                            // No invoice at purchase time — invoice is only generated when the
                            // package expires and gets renewed (auto or manual).
                            // Use already calculated values
                            $message = $result['message'] . ' | ₹' . number_format($total, 2) .
                                ' will be billed when the package expires';
                            if ($expiry_date) {
                                $message .= ' on ' . date('d-m-Y', strtotime($expiry_date));
                            }
                            $message .= '.';

                            $this->response([
                                'status'     => true,
                                'message'    => $message,
                                'bill_amount' => $total,
                                'expiry_date' => $expiry_date,
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => $result['message']
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Service not Available"
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
                    'message' => "User not Logged In!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function myservicepackage_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && !empty($firm_id) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $data = array("t1.user_id" => $user['id'], 't1.firm_id' => $firm_id, 't1.year' => $year);
                    $package = $this->customer->getservicepackage($data, 'single');
                    if (!empty($package)) {
                        $this->response([
                            'status' => true,
                            'package' => $package
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Package not Created!"
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
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getservicepackages_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id'); // Optional: filter by firm
        $year = $this->post('year'); // Optional: filter by year

        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array('t1.user_id' => $user['id']);
                if (!empty($firm_id)) {
                    $where['t1.firm_id'] = $firm_id;
                }
                if (!empty($year)) {
                    $where['t1.year'] = $year;
                }

                $packages = $this->customer->getservicepackage($where, 'all');

                if (!empty($packages)) {
                    // Get all services for resolving service names
                    $all_services = $this->master->getservices(['status' => 1]);
                    $services_map = array();
                    foreach ($all_services as $svc) {
                        $services_map[$svc['id']] = $svc;
                    }

                    // Enrich packages with service details
                    foreach ($packages as &$pkg) {
                        $service_ids = !empty($pkg['service_ids']) ? array_filter(array_map('trim', explode(',', $pkg['service_ids']))) : [];
                        $pkg_services = array();
                        $opt_ids_map = array();

                        if (!empty($pkg['service_option_ids'])) {
                            $opt_ids_map = json_decode($pkg['service_option_ids'], true) ?: array();
                        }

                        foreach ($service_ids as $sid) {
                            if (isset($services_map[$sid])) {
                                $svc = $services_map[$sid];
                                $svc_data = array(
                                    'id' => $svc['id'],
                                    'name' => $svc['name'],
                                    'rate' => $svc['rate'],
                                    'service_for' => $svc['service_for'] ?? '',
                                    'debit_date' => $svc['debit_date'] ?? '',
                                    'type' => $svc['type'] ?? '',
                                );

                                // Add option details if exists
                                if (!empty($opt_ids_map[$sid])) {
                                    $opt = $this->master->getserviceoptions(['id' => $opt_ids_map[$sid], 'status' => 1], 'single');
                                    if (!empty($opt)) {
                                        $svc_data['option_id'] = $opt['id'];
                                        $svc_data['option_name'] = $opt['display_name'];
                                        $svc_data['option_rate'] = $opt['rate'];
                                        $svc_data['rate'] = $opt['rate']; // Use option rate
                                    }
                                }

                                $pkg_services[] = $svc_data;
                            }
                        }

                        $pkg['services'] = $pkg_services;

                        // Calculate days until expiry
                        $expiry_ts = !empty($pkg['expiry_date']) ? strtotime($pkg['expiry_date']) : 0;
                        $is_expired = $expiry_ts && $expiry_ts <= time();
                        $pkg['is_expired'] = $is_expired;
                        if ($expiry_ts) {
                            $days = (int)ceil(($expiry_ts - time()) / 86400);
                            $pkg['days_until_expiry'] = $days;
                            $pkg['days_text'] = $days > 0
                                ? "Expires in $days day" . ($days > 1 ? 's' : '')
                                : abs($days) . ' day' . (abs($days) != 1 ? 's' : '') . ' overdue';
                        }
                    }

                    $this->response([
                        'status' => true,
                        'packages' => $packages
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => true,
                        'packages' => array(),
                        'message' => 'No packages found'
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function requestpackagedelete_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');
        $package_id = (int)$this->post('package_id');

        if (!empty($token) && (!empty($package_id) || (!empty($firm_id) && !empty($year)))) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Check if request column exists
                $check_column = $this->db->query("SHOW COLUMNS FROM `tf_service_packages` LIKE 'request'");
                if ($check_column->num_rows() == 0) {
                    $this->response([
                        'status' => false,
                        'message' => "Delete request feature is not available. Please contact administrator."
                    ], RestController::HTTP_OK);
                    return;
                }

                // Support deleting a specific package_id via POST, or fall back to first for firm/year
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
                    // Check if request field exists in the result
                    if (!isset($service_package['request'])) {
                        $service_package['request'] = 0;
                    }
                    // Allow request if no request (0) or if rejected (2) - can re-request after rejection
                    if ($service_package['request'] == 0 || $service_package['request'] == 2) {
                        if ($this->db->update('service_packages', ['request' => 1], ['id' => $service_package['id']])) {
                            $message = $service_package['request'] == 2 ? "Package Delete Request Resubmitted! Admin will review your request." : "Package Delete Request Saved! Admin will review your request.";
                            $this->response([
                                'status' => true,
                                'message' => $message
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Failed to save delete request!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Delete Request already submitted!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Package not found!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function requestdeleteaccountwork_post()
    {
        $token = $this->post('token');
        $package_id = (int)$this->post('package_id');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && (!empty($package_id) || (!empty($firm_id) && !empty($year)))) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Check if request column exists
                $check_column = $this->db->query("SHOW COLUMNS FROM `tf_customer_packages` LIKE 'request'");
                if ($check_column->num_rows() == 0) {
                    $this->response([
                        'status' => false,
                        'message' => "Delete request feature is not available. Please contact administrator."
                    ], RestController::HTTP_OK);
                    return;
                }

                // Support deleting a specific package_id via POST, or fall back to first for firm/year
                if ($package_id > 0) {
                    $account_work_package = $this->db->get_where(
                        'customer_packages',
                        ['id' => $package_id, 'user_id' => $user['id'], 'status' => 1]
                    )->unbuffered_row('array');
                } else {
                    $account_work_package = $this->db->get_where(
                        'customer_packages',
                        ['user_id' => $user['id'], 'firm_id' => $firm_id, 'year' => $year, 'status' => 1]
                    )->unbuffered_row('array');
                }

                if (!empty($account_work_package)) {
                    // Check if request field exists in the result
                    if (!isset($account_work_package['request'])) {
                        $account_work_package['request'] = 0;
                    }
                    // Allow request if no request (0) or if rejected (2) - can re-request after rejection
                    if ($account_work_package['request'] == 0 || $account_work_package['request'] == 2) {
                        if ($this->db->update('customer_packages', ['request' => 1], ['id' => $account_work_package['id']])) {
                            $message = $account_work_package['request'] == 2 ? "Account Work Package Delete Request Resubmitted! Admin will review your request." : "Account Work Package Delete Request Saved! Admin will review your request.";
                            $this->response([
                                'status' => true,
                                'message' => $message
                            ], RestController::HTTP_OK);
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Failed to save delete request!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Delete Request already submitted!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Account Work Package not found!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function checkaccountancy_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && !empty($firm_id) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $data = array("t1.user_id" => $user['id']);
                    $status = checkaccountancy($user, $firm_id);
                    $this->response([
                        'status' => true,
                        'accountancy' => $status
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Firm not Selected!"
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
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
     * Trigger auto-renewal for expired packages
     * Checks wallet balance and auto-renews if sufficient, otherwise leaves for manual renewal
     */
    public function triggerautorenew_post()
    {
        $token = $this->post('token');

        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                try {
                    // Load required models
                    $this->load->model('Wallet_model', 'wallet');
                    $this->load->model('Master_model', 'master');
                    $this->load->model('Customer_model', 'customer');
                    $this->load->model('Invoice_model', 'invoice');

                    $today = date('Y-m-d');
                    $renewed_packages = [];
                    $renewed_account_work = [];
                    $insufficient_balance = [];

                    // ── Auto-renew expired service packages ─────────────────────────
                    $expired = $this->db->query(
                        "SELECT sp.*, u.name AS user_name
                         FROM {$this->db->dbprefix('service_packages')} sp
                         JOIN {$this->db->dbprefix('users')} u ON u.id = sp.user_id
                         WHERE sp.expiry_date <= '{$today}'
                           AND sp.payment_status = 0
                           AND sp.user_id = {$user['id']}"
                    )->result_array();

                    foreach ($expired as $pkg) {
                        try {
                            $user_id    = $pkg['user_id'];
                            $firm_id    = $pkg['firm_id'];
                            $year       = $pkg['year'];
                            $bill       = (float)($pkg['bill_amount'] ?? 0);
                            $pkg_type   = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Yearly';

                            if ($bill <= 0) continue;

                            // ── Resolve services in the package ────────────────────────────
                            $s_ids = array_filter(array_map('trim', explode(',', $pkg['service_ids'] ?? '')));
                            if (empty($s_ids)) continue;

                            $services = $this->master->getservices(
                                "status='1' AND id IN ('" . implode("','", $s_ids) . "')"
                            );
                            if (empty($services)) continue;

                            // ── Check customer GST setting ──────────────────────────────────
                            $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                            // For service packages, bill_amount is already total (subtotal + GST included)
                            // So use bill_amount directly as total_amount
                            $total_amount = $bill;

                            // Check wallet balance against total amount
                            $balance = $this->wallet->getwalletbalance($user_id);
                            if ($balance < $total_amount) {
                                // Not enough balance — leave for manual renewal
                                $insufficient_balance[] = [
                                    'type' => 'service_package',
                                    'package_id' => $pkg['id'],
                                    'bill_amount' => $bill, // Total amount (already includes GST)
                                    'total_amount' => $total_amount,
                                    'wallet_balance' => $balance,
                                    'needed' => $total_amount - $balance
                                ];
                                continue;
                            }

                            // ── Create a purchase row per service (deducts from wallet) ────
                            $opt_data = [];
                            if (!empty($pkg['service_option_ids'])) {
                                $opt_data = json_decode($pkg['service_option_ids'], true) ?: [];
                            }
                            $datetime = date('Y-m-d H:i:s');

                            // Calculate total base rate (sum of all service rates) for GST distribution
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

                            // Calculate GST amounts if enabled
                            $total_gst = 0;
                            if ($gst_enabled && $total_base_rate > 0) {
                                // bill_amount is total (subtotal + GST), extract subtotal and GST
                                $this->load->helper('dropdown');
                                $gst_rate = get_gst_rate();
                                $gst_divisor = 1 + ($gst_rate / 100); // e.g., 1.18 for 18% GST
                                $subtotal_from_bill = round($bill / $gst_divisor, 2);
                                $total_gst = round($bill - $subtotal_from_bill, 2);
                            }

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
                                    'type'       => $pkg_type,
                                    'user_id'    => $user_id,
                                    'service_id' => $svc['id'],
                                    'firm_id'    => $firm_id,
                                    'service'    => $svc['name'] . ' (Package Auto-Renew)',
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

                            // ── Calculate new expiry ───────────────────────────────────────
                            $new_expiry = $this->_nextExpiry($pkg_type, $today);

                            // ── Update expiry (keep payment_status=0 so next expiry triggers renewal again)
                            $this->db->update('service_packages', [
                                'payment_status' => 0,
                                'purchase_date'  => $today,
                                'expiry_date'    => $new_expiry,
                                'updated_on'     => $datetime,
                            ], ['id' => $pkg['id']]);

                            // ── Generate invoice ───────────────────────────────────────────
                            $customer  = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                            $firm_info = $this->customer->getfirms(['t1.id' => $firm_id], 'single');
                            $gst_on    = !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                            // Calculate GST - bill is base rate, GST is added on top
                            $this->load->helper('dropdown');
                            $gst_rate = $gst_on ? get_gst_rate() : 0.0;
                            $subtotal  = $bill; // Base rate
                            $gst_amt   = $gst_on ? round(($bill * $gst_rate) / 100, 2) : 0;
                            $total_amt = $subtotal + $gst_amt;

                            $svc_names = array_column($services, 'name');
                            $invoice_id = null;
                            $invoice_no = null;
                            try {
                                $invoice_result = $this->invoice->create_custom_invoice([
                                    'user_id'        => $user_id,
                                    'firm_id'        => $firm_id,
                                    'year'           => $year,
                                    'invoice_date'   => $today,
                                    'billing_name'   => !empty($customer['name'])   ? $customer['name']   : ($pkg['user_name'] ?? ''),
                                    'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                                    'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                                    'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                                    'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                                    'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                                    'service_name'   => implode(', ', $svc_names) . ' (Package Renewal)',
                                    'type'           => $pkg_type,
                                    'period_value'   => $year,
                                    'subtotal'       => $subtotal,
                                    'gst_rate'       => $gst_rate,
                                    'gst_amount'     => $gst_amt,
                                    'total_amount'   => $total_amt,
                                ]);
                                if (!empty($invoice_result['status']) && !empty($invoice_result['invoice'])) {
                                    $invoice_id = !empty($invoice_result['invoice']['id']) ? $invoice_result['invoice']['id'] : null;
                                    $invoice_no = !empty($invoice_result['invoice']['invoice_no']) ? $invoice_result['invoice']['invoice_no'] : null;
                                }
                            } catch (Exception $e) {
                                log_message('error', 'Package auto-renewal invoice error: ' . $e->getMessage());
                            }

                            $renewed_packages[] = [
                                'package_id' => $pkg['id'],
                                'package_type' => $pkg_type,
                                'bill_amount' => $bill,
                                'new_expiry_date' => $new_expiry,
                                'invoice_id' => $invoice_id,
                                'invoice_no' => $invoice_no,
                            ];
                        } catch (Exception $e) {
                            // Log error but continue processing other packages
                            log_message('error', 'Auto-renewal error for package #' . ($pkg['id'] ?? 'unknown') . ': ' . $e->getMessage());
                            // Add to insufficient balance so user knows something went wrong
                            $insufficient_balance[] = [
                                'type' => 'service_package',
                                'package_id' => $pkg['id'] ?? 0,
                                'bill_amount' => isset($bill) ? $bill : 0,
                                'wallet_balance' => 0,
                                'needed' => 0,
                                'error' => 'Failed to process auto-renewal'
                            ];
                        }
                    }

                    // ── Auto-renew expired Account Work packages ───────────────────
                    $expired_account_work = $this->db->query(
                        "SELECT cp.*, u.name AS user_name, s.debit_date AS service_debit_date
                     FROM {$this->db->dbprefix('customer_packages')} cp
                     JOIN {$this->db->dbprefix('users')} u ON u.id = cp.user_id
                     LEFT JOIN {$this->db->dbprefix('services')} s ON s.id = 1
                     WHERE cp.expiry_date <= '{$today}'
                       AND cp.payment_status = 0
                       AND cp.status = 1
                       AND cp.user_id = {$user['id']}"
                    )->result_array();

                    foreach ($expired_account_work as $pkg) {
                        try {
                            $user_id    = $pkg['user_id'];
                            $firm_id    = $pkg['firm_id'];
                            $year       = $pkg['year'];
                            $pkg_type   = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Turnover';

                            // Always use service rate (₹5,000) for Account Work packages
                            if ($pkg_type == 'Turnover') {
                                $account_work_service = $this->master->getservices(['id' => 1, 'status' => 1], 'single');
                                $bill = !empty($account_work_service['rate']) ? (float)$account_work_service['rate'] : 5000;
                            } else {
                                $bill = (float)($pkg['bill_amount'] ?? $pkg['amount'] ?? 0);
                            }

                            if ($bill <= 0) continue;

                            // ── Check customer GST setting first to calculate total amount ──────────────────────────────────
                            $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                            $gst_enabled = !empty($customer) && !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                            // Calculate total amount with GST (what user will actually pay)
                            $this->load->helper('dropdown');
                            $gst_rate = $gst_enabled ? get_gst_rate() : 0.0;
                            $subtotal = $bill;
                            $gst_amount = $gst_enabled ? round($bill * $gst_rate / 100, 2) : 0;
                            $total_amount = $subtotal + $gst_amount;

                            // Check wallet balance against total amount (with GST)
                            $balance = $this->wallet->getwalletbalance($user_id);
                            if ($balance < $total_amount) {
                                $insufficient_balance[] = [
                                    'type' => 'account_work',
                                    'package_id' => $pkg['id'],
                                    'bill_amount' => $bill, // Base amount
                                    'gst_amount' => $gst_amount, // GST amount
                                    'total_amount' => $total_amount, // Total with GST (what user needs)
                                    'wallet_balance' => $balance,
                                    'needed' => $total_amount - $balance
                                ];
                                continue;
                            }

                            // ── Create purchase row ────────────────────────────────────────
                            $datetime = date('Y-m-d H:i:s');
                            $subtotal = $bill;
                            $this->load->helper('dropdown');
                            $gst_rate = $gst_enabled ? get_gst_rate() : 0.0;
                            $gst_amount = $gst_enabled ? round($bill * $gst_rate / 100, 2) : 0;
                            $total_amount = $subtotal + $gst_amount;

                            $this->db->insert('purchases', [
                                'date'       => $today,
                                'year'       => $year,
                                'type'       => $pkg_type,
                                'user_id'    => $user_id,
                                'service_id' => 1,
                                'firm_id'    => $firm_id,
                                'service'    => 'Account Work (Auto-Renew)',
                                'rate'       => $subtotal,
                                'subtotal'   => $subtotal,
                                'gst_amount' => $gst_amount,
                                'gst_enabled' => $gst_enabled ? 1 : 0,
                                'amount'     => $total_amount,
                                'status'     => 0,
                                'added_on'   => $datetime,
                                'updated_on' => $datetime,
                            ]);

                            // ── Calculate new expiry ───────────────────────────────────────
                            $new_expiry = $this->_nextExpiryAccountWork($pkg_type, $today, $pkg['service_debit_date'] ?? null);

                            // ── Update expiry ────────────────────────────────────────────
                            $this->db->update('customer_packages', [
                                'payment_status' => 0,
                                'purchase_date'  => $today,
                                'expiry_date'    => $new_expiry,
                                'updated_on'     => $datetime,
                            ], ['id' => $pkg['id']]);

                            // ── For Monthly type, record monthly amount in accountancy other_fee ───────────────────────────────────────────
                            if ($pkg_type == 'Monthly') {
                                // Get current month's first day (e.g., 2024-04-01)
                                $current_month_start = date('Y-m-01');

                                // Check if accountancy record exists for this month
                                $acc_record = $this->db->get_where('accountancy', [
                                    'user_id' => $user_id,
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
                                    $this->load->model('Service_model', 'service');
                                    $acc_data = [
                                        'user_id' => $user_id,
                                        'firm_id' => $firm_id,
                                        'year' => $year,
                                        'date' => $current_month_start,
                                        'turnover' => 0,
                                        'other_fee' => $subtotal, // Use base rate (without GST)
                                        'due_date' => date('Y-m-d', strtotime('+1 month', strtotime($current_month_start))), // Due date is next month
                                        'added_by' => $user_id,
                                        'status' => 1,
                                        'added_on' => $datetime,
                                        'updated_on' => $datetime
                                    ];
                                    $this->service->saveturnover($acc_data);
                                }
                            }

                            // ── Generate invoice ───────────────────────────────────────────
                            $customer  = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
                            $firm_info = $this->customer->getfirms(['t1.id' => $firm_id], 'single');
                            $gst_on    = !empty($customer['gst_enabled']) && $customer['gst_enabled'] == 1;

                            $this->load->helper('dropdown');
                            $subtotal  = $bill;
                            $gst_rate = $gst_on ? get_gst_rate() : 0.0;
                            $gst_amt   = $gst_on ? round(($bill * $gst_rate) / 100, 2) : 0;
                            $total_amt = $subtotal + $gst_amt;

                            $invoice_id = null;
                            $invoice_no = null;
                            try {
                                $invoice_result = $this->invoice->create_custom_invoice([
                                    'user_id'        => $user_id,
                                    'firm_id'        => $firm_id,
                                    'year'           => $year,
                                    'invoice_date'   => $today,
                                    'billing_name'   => !empty($customer['name'])   ? $customer['name']   : ($pkg['user_name'] ?? ''),
                                    'billing_email'  => !empty($customer['email'])  ? $customer['email']  : '',
                                    'billing_mobile' => !empty($customer['mobile']) ? $customer['mobile'] : '',
                                    'firm_name'      => !empty($firm_info['name'])  ? $firm_info['name']  : '',
                                    'firm_gstin'     => !empty($firm_info['gstin']) ? $firm_info['gstin'] : '',
                                    'firm_pan'       => !empty($firm_info['pan'])   ? $firm_info['pan']   : '',
                                    'service_name'   => 'Account Work (Auto-Renew)',
                                    'type'           => $pkg_type,
                                    'period_value'   => $year,
                                    'subtotal'       => $subtotal,
                                    'gst_rate'       => $gst_rate,
                                    'gst_amount'     => $gst_amt,
                                    'total_amount'   => $total_amt,
                                ]);
                                if (!empty($invoice_result['status']) && !empty($invoice_result['invoice'])) {
                                    $invoice_id = !empty($invoice_result['invoice']['id']) ? $invoice_result['invoice']['id'] : null;
                                    $invoice_no = !empty($invoice_result['invoice']['invoice_no']) ? $invoice_result['invoice']['invoice_no'] : null;
                                }
                            } catch (Exception $e) {
                                log_message('error', 'Account Work auto-renewal invoice error: ' . $e->getMessage());
                            }

                            $renewed_account_work[] = [
                                'package_id' => $pkg['id'],
                                'package_type' => $pkg_type,
                                'bill_amount' => $bill,
                                'new_expiry_date' => $new_expiry,
                                'invoice_id' => $invoice_id,
                                'invoice_no' => $invoice_no,
                            ];
                        } catch (Exception $e) {
                            // Log error but continue processing other packages
                            log_message('error', 'Account Work auto-renewal error for package #' . ($pkg['id'] ?? 'unknown') . ': ' . $e->getMessage());
                            // Add to insufficient balance so user knows something went wrong
                            $insufficient_balance[] = [
                                'type' => 'account_work',
                                'package_id' => $pkg['id'] ?? 0,
                                'bill_amount' => isset($bill) ? $bill : 0,
                                'wallet_balance' => 0,
                                'needed' => 0,
                                'error' => 'Failed to process auto-renewal'
                            ];
                        }
                    }

                    $this->response([
                        'status' => true,
                        'renewed_packages' => $renewed_packages,
                        'renewed_account_work' => $renewed_account_work,
                        'insufficient_balance' => $insufficient_balance,
                        'message' => count($renewed_packages) > 0 || count($renewed_account_work) > 0
                            ? (count($renewed_packages) + count($renewed_account_work)) . ' package(s) auto-renewed successfully.'
                            : (count($insufficient_balance) > 0
                                ? 'Some packages could not be auto-renewed due to insufficient wallet balance. Please add funds to your wallet.'
                                : 'No expired packages found.')
                    ], RestController::HTTP_OK);
                } catch (Exception $e) {
                    // Catch any unexpected errors and return a safe response
                    log_message('error', 'Auto-renewal API error: ' . $e->getMessage());
                    $this->response([
                        'status' => true,
                        'renewed_packages' => [],
                        'renewed_account_work' => [],
                        'insufficient_balance' => [],
                        'message' => 'Auto-renewal check completed. Some packages may require manual renewal.'
                    ], RestController::HTTP_OK);
                }
            } else {
                $this->response([
                    'status' => false,
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide token!"
            ], RestController::HTTP_OK);
        }
    }

    /**
     * Calculate the next expiry date for Account Work packages
     */
    private function _nextExpiryAccountWork($package_type, $from_date, $debit_date = null)
    {
        $ts = strtotime($from_date);
        switch ($package_type) {
            case 'Monthly':
                return date('Y-m-d', strtotime('+1 month', $ts));
            case 'Turnover':
            case 'Yearly':
            default:
                if (!empty($debit_date)) {
                    $dm = (int)date('m', strtotime($debit_date));
                    $dd = (int)date('d', strtotime($debit_date));
                    $cy = (int)date('Y', $ts);

                    $candidate = sprintf('%04d-%02d-%02d', $cy, $dm, $dd);
                    if (strtotime($candidate) <= $ts) {
                        $candidate = sprintf('%04d-%02d-%02d', $cy + 1, $dm, $dd);
                    }
                    return $candidate;
                }
                return date('Y-m-d', strtotime('+1 year', $ts));
        }
    }

    /**
     * Calculate the next expiry date from a given start date.
     */
    private function _nextExpiry($package_type, $from_date)
    {
        $ts = strtotime($from_date);
        switch ($package_type) {
            case 'Monthly':
                return date('Y-m-d', strtotime('+1 month', $ts));
            case 'Quarterly':
                return date('Y-m-d', strtotime('+3 months', $ts));
            case 'Once':
                return date('Y-m-d', strtotime('+1 year', $ts));
            case 'Yearly':
            default:
                return date('Y-m-d', strtotime('+1 year', $ts));
        }
    }

    public function savemonthlystatement_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');
        $month = $this->post('month');

        if (!empty($token) && !empty($firm_id) && !empty($year) && !empty($month)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $data = array("t1.user_id" => $user['id']);
                    $status = checkaccountancy($user, $firm_id);
                    if ($status) {
                        $months = getmonths($year);
                        if (!empty($months)) {
                            $month_ids = array_column($months, 'id');
                            $index = array_search($month, $month_ids);
                            if ($index !== false) {
                                $month_name = $months[$index]['value'];
                                $where = array('user_id' => $user['id'], 'firm_id' => $firm_id, 'year' => $year, 'month' => $month);
                                $check = $this->db->get_where('bank_statements', $where)->num_rows();
                                if ($check == 0) {
                                    $data = $where;
                                    $upload_path = './assets/documents/statements/';
                                    $allowed_types = 'pdf';
                                    $upload = upload_file('statement', $upload_path, $allowed_types, generate_slug($user['name'] . '-bank-statement-' . $month));
                                    if ($upload['status'] === true) {
                                        $data['statement'] = $upload['path'];
                                        $data['uploaded_by'] = $user['id'];

                                        // Save bank statement first to get the ID
                                        $result = $this->service->savebankstatement($data);

                                        if ($result['status'] === true) {
                                            $bank_statement_id = $result['bank_statement_id'];

                                            // Handle multiple creditors statement uploads
                                            $creditors_upload_errors = array();
                                            $creditors_upload_success = 0;

                                            if (!empty($_FILES['creditors_statement']['name'][0])) {
                                                $file_count = count($_FILES['creditors_statement']['name']);

                                                for ($i = 0; $i < $file_count; $i++) {
                                                    // Create a temporary single file array for upload_file function
                                                    $_FILES['creditors_statement_single'] = array(
                                                        'name' => $_FILES['creditors_statement']['name'][$i],
                                                        'type' => $_FILES['creditors_statement']['type'][$i],
                                                        'tmp_name' => $_FILES['creditors_statement']['tmp_name'][$i],
                                                        'error' => $_FILES['creditors_statement']['error'][$i],
                                                        'size' => $_FILES['creditors_statement']['size'][$i]
                                                    );

                                                    $upload = upload_file(
                                                        'creditors_statement_single',
                                                        $upload_path,
                                                        $allowed_types,
                                                        generate_slug($user['name'] . '-creditors-statement-' . $month . '-' . ($i + 1))
                                                    );

                                                    if ($upload['status'] === true) {
                                                        // Save to bank_creditors_statements table
                                                        $creditors_data = array(
                                                            'bank_statement_id' => $bank_statement_id,
                                                            'user_id' => $user['id'],
                                                            'firm_id' => $firm_id,
                                                            'file_path' => $upload['path'],
                                                            'file_name' => $_FILES['creditors_statement']['name'][$i],
                                                            'uploaded_by' => $user['id'],
                                                            'added_on' => date('Y-m-d H:i:s'),
                                                            'updated_on' => date('Y-m-d H:i:s')
                                                        );

                                                        if ($this->db->insert('bank_creditors_statements', $creditors_data)) {
                                                            $creditors_upload_success++;
                                                        } else {
                                                            $creditors_upload_errors[] = 'Failed to save creditors statement file: ' . $_FILES['creditors_statement']['name'][$i];
                                                        }
                                                    } else {
                                                        $creditors_upload_errors[] = 'Creditors Statement ' . ($i + 1) . ': ' . $upload['msg'];
                                                    }

                                                    // Clean up temporary file array
                                                    unset($_FILES['creditors_statement_single']);
                                                }

                                                // Set success/error messages
                                                if ($creditors_upload_success > 0) {
                                                    $msg = $result['message'];
                                                    if ($creditors_upload_success > 1) {
                                                        $msg .= " " . $creditors_upload_success . " creditors statements uploaded successfully.";
                                                    } else {
                                                        $msg .= " Creditors statement uploaded successfully.";
                                                    }
                                                    if (!empty($creditors_upload_errors)) {
                                                        $msg .= " Errors: " . implode('; ', $creditors_upload_errors);
                                                    }
                                                    $this->response([
                                                        'status' => true,
                                                        'message' => $msg
                                                    ], RestController::HTTP_OK);
                                                } else if (!empty($creditors_upload_errors)) {
                                                    $this->response([
                                                        'status' => true,
                                                        'message' => $result['message'] . " Creditors statements failed: " . implode('; ', $creditors_upload_errors)
                                                    ], RestController::HTTP_OK);
                                                } else {
                                                    $this->response([
                                                        'status' => true,
                                                        'message' => $result['message']
                                                    ], RestController::HTTP_OK);
                                                }
                                            } else {
                                                // No creditors statements uploaded, but bank statement is saved
                                                $this->response([
                                                    'status' => true,
                                                    'message' => $result['message']
                                                ], RestController::HTTP_OK);
                                            }
                                        } else {
                                            $this->response([
                                                'status' => false,
                                                'message' => $result['message']
                                            ], RestController::HTTP_OK);
                                        }
                                    } else {
                                        $this->response([
                                            'status' => false,
                                            'message' => $upload['msg']
                                        ], RestController::HTTP_OK);
                                    }
                                } else {
                                    $this->response([
                                        'status' => false,
                                        'message' => "Statement Already uploaded for " . $month_name
                                    ], RestController::HTTP_OK);
                                }
                            } else {
                                $this->response([
                                    'status' => false,
                                    'message' => "Select Valid Month!"
                                ], RestController::HTTP_OK);
                            }
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "Select Valid Year!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Accountancy Service not Active"
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
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getbankstatements_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id');
        $year = $this->post('year');

        if (!empty($token) && !empty($firm_id) && !empty($year)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $data = array("t1.user_id" => $user['id'], 't1.id' => $firm_id);
                $firm = $this->customer->getfirms($data, 'single');
                if (!empty($firm)) {
                    $where = array("t1.user_id" => $user['id'], 't1.firm_id' => $firm_id);
                    $statements = $this->service->getbankstatements($where, 'all', 't1.month desc');

                    if (!empty($statements)) {
                        foreach ($statements as $key => $single) {
                            $year = getyearmonthvalues($single['year']);
                            $month = getyearmonthvalues($single['month']);
                            $statements[$key]['year_value'] = $year['value'];
                            $statements[$key]['month_value'] = $month['value'];
                            $statements[$key]['statement'] = file_url($single['statement']);

                            // Get multiple creditors statements for this bank statement
                            $creditors_statements = $this->db->get_where('bank_creditors_statements', array('bank_statement_id' => $single['id']))->result_array();
                            $statements[$key]['creditors_statements'] = array();
                            if (!empty($creditors_statements)) {
                                foreach ($creditors_statements as $cred) {
                                    $statements[$key]['creditors_statements'][] = array(
                                        'id' => $cred['id'],
                                        'file_path' => file_url($cred['file_path']),
                                        'file_name' => $cred['file_name']
                                    );
                                }
                            }

                            // Keep backward compatibility with old single creditors_statement field
                            if (!empty($single['creditors_statement'])) {
                                $statements[$key]['creditors_statement'] = file_url($single['creditors_statement']);
                            }
                        }
                        $this->response([
                            'status' => true,
                            'response' => $statements
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Bank Statements not Uploaded!"
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
                    'message' => "Unauthorized Access!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "Please provide all Details!"
            ], RestController::HTTP_OK);
        }
    }

    public function getcertificates_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id'); // Optional firm_id for firm-specific certificates
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Build where clause to match web behavior:
                // with firm_id => firm-specific KYC, without => user-level KYC.
                $where = ['t1.user_id' => $user['id']];
                if (!empty($firm_id)) {
                    $where['t1.firm_id'] = (int)$firm_id;
                } else {
                    $where['t1.firm_id IS NULL'] = null;
                }

                $kyc = $this->account->getkyc($where, 'single');
                if (!empty($kyc)) {
                    $certificates = array();
                    $allowed_types = array('tds_certificate', 'gst_certificate', 'audit_report', 'income_tax_certificate');
                    foreach ($allowed_types as $type) {
                        if (!empty($kyc[$type])) {
                            // NOTE: Account_model::getkyc() already prefixes file paths with base_url().
                            // In some cases we may receive an already-absolute URL, so we must not wrap it again
                            // (double base_url() can produce invalid URLs which often lead to 403).
                            $rawPathOrUrl = $kyc[$type];
                            $downloadUrl = $rawPathOrUrl;
                            if (!empty($rawPathOrUrl) && !preg_match('/^https?:\/\//i', $rawPathOrUrl)) {
                                $downloadUrl = file_url($rawPathOrUrl);
                            }
                            log_message(
                                'error',
                                'getcertificates download_url: type=' . $type .
                                ' raw=' . substr((string)$rawPathOrUrl, 0, 200) .
                                ' -> final=' . substr((string)$downloadUrl, 0, 200)
                            );
                            $certificates[] = array(
                                'type' => $type,
                                'name' => ucfirst(str_replace('_', ' ', $type)),
                                'download_url' => $downloadUrl,
                                'file_name' => basename($kyc[$type])
                            );
                        }
                    }
                    $this->response([
                        'status' => true,
                        'certificates' => $certificates,
                        'response' => $certificates
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "KYC not Uploaded!"
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

    public function getolddata_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array('t1.user_id' => $user['id'], 't1.status' => 1);
                $old_data = $this->customer->getoldclientdata($where);
                if (!empty($old_data)) {
                    foreach ($old_data as $key => $data) {
                        $old_data[$key]['download_url'] = file_url($data['file_path']);
                    }
                    $this->response([
                        'status' => true,
                        'old_data' => $old_data
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Old Data Available!"
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

    public function savesessdata_post()
    {
        $token = $this->post('token');
        $year = trim($this->post('year'));
        $firm = trim($this->post('firm'));

        // Validate required fields with specific error messages
        if (empty($token)) {
            $this->response([
                'status' => false,
                'message' => "Token is required!"
            ], RestController::HTTP_OK);
            return;
        }

        if (empty($year)) {
            $this->response([
                'status' => false,
                'message' => "Year is required!"
            ], RestController::HTTP_OK);
            return;
        }

        if (empty($firm)) {
            $this->response([
                'status' => false,
                'message' => "Firm is required!"
            ], RestController::HTTP_OK);
            return;
        }

        if (!empty($token) && !empty($year) && !empty($firm)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array("t1.user_id" => $user['id'], 't1.status' => 1, 't1.request!=' => 1, 't1.id' => $firm);
                $firmData = $this->customer->getfirms($where, 'single');
                if (!empty($firmData)) {
                    // For mobile app, we return the selected year and firm data
                    // The app will store it locally
                    $this->response([
                        'status' => true,
                        'message' => 'Session data saved successfully',
                        'year' => $year,
                        'firm_id' => $firmData['id'],
                        'firm' => $firmData
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "Firm not found or not authorized!"
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
                'message' => "Please provide all Details (token, year, firm)!"
            ], RestController::HTTP_OK);
        }
    }
}
