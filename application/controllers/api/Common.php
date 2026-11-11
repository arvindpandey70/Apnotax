<?php
defined('BASEPATH') or exit('No direct script access allowed');
//include Rest Controller library
use chriskacerguis\RestServer\RestController;

class Common extends RestController
{
    function __construct()
    {
        parent::__construct();
        logrequest();
    }

    public function getservices_post()
    {
        $token = $this->post('token');
        $firm_id = $this->post('firm_id'); // Optional: for purchase status check
        $year = $this->post('year'); // Optional: for purchase status check

        if (!empty($token)) {
            $verify = $this->account->verify_token($token);
            if ($verify !== false && is_array($verify) && $verify['role'] == 'customer') {
                $this->load->helper('common'); // Load common helper for checkservicepurchase

                $where = ['status' => 1];
                $services = $this->master->getservices($where);
                $this->master->ensure_default_service_options($services);

                if (!empty($services)) {
                    // Load service options for services that have dynamic options (like website)
                    foreach ($services as $key => $service) {
                        $service_options = $this->master->getserviceoptions(array('service_id' => $service['id'], 'status' => 1), 'all');
                        if (!empty($service_options)) {
                            $services[$key]['has_options'] = true;
                            $services[$key]['options'] = $service_options;
                        } else {
                            $services[$key]['has_options'] = false;
                        }

                        // Convert types and services_for to arrays
                        $services[$key]['types'] = explode(',', $service['type']);
                        $services[$key]['services_for'] = explode(',', $service['service_for']);

                        // Apply purchase check logic (same as website) if firm_id and year provided
                        // Website calls: checkservicepurchase($single, $user, $this->session->firm, $this->session->year)
                        if (!empty($firm_id) && !empty($year)) {
                            // Call checkservicepurchase with explicit year (matches website exactly)
                            // This checks:
                            // - Service ID 1: Package already selected
                            // - Once type: Already purchased
                            // - Yearly: Max 1 per year
                            // - Quarterly: Max 4 per year  
                            // - Monthly: Max 12 per year
                            $services[$key] = checkservicepurchase($services[$key], $verify, $firm_id, $year);

                            // Ensure buy_status is boolean (not undefined) - convert to boolean explicitly
                            $services[$key]['buy_status'] = isset($services[$key]['buy_status']) ? (bool)$services[$key]['buy_status'] : true;
                            $services[$key]['message'] = isset($services[$key]['message']) ? $services[$key]['message'] : '';
                        } else {
                            // If firm/year not provided, default to allowing purchase (for backward compatibility)
                            // But log warning so we know when this happens
                            $services[$key]['buy_status'] = true;
                            $services[$key]['message'] = '';
                            if (empty($firm_id) || empty($year)) {
                                log_message('debug', 'getservices_post: firm_id or year not provided. Service ID: ' . $services[$key]['id'] . ' - Purchase checks skipped.');
                            }
                        }
                    }

                    $this->response([
                        'status' => true,
                        'services' => $services
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => false,
                        'message' => "No Service Available!"
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

    public function getpackages_post()
    {
        $packages = array(['id' => 1, 'name' => 'Accountancy Prime'], ['id' => 2, 'name' => 'Accountancy Premium']);
        if (!empty($packages)) {
            $this->response([
                'status' => true,
                'packages' => $packages
            ], RestController::HTTP_OK);
        } else {
            $this->response([
                'status' => false,
                'message' => "No Packages Found!"
            ], RestController::HTTP_OK);
        }
    }

    public function getpackagedetails_post()
    {
        $package_id = $this->post('package_id');
        if (!empty($package_id) && ($package_id == 1 || $package_id == 2)) {
            $name = $package_id == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
            $package = $this->master->getpackages(['name' => $name]);
            if (!empty($package)) {
                $this->response([
                    'status' => true,
                    'details' => $package
                ], RestController::HTTP_OK);
            } else {
                $this->response([
                    'status' => false,
                    'message' => "No Packages Found!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'message' => "No Packages Found!"
            ], RestController::HTTP_OK);
        }
    }

    public function getstates_get()
    {
        $states = $this->common->getstates();
        if (!empty($states)) {
            $this->response([
                'status' => true,
                'response' => $states,
                'states' => $states
            ], RestController::HTTP_OK);
        } else {
            $this->response([
                'status' => false,
                'response' => [],
                'message' => "No States Found!"
            ], RestController::HTTP_OK);
        }
    }

    public function getstates_post()
    {
        $states = $this->common->getstates();
        if (!empty($states)) {
            $this->response([
                'status' => true,
                'response' => $states,
                'states' => $states
            ], RestController::HTTP_OK);
        } else {
            $this->response([
                'status' => false,
                'response' => [],
                'message' => "No States Found!"
            ], RestController::HTTP_OK);
        }
    }

    public function getdistricts_post()
    {
        $state_id = $this->post('state_id');
        if (!empty($state_id)) {
            $districts = $this->common->getdistricts($state_id);
            if (!empty($districts)) {
                $this->response([
                    'status' => true,
                    'response' => $districts,
                    'districts' => $districts
                ], RestController::HTTP_OK);
            } else {
                $this->response([
                    'status' => false,
                    'response' => [],
                    'message' => "No Districts Found!"
                ], RestController::HTTP_OK);
            }
        } else {
            $this->response([
                'status' => false,
                'response' => [],
                'message' => "Please provide State ID!"
            ], RestController::HTTP_OK);
        }
    }

    public function getyears_post()
    {
        try {
            // Ensure helper is loaded
            $this->load->helper('common');

            $years = getyearly();
            if ($years === false || $years === null) {
                $years = array();
            }
            $this->response([
                'status' => true,
                'years' => $years,
                'response' => $years
            ], RestController::HTTP_OK);
        } catch (Exception $e) {
            log_message('error', 'getyears_post error: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Error loading years: ' . $e->getMessage()
            ], RestController::HTTP_INTERNAL_ERROR);
        }
    }

    public function getquarters_post()
    {
        try {
            // Ensure helper is loaded
            $this->load->helper('common');

            $year = $this->post('year');
            $year = empty($year) ? date('Y') : $year;

            // Extract year from 8-digit format if needed (e.g., "20232024" -> "2023")
            if (strlen($year) == 8) {
                $year = substr($year, 0, 4);
            }

            $quarters = getquarterly($year);
            if ($quarters === false || $quarters === null) {
                $quarters = array();
            }
            $this->response([
                'status' => true,
                'quarters' => $quarters
            ], RestController::HTTP_OK);
        } catch (Exception $e) {
            log_message('error', 'getquarters_post error: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Error loading quarters: ' . $e->getMessage()
            ], RestController::HTTP_INTERNAL_ERROR);
        }
    }

    public function getmonths_post()
    {
        try {
            // Ensure helper is loaded
            $this->load->helper('common');

            $year = $this->post('year');
            $year = empty($year) ? date('Y') : $year;

            // Extract year from 8-digit format if needed (e.g., "20232024" -> "2023")
            if (strlen($year) == 8) {
                $year = substr($year, 0, 4);
            }

            $months = getmonths($year);
            if ($months === false || $months === null) {
                $months = array();
            }
            $this->response([
                'status' => true,
                'months' => $months
            ], RestController::HTTP_OK);
        } catch (Exception $e) {
            log_message('error', 'getmonths_post error: ' . $e->getMessage());
            $this->response([
                'status' => false,
                'message' => 'Error loading months: ' . $e->getMessage()
            ], RestController::HTTP_INTERNAL_ERROR);
        }
    }

    public function getnotifications_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user)) {
                // All non–user-deleted rows; user_status 0/1 = unread/read for this customer.
                $notifications = $this->common->getnotifications_for_end_user($user['id']);
                if (!empty($notifications)) {
                    $this->response([
                        'status' => true,
                        'notifications' => $notifications
                    ], RestController::HTTP_OK);
                } else {
                    $this->response([
                        'status' => true,
                        'notifications' => array(),
                        'message' => "No notifications available!"
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

    public function updatenotification_post()
    {
        $token = $this->post('token');
        $notification_id = $this->post('notification_id');
        $action = $this->post('action');
        $value = ($action === 'delete') ? 2 : 1;
        if (!empty($token) && !empty($notification_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user)) {
                $where = array('id' => $notification_id, 'user_id' => $user['id']);
                $data = array('user_status' => $value, 'updated_on' => date('Y-m-d H:i:s'));
                if ($this->db->update("notify", $data, $where)) {
                    $this->response([
                        'status' => true,
                        'message' => "Notification Updated Successfully!"
                    ], RestController::HTTP_OK);
                } else {
                    $error = $this->db->error();
                    $this->response([
                        'status' => false,
                        'message' => $error['message']
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
