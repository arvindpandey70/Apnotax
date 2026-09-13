<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        //logrequest();
        //checkcookie();
        if ($this->session->role != 'customer') {
            redirect('home/');
        }
    }

    public function index()
    {
        $data['title'] = "Profile";
        $user = getuser();
        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        if (empty($customer)) {
            redirect('customers/');
        }
        $data['customer'] = $customer;
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['states'] = state_dropdown();

        $options = district_dropdown($customer['parent_id']);
        $data['districts'] = $options;

        // Get user photo for display
        $data['user'] = $user;

        $data['form'] = 'update';
        $this->template->load('profile', 'profile', $data);
    }

    public function kyc()
    {
        $data['title'] = "KYC";
        $user = getuser();
        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        
        // Get firm_id from session if available
        $firm_id = !empty($this->session->firm) ? (int)$this->session->firm : null;
        
        // Build where clause - if firm_id provided, get firm-specific KYC, otherwise user-level
        $where = ['t1.user_id' => $user['id']];
        if (!empty($firm_id)) {
            $where['t1.firm_id'] = $firm_id;
        } else {
            // Get user-level KYC (where firm_id is NULL)
            $where['t1.firm_id IS NULL'] = null;
        }
        
        $data['kyc'] = $this->account->getkyc($where, 'single');
        $data['firm_id'] = $firm_id; // Pass firm_id to view for form submission
        $this->template->load('profile', 'kyc', $data);
    }

    public function certificates()
    {
        $data['title'] = "Certificates";
        $user = getuser();
        $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
        
        // Get firm_id from session if available
        $firm_id = !empty($this->session->firm) ? (int)$this->session->firm : null;
        
        // Build where clause - if firm_id provided, get firm-specific KYC, otherwise user-level
        $where = ['t1.user_id' => $user['id']];
        if (!empty($firm_id)) {
            $where['t1.firm_id'] = $firm_id;
        } else {
            // Get user-level KYC (general row)
            $where['t1.firm_id IS NULL'] = null;
        }
        
        $data['kyc'] = $this->account->getkyc($where, 'single');
        $this->template->load('profile', 'certificates', $data);
    }

    public function download_certificate($type = '')
    {
        $user = getuser();
        $firm_id = !empty($this->session->firm) ? (int)$this->session->firm : 0;
        $allowed_types = array('tds_certificate', 'gst_certificate', 'audit_report', 'income_tax_certificate');

        if (empty($type) || !in_array($type, $allowed_types)) {
            $this->session->set_flashdata("err_msg", "Invalid certificate type!");
            redirect('profile/certificates');
        }

        // Map logical type to actual DB column for backward compatibility
        if ($type === 'audit_report') {
            $db_column = 'company_registration_certificate as audit_report';
        } elseif ($type === 'income_tax_certificate') {
            $db_column = 'din_certificate as income_tax_certificate';
        } else {
            $db_column = $type;
        }

        // Get KYC data with raw file path (without file_url conversion)
        $this->db->select($db_column)->where('user_id', $user['id']);
        if ($firm_id > 0) {
            $this->db->where('firm_id', $firm_id);
        } else {
            $this->db->group_start();
            $this->db->where('firm_id', 0);
            $this->db->or_where('firm_id IS NULL', null, false);
            $this->db->group_end();
        }
        $kyc = $this->db->get('kyc')->row_array();

        if (empty($kyc) || empty($kyc[$type])) {
            $this->session->set_flashdata("err_msg", "Certificate not found!");
            redirect('profile/certificates');
        }

        $file_path = $kyc[$type];
        $full_path = FCPATH . $file_path;

        // Check if file exists
        if (!file_exists($full_path)) {
            $this->session->set_flashdata("err_msg", "Certificate file not found!");
            redirect('profile/certificates');
        }

        // Get filename from path
        $filename = basename($file_path);

        // Load download helper and force download
        $this->load->helper('download');
        force_download($full_path, NULL);
    }

    public function download_kyc_document($type = '')
    {
        $user = getuser();
        $allowed_types = array('pan_image', 'aadhar_image', 'aadhar_back');

        if (empty($type) || !in_array($type, $allowed_types)) {
            $this->session->set_flashdata("err_msg", "Invalid document type!");
            redirect('profile/kyc');
        }

        // Get KYC data with raw file path (without file_url conversion)
        $kyc = $this->db->select($type)->where('user_id', $user['id'])->get('kyc')->row_array();

        if (empty($kyc) || empty($kyc[$type])) {
            $this->session->set_flashdata("err_msg", "Document not found!");
            redirect('profile/kyc');
        }

        $file_path = $kyc[$type];
        $full_path = FCPATH . $file_path;

        // Check if file exists
        if (!file_exists($full_path)) {
            $this->session->set_flashdata("err_msg", "Document file not found!");
            redirect('profile/kyc');
        }

        // Load download helper and force download
        $this->load->helper('download');
        force_download($full_path, NULL);
    }

    public function bankstatement()
    {
        $data['title'] = "Monthly Bank Statement";
        $user = getuser();
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();

        $where = array("t1.user_id" => $user['id'], 't1.firm_id' => $this->session->firm, 't1.year' => $this->session->year);
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
        }
        $data['statements'] = $statements;
        $this->template->load('profile', 'bankstatement', $data);
    }

    public function updateprofile()
    {
        if ($this->input->post('updateprofile') !== NULL) {
            $data = $this->input->post();
            unset($data['updateprofile']);
            $user = getuser();
            
            // Handle photo upload
            if (!empty($_FILES['photo']) && isset($_FILES['photo']['tmp_name']) && !empty($_FILES['photo']['tmp_name'])) {
                $upload_path = './assets/images/profile/';
                $allowed_types = 'gif|jpg|jpeg|png|svg';
                $upload = upload_file('photo', $upload_path, $allowed_types, generate_slug($user['name'] . '-profile'));
                if ($upload['status'] === true) {
                    $path = $upload['path'];
                    
                    // Try to process image if GD extension is available
                    if (extension_loaded('gd')) {
                        try {
                            $this->load->library('imager');
                            $path = $this->imager->processimage(upload_disk_path($upload['path']), 'cropscale', 80, ['width' => 300, 'height' => 300]);
                            $path = upload_path_for_db($path);
                        } catch (Exception $e) {
                            $path = $upload['path'];
                        }
                    }
                    
                    $photo_data = array('photo' => $path);
                    $where = array('id' => $user['id']);
                    $photo_result = $this->account->updatephoto($photo_data, $where);
                    if ($photo_result['status'] === false) {
                        $this->session->set_flashdata("err_msg", "Profile updated but photo upload failed: " . $photo_result['message']);
                    }
                } else {
                    $this->session->set_flashdata("err_msg", "Photo upload failed: " . $upload['msg']);
                }
            }
            
            //print_pre($data,true);
            $result = $this->customer->updatecustomer($data);
            if ($result['status'] === true) {
                $this->session->set_flashdata("msg", "Profile Updated Successfully!");
            } else {
                $this->session->set_flashdata("err_msg", $result['message']);
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function updatekyc()
    {
        if ($this->input->post('updatekyc') !== NULL) {
            $data = $this->input->post();
            $user = getuser();
            $firm_id = !empty($data['firm_id']) ? (int)$data['firm_id'] : null;
            
            // PAN is optional - validate format only if provided
            $pan = !empty($data['pan']) ? trim($data['pan']) : '';
            if ($pan !== '') {
                $checkpan = preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $pan);
                if (!$checkpan) {
                    $this->session->set_flashdata("err_msg", "Enter Valid PAN!");
                    redirect($_SERVER['HTTP_REFERER']);
                    return;
                }
            }
            
            // Aadhar is optional - validate format only if provided
            $aadhar = !empty($data['aadhar']) ? trim($data['aadhar']) : '';
            if ($aadhar !== '') {
                $checkaadhar = preg_match('/[0-9]{12}$/', $aadhar);
                if (!$checkaadhar) {
                    $this->session->set_flashdata("err_msg", "Enter Valid Aadhar No!");
                    redirect($_SERVER['HTTP_REFERER']);
                    return;
                }
            }
            
            $kyc_data = array(
                "user_id" => $user['id'],
                "pan" => $pan,
                "aadhar" => $aadhar,
                // Customer-submitted KYC must be approved by admin.
                "status" => 0
            );
            
            // Add firm_id if provided
            if (!empty($firm_id)) {
                $kyc_data['firm_id'] = $firm_id;
            }
            
            $status = true;
            $message = array();
            $upload_path = './assets/images/profile/kyc/';
            $allowed_types = 'gif|jpg|jpeg|png|svg';
            
            // PAN image (optional - upload only if provided)
            if (!empty($_FILES['pan_image']['name'])) {
                $upload = upload_file('pan_image', $upload_path, $allowed_types, generate_slug($user['name'] . '-pan-' . ($firm_id ? $firm_id : 'user')));
                if ($upload['status'] === true) {
                    $kyc_data['pan_image'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "PAN- " . trim($upload['msg']);
                }
            }
            
            // Aadhar images (optional - upload only if provided)
            if (!empty($_FILES['aadhar_image']['name'])) {
                $upload = upload_file('aadhar_image', $upload_path, $allowed_types, generate_slug($user['name'] . '-aadhar-' . ($firm_id ? $firm_id : 'user')));
                if ($upload['status'] === true) {
                    $kyc_data['aadhar_image'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "Aadhar Front- " . trim($upload['msg']);
                }
            }
            
            if (!empty($_FILES['aadhar_back']['name'])) {
                $upload = upload_file('aadhar_back', $upload_path, $allowed_types, generate_slug($user['name'] . '-aadhar-back-' . ($firm_id ? $firm_id : 'user')));
                if ($upload['status'] === true) {
                    $kyc_data['aadhar_back'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "Aadhar Back- " . trim($upload['msg']);
                }
            }

            if ($status) {
                $result = $this->account->savekyc($kyc_data);
                if ($result['status'] === true) {
                    $this->common->notify_admins_kyc_pending_submission($user['id'], $firm_id);
                    $this->session->set_flashdata("msg", "KYC submitted successfully and is pending admin approval.");
                } else {
                    $this->session->set_flashdata("err_msg", $result['message']);
                }
            } else {
                $message = implode('; ', $message);
                $this->session->set_flashdata("err_msg", $message);
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function savebankstatement()
    {
        if ($this->input->post('savebankstatement') !== NULL) {
            $data = $this->input->post();
            unset($data['savebankstatement']);
            $user = getuser();

            $where = array("t1.user_id" => $user['id'], 't1.id' => $this->session->firm);
            $firm = $this->customer->getfirms($where, 'single');
            if (!empty($firm)) {
                $status = checkaccountancy($user, $firm['id']);
                if ($status) {
                    $months = getmonths($this->session->year);
                    if (!empty($months)) {
                        $month_ids = array_column($months, 'id');
                        $index = array_search($data['month'], $month_ids);
                        if ($index !== false) {
                            $month_name = $months[$index]['value'];
                            $where = array('user_id' => $user['id'], 'firm_id' => $firm['id'], 'year' => $this->session->year, 'month' => $data['month']);
                            $check = $this->db->get_where('bank_statements', $where)->num_rows();
                            if ($check == 0) {
                                $data = $where;
                                $upload_path = './assets/documents/statements/';
                                $allowed_types = 'pdf';
                                $upload = upload_file('statement', $upload_path, $allowed_types, generate_slug($user['name'] . '-bank-statement-' . $data['month']));

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
                                                    generate_slug($user['name'] . '-creditors-statement-' . $data['month'] . '-' . ($i + 1))
                                                );

                                                if ($upload['status'] === true) {
                                                    // Save to bank_creditors_statements table
                                                    $creditors_data = array(
                                                        'bank_statement_id' => $bank_statement_id,
                                                        'user_id' => $user['id'],
                                                        'firm_id' => $firm['id'],
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
                                                $this->session->set_flashdata("msg", $msg);
                                            } else if (!empty($creditors_upload_errors)) {
                                                $this->session->set_flashdata("err_msg", "Bank statement saved but creditors statements failed: " . implode('; ', $creditors_upload_errors));
                                            } else {
                                                $this->session->set_flashdata("msg", $result['message']);
                                            }
                                        } else {
                                            // No creditors statements uploaded, but bank statement is saved
                                            $this->session->set_flashdata("msg", $result['message']);
                                        }
                                    } else {
                                        $this->session->set_flashdata("err_msg", $result['message']);
                                    }
                                } else {
                                    $this->session->set_flashdata("err_msg", $upload['msg']);
                                }
                            } else {
                                $this->session->set_flashdata("err_msg", "Statement Already uploaded for " . $month_name);
                            }
                        } else {
                            $this->session->set_flashdata("err_msg", "Select Valid Month!");
                        }
                    } else {
                        $this->session->set_flashdata("err_msg", "Select Valid Year!");
                    }
                } else {
                    $this->session->set_flashdata("err_msg", "Accountancy Service not Active!");
                }
            } else {
                $this->session->set_flashdata("err_msg", "Firm not found!");
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function olddata()
    {
        $data['title'] = "Old Data";
        $user = getuser();
        $data['breadcrumb'] = array("active" => "Old Data");
        $data['datatable'] = true;

        // Get old datas for this customer
        $where = array('t1.user_id' => $user['id'], 't1.status' => 1);
        $data['old_data'] = $this->customer->getoldclientdata($where);

        $this->template->load('profile', 'olddata', $data);
    }

    public function downloadolddata($id = NULL)
    {
        if ($id === NULL) {
            redirect('profile/olddata');
        }
        $user = getuser();
        $old_data = $this->customer->getoldclientdata(['md5(t1.id)' => $id, 't1.user_id' => $user['id']], 'single');
        if (empty($old_data)) {
            $this->session->set_flashdata("err_msg", "Data not found!");
            redirect('profile/olddata');
        }

        $file_path = FCPATH . $old_data['file_path'];
        if (file_exists($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $old_data['file_name'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $this->session->set_flashdata("err_msg", "File not found!");
            redirect('profile/olddata');
        }
    }
}
