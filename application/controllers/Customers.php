<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Customers extends CI_Controller
{
    var $multiplier = 1;


    function __construct()
    {
        parent::__construct();
        checklogin();
    }

    /** Staff roles that see the full customer list (no added_by filter). */
    private function staff_sees_all_customers()
    {
        $role = (string) $this->session->role;
        $isCa = ($role === 'ca') || preg_match('/^ca[-_]/i', $role) === 1;
        return in_array($role, array('admin', 'superadmin', 'employee'), true) || $isCa;
    }

    public function index()
    {
        $data['title'] = "Customers";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        if (!$this->staff_sees_all_customers()) {
            $where['md5(t1.added_by)'] = $this->session->user;
        }
        $data['customers'] = $this->customer->getcustomers($where);
        $this->template->load('customer', 'customers', $data);
    }

    public function bulkgsttoggle()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'status' => false,
                'message' => 'Unauthorized access!'
            ]));
            return;
        }

        $enable = $this->input->post('enable');
        $enable = ($enable == 1 || $enable === '1') ? 1 : 0;

        $result = $this->customer->bulkupdategst($enable);

        $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }

    public function addcustomer()
    {
        $data['title'] = "Add Customer";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['states'] = state_dropdown();

        $options = array('' => 'Select District');
        $data['districts'] = $options;



        $data['form'] = 'add';

        $this->template->load('customer', 'customerform', $data);
    }

    public function editcustomer($id = NULL)
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            redirect('customers/');
        }
        $data['customer'] = $customer;
        $data['title'] = "Edit customer";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['states'] = state_dropdown();

        $options = district_dropdown($customer['parent_id']);
        $data['districts'] = $options;


        $data['form'] = 'update';

        $this->template->load('customer', 'customerform', $data);
    }


    public function kycdetails($id = NULL)
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            redirect('customers/');
        }
        $data['customer'] = $customer;
        $data['title'] = "Customer KYC Details";

        // Fetch all KYC records for this user across all firms
        $all_kyc_raw = $this->account->getkyc(['t1.user_id' => $customer['user_id']], 'all');

        // Fetch all active firms for this user so we can attach firm names
        $firms = $this->customer->getfirms(['t1.user_id' => $customer['user_id'], 't1.status' => 1]);
        $firm_map = array();
        if (!empty($firms)) {
            foreach ($firms as $firm) {
                $firm_map[$firm['id']] = $firm['name'];
            }
        }

        // Attach firm_name to each KYC record
        $all_kyc = array();
        if (!empty($all_kyc_raw)) {
            foreach ($all_kyc_raw as $kyc_row) {
                if (!empty($kyc_row['firm_id']) && isset($firm_map[$kyc_row['firm_id']])) {
                    $kyc_row['firm_name'] = $firm_map[$kyc_row['firm_id']];
                } elseif (!empty($kyc_row['firm_id'])) {
                    $kyc_row['firm_name'] = 'Firm #' . $kyc_row['firm_id'];
                } else {
                    $kyc_row['firm_name'] = 'General';
                }
                $all_kyc[] = $kyc_row;
            }
        }

        $selected_firm_id = (int)$this->input->get('firm_id');
        $selected_kyc = array();
        if (!empty($all_kyc)) {
            foreach ($all_kyc as $kyc_row) {
                if ((int)$kyc_row['firm_id'] === $selected_firm_id) {
                    $selected_kyc = $kyc_row;
                    break;
                }
            }
        }

        $data['all_kyc']          = $all_kyc;
        $data['selected_firm_id'] = $selected_firm_id;
        // Certificates section should use selected firm KYC when provided.
        $data['kyc']              = !empty($selected_kyc) ? $selected_kyc : (!empty($all_kyc) ? $all_kyc[0] : array());
        $data['form']     = 'update';

        $this->template->load('customer', 'kycdetails', $data);
    }

    public function approvekyc($id = NULL)
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            $this->session->set_flashdata("err_msg", "Customer not found!");
            redirect('customers/');
            return;
        }
        $firm_id = (int)$this->input->get('firm_id');
        $this->db->where('user_id', $customer['user_id']);
        if ($firm_id > 0) {
            $this->db->where('firm_id', $firm_id);
        } else {
            $this->db->group_start();
            $this->db->where('firm_id', 0);
            $this->db->or_where('firm_id IS NULL', null, false);
            $this->db->group_end();
        }
        $ok = $this->db->update('kyc', ['status' => 1, 'updated_on' => date('Y-m-d H:i:s')]);
        $this->session->set_flashdata($ok ? "msg" : "err_msg", $ok ? "KYC approved successfully." : "Failed to approve KYC.");
        redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
    }

    public function deletekyc($id = NULL)
    {
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            $this->session->set_flashdata("err_msg", "Customer not found!");
            redirect('customers/');
            return;
        }
        $firm_id = (int)$this->input->get('firm_id');

        // Safety rule: don't allow KYC deletion if purchases already exist for this customer+firm.
        if ($firm_id > 0) {
            $purchase_count = $this->db
                ->where('user_id', $customer['user_id'])
                ->where('firm_id', $firm_id)
                ->count_all_results('purchases');
            if ($purchase_count > 0) {
                $this->session->set_flashdata("err_msg", "KYC cannot be deleted because purchases already exist for this firm.");
                redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
                return;
            }
        } else {
            // For general/non-firm row, block delete if customer has any purchases.
            $purchase_count = $this->db
                ->where('user_id', $customer['user_id'])
                ->count_all_results('purchases');
            if ($purchase_count > 0) {
                $this->session->set_flashdata("err_msg", "General KYC cannot be deleted because purchases already exist.");
                redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
                return;
            }
        }

        $this->db->select('pan_image,aadhar_image,aadhar_back,tds_certificate,gst_certificate,company_registration_certificate,din_certificate');
        $this->db->where('user_id', $customer['user_id']);
        if ($firm_id > 0) {
            $this->db->where('firm_id', $firm_id);
        } else {
            $this->db->group_start();
            $this->db->where('firm_id', 0);
            $this->db->or_where('firm_id IS NULL', null, false);
            $this->db->group_end();
        }
        $kyc_row = $this->db->get('kyc')->row_array();
        if (empty($kyc_row)) {
            $this->session->set_flashdata("err_msg", "KYC record not found!");
            redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
            return;
        }

        foreach ($kyc_row as $path) {
            if (!empty($path) && file_exists(FCPATH . $path)) {
                @unlink(FCPATH . $path);
            }
        }

        $this->db->where('user_id', $customer['user_id']);
        if ($firm_id > 0) {
            $this->db->where('firm_id', $firm_id);
        } else {
            $this->db->group_start();
            $this->db->where('firm_id', 0);
            $this->db->or_where('firm_id IS NULL', null, false);
            $this->db->group_end();
        }
        $ok = $this->db->delete('kyc');
        $this->session->set_flashdata($ok ? "msg" : "err_msg", $ok ? "KYC deleted successfully." : "Failed to delete KYC.");
        redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
    }

    public function uploadcertificates($id = NULL)
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            redirect('customers/');
        }

        // Keep firm_id available for redirects even if request is not a POST.
        $firm_id = (int)$this->input->post('firm_id');
        if ($this->input->post('firm_id') === NULL) {
            $firm_id = (int)$this->input->get('firm_id');
        }

        if ($this->input->post('uploadcertificates') !== NULL) {
            $user = getuser();
            $data = array();
            $status = true;
            $message = array();
            $upload_path = './assets/images/profile/kyc/';
            $allowed_types = 'gif|jpg|jpeg|png|pdf';
            $firm_id = (int)$this->input->post('firm_id');

            // Check if KYC record exists
            $where = ['t1.user_id' => $customer['user_id']];
            if ($firm_id > 0) {
                $where['t1.firm_id'] = $firm_id;
            } else {
                $where['t1.firm_id IS NULL'] = null;
            }
            $kyc = $this->account->getkyc($where, 'single');
            if (empty($kyc)) {
                $this->session->set_flashdata("err_msg", "Please upload KYC details first!");
                redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
            }

            // Get existing KYC data with raw file paths (without file_url conversion)
            // Map logical names (audit_report, income_tax_certificate) to actual DB columns
            $existing_kyc = $this->db
                ->select('tds_certificate, gst_certificate, company_registration_certificate as audit_report, din_certificate as income_tax_certificate')
                ->where('user_id', $customer['user_id'])
                ->where('firm_id', $firm_id > 0 ? $firm_id : 0)
                ->get('kyc')
                ->row_array();

            // Upload TDS Certificate
            if (isset($_FILES['tds_certificate']['tmp_name']) && !empty($_FILES['tds_certificate']['tmp_name'])) {
                // Delete old file if exists
                if (!empty($existing_kyc['tds_certificate']) && file_exists(FCPATH . $existing_kyc['tds_certificate'])) {
                    @unlink(FCPATH . $existing_kyc['tds_certificate']);
                }

                $upload = upload_file('tds_certificate', $upload_path, $allowed_types, generate_slug($customer['name'] . '-tds-certificate'));
                if ($upload['status'] === true) {
                    $data['tds_certificate'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "TDS Certificate- " . trim($upload['msg']);
                }
            }

            // Upload GST Certificate
            if (isset($_FILES['gst_certificate']['tmp_name']) && !empty($_FILES['gst_certificate']['tmp_name'])) {
                // Delete old file if exists
                if (!empty($existing_kyc['gst_certificate']) && file_exists(FCPATH . $existing_kyc['gst_certificate'])) {
                    @unlink(FCPATH . $existing_kyc['gst_certificate']);
                }

                $upload = upload_file('gst_certificate', $upload_path, $allowed_types, generate_slug($customer['name'] . '-gst-certificate'));
                if ($upload['status'] === true) {
                    $data['gst_certificate'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "GST Certificate- " . trim($upload['msg']);
                }
            }

            // Upload Audit Report
            if (isset($_FILES['audit_report']['tmp_name']) && !empty($_FILES['audit_report']['tmp_name'])) {
                // Delete old file if exists
                if (!empty($existing_kyc['audit_report']) && file_exists(FCPATH . $existing_kyc['audit_report'])) {
                    @unlink(FCPATH . $existing_kyc['audit_report']);
                }

                $upload = upload_file('audit_report', $upload_path, $allowed_types, generate_slug($customer['name'] . '-audit-report'));
                if ($upload['status'] === true) {
                    $data['audit_report'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "Audit Report- " . trim($upload['msg']);
                }
            }

            // Upload Income Tax Certificate
            if (isset($_FILES['income_tax_certificate']['tmp_name']) && !empty($_FILES['income_tax_certificate']['tmp_name'])) {
                // Delete old file if exists
                if (!empty($existing_kyc['income_tax_certificate']) && file_exists(FCPATH . $existing_kyc['income_tax_certificate'])) {
                    @unlink(FCPATH . $existing_kyc['income_tax_certificate']);
                }

                $upload = upload_file('income_tax_certificate', $upload_path, $allowed_types, generate_slug($customer['name'] . '-income-tax-certificate'));
                if ($upload['status'] === true) {
                    $data['income_tax_certificate'] = $upload['path'];
                } else {
                    $status = false;
                    $message[] = "Income Tax Certificate- " . trim($upload['msg']);
                }
            }

            if (!empty($data)) {
                $data['user_id'] = $customer['user_id'];
                $data['firm_id'] = $firm_id > 0 ? $firm_id : 0;
                $data['updated_on'] = date('Y-m-d H:i:s');
                $result = $this->account->savekyc($data);
                if ($result['status'] === true) {
                    $this->session->set_flashdata("msg", "Certificates uploaded successfully!");
                } else {
                    $this->session->set_flashdata("err_msg", $result['message']);
                }
            } else if (!$status) {
                $message = implode('; ', $message);
                $this->session->set_flashdata("err_msg", $message);
            } else {
                $this->session->set_flashdata("err_msg", "Please select at least one certificate to upload!");
            }
        }
        redirect('customers/kycdetails/' . $id . '?firm_id=' . $firm_id);
    }

    public function download_certificate($id = NULL, $type = '')
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        $firm_id = (int)$this->input->get('firm_id');
        if (empty($customer)) {
            redirect('customers/');
        }

        $allowed_types = array('tds_certificate', 'gst_certificate', 'audit_report', 'income_tax_certificate');

        if (empty($type) || !in_array($type, $allowed_types)) {
            $this->session->set_flashdata("err_msg", "Invalid certificate type!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
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
        $this->db->select($db_column)->where('user_id', $customer['user_id']);
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
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        $file_path = $kyc[$type];
        $full_path = FCPATH . $file_path;

        // Check if file exists
        if (!file_exists($full_path)) {
            $this->session->set_flashdata("err_msg", "Certificate file not found!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        // Get filename from path
        $filename = basename($file_path);

        // Load download helper and force download
        $this->load->helper('download');
        force_download($full_path, NULL);
    }

    public function download_kyc_document($id = NULL, $type = '')
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        $firm_id = (int)$this->input->get('firm_id');
        if (empty($customer)) {
            redirect('customers/');
        }

        $allowed_types = array('pan_image', 'aadhar_image', 'aadhar_back');

        if (empty($type) || !in_array($type, $allowed_types)) {
            $this->session->set_flashdata("err_msg", "Invalid document type!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        // Get KYC data with raw file path (without file_url conversion)
        $this->db->select($type)->where('user_id', $customer['user_id']);
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
            $this->session->set_flashdata("err_msg", "Document not found!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        $file_path = $kyc[$type];
        $full_path = FCPATH . $file_path;

        // Check if file exists
        if (!file_exists($full_path)) {
            $this->session->set_flashdata("err_msg", "Document file not found!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        // Load download helper and force download
        $this->load->helper('download');
        force_download($full_path, NULL);
    }

    public function delete_certificate($id = NULL, $type = '')
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        $firm_id = (int)$this->input->get('firm_id');
        if (empty($customer)) {
            redirect('customers/');
        }

        $allowed_types = array('tds_certificate', 'gst_certificate', 'audit_report', 'income_tax_certificate');

        if (empty($type) || !in_array($type, $allowed_types)) {
            $this->session->set_flashdata("err_msg", "Invalid certificate type!");
            redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
        }

        // Map logical type to actual DB column for backward compatibility
        if ($type === 'audit_report') {
            $db_column = 'company_registration_certificate as audit_report';
        } elseif ($type === 'income_tax_certificate') {
            $db_column = 'din_certificate as income_tax_certificate';
        } else {
            $db_column = $type;
        }

        // Get existing certificate file path
        $this->db->select($db_column)->where('user_id', $customer['user_id']);
        if ($firm_id > 0) {
            $this->db->where('firm_id', $firm_id);
        } else {
            $this->db->group_start();
            $this->db->where('firm_id', 0);
            $this->db->or_where('firm_id IS NULL', null, false);
            $this->db->group_end();
        }
        $kyc = $this->db->get('kyc')->row_array();

        if (!empty($kyc) && !empty($kyc[$type])) {
            $file_path = $kyc[$type];
            $full_path = FCPATH . $file_path;

            // Delete file from server
            if (file_exists($full_path)) {
                @unlink($full_path);
            }

            // Update database - set certificate field to empty
            $update_data = array($type => '', 'updated_on' => date('Y-m-d H:i:s'));
            $this->db->where('user_id', $customer['user_id']);
            if ($firm_id > 0) {
                $this->db->where('firm_id', $firm_id);
            } else {
                $this->db->group_start();
                $this->db->where('firm_id', 0);
                $this->db->or_where('firm_id IS NULL', null, false);
                $this->db->group_end();
            }
            $result = $this->db->update('kyc', $update_data);

            if ($result) {
                $this->session->set_flashdata("msg", ucfirst(str_replace('_', ' ', $type)) . " deleted successfully!");
            } else {
                $this->session->set_flashdata("err_msg", "Failed to delete certificate!");
            }
        } else {
            $this->session->set_flashdata("err_msg", "Certificate not found!");
        }

        redirect('customers/kycdetails/' . $id . ($firm_id > 0 ? '?firm_id=' . $firm_id : ''));
    }

    public function customerpurchases()
    {
        $data['title'] = "Customer Purchases";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        if (!$this->staff_sees_all_customers()) {
            $where['md5(t1.added_by)'] = $this->session->user;
        }
        $data['customers'] = customer_dropdown($where);
        $data['years'] = year_dropdown();
        $this->template->load('customer', 'customerpurchases', $data);
    }

    public function customerfirmdetails()
    {
        $data['title'] = "Customer Firm Details";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        if (!$this->staff_sees_all_customers()) {
            $where['md5(t1.added_by)'] = $this->session->user;
        }
        $data['customers'] = customer_dropdown($where);
        $data['years'] = year_dropdown();
        $this->template->load('customer', 'customerfirmdetails', $data);
    }

    public function customerwisereport()
    {
        $data['title'] = "Customer Wise Report";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        if (!$this->staff_sees_all_customers()) {
            $where['md5(t1.added_by)'] = $this->session->user;
        }
        $data['customers'] = customer_dropdown($where);
        $data['years'] = year_dropdown();
        $this->template->load('customer', 'customerwisereport', $data);
    }

    public function packageswitchrequests()
    {
        $data['title'] = "Customer Package Switch Requests";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array('t1.status' => 2);
        $data['customers'] = $this->customer->getcustomerpackages($where);
        $this->template->load('customer', 'packageswitchrequests', $data);
    }

    public function firmdeleterequests()
    {
        $data['title'] = "Firm Delete Requests";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array('t1.status' => 1, 't1.request' => 1);
        $data['customers'] = $this->customer->getfirms($where);
        $this->template->load('customer', 'firmdeleterequests', $data);
    }

    public function firmeditrequests()
    {
        $data['title'] = "Firm Edit Requests";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array('t1.status' => 1, 't1.edit_request' => 1);
        $data['customers'] = $this->customer->getfirms($where);
        $this->template->load('customer', 'firmeditrequests', $data);
    }

    public function updatefirmeditstatus()
    {
        $id     = $this->input->post('id');
        $status = $this->input->post('status');
        $firm   = $this->customer->getfirms(["md5(concat('firm-id-',t1.id))" => $id, 't1.edit_request' => 1, 't1.status' => 1], 'single');
        if (!empty($firm)) {
            if ($status == 1) {
                // Approve: apply proposed changes to the firm
                $edit_data = !empty($firm['edit_request_data']) ? json_decode($firm['edit_request_data'], true) : array();
                $update = array('edit_request' => 0, 'edit_request_data' => NULL);
                if (!empty($edit_data['name'])) {
                    $update['name'] = $edit_data['name'];
                }
                if (!empty($edit_data['gstin'])) {
                    $update['gstin'] = $edit_data['gstin'];
                }
                logupdateoperations('firms', $update, ['id' => $firm['id']]);
                $result = $this->db->update('firms', $update, ['id' => $firm['id']]);
                $message = "Firm Edit Request Approved Successfully!";
            } else {
                // Reject: clear the request (edit_request=2 allows customer to re-request)
                $update = array('edit_request' => 2, 'edit_request_data' => NULL);
                logupdateoperations('firms', $update, ['id' => $firm['id']]);
                $result = $this->db->update('firms', $update, ['id' => $firm['id']]);
                $message = "Firm Edit Request Rejected!";
            }
            if ($result) {
                $this->session->set_flashdata("msg", $message);
            } else {
                $error = $this->db->error();
                $this->session->set_flashdata("err_msg", $error['message']);
            }
        }
    }

    public function packagedeleterequests()
    {
        $data['title'] = "Package Delete Requests";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;

        // Check if request column exists in service_packages table
        $check_column_service = $this->db->query("SHOW COLUMNS FROM `tf_service_packages` LIKE 'request'");
        $check_column_customer = $this->db->query("SHOW COLUMNS FROM `tf_customer_packages` LIKE 'request'");
        
        if ($check_column_service->num_rows() == 0 && $check_column_customer->num_rows() == 0) {
            // Column doesn't exist, show message to run migration
            $data['packages'] = array();
            $data['accountancy_packages'] = array();
            $data['migration_needed'] = true;
        } else {
            // Fetch service packages with delete requests
            if ($check_column_service->num_rows() > 0) {
                $where = array('t1.request' => 1);
                $data['packages'] = $this->customer->getservicepackage($where);
                if ($data['packages'] === false || $data['packages'] === null) {
                    $data['packages'] = array();
                }
            } else {
                $data['packages'] = array();
            }

            // Fetch Account Work packages (customer_packages) with delete requests
            if ($check_column_customer->num_rows() > 0) {
                $this->db->select('cp.*, u.name, f.name as firm_name');
                $this->db->from('customer_packages cp');
                $this->db->join('users u', 'u.id = cp.user_id', 'left');
                $this->db->join('firms f', 'f.id = cp.firm_id', 'left');
                $this->db->where('cp.request', 1);
                $this->db->where('cp.status', 1);
                $accountancy_packages = $this->db->get()->result_array();
                
                // Format package name
                if (!empty($accountancy_packages)) {
                    foreach ($accountancy_packages as $key => $pkg) {
                        $package_name = $pkg['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                        $package_type = !empty($pkg['package_type']) ? $pkg['package_type'] : 'Turnover';
                        $accountancy_packages[$key]['package'] = $package_name . ' (' . $package_type . ')';
                    }
                }
                $data['accountancy_packages'] = !empty($accountancy_packages) ? $accountancy_packages : array();
            } else {
                $data['accountancy_packages'] = array();
            }
        }
        $this->template->load('customer', 'packagedeleterequests', $data);
    }


    public function savecustomer()
    {
        if ($this->input->post('savecustomer') !== NULL) {
            $data = $this->input->post();
            unset($data['savecustomer']);
            $user = getuser();
            $data['added_by'] = $user['id'];
            // GST Configuration - Now handled via bulk toggle in customers list
            // Handle GST enabled checkbox - commented out as GST is now managed via bulk toggle
            // $data['gst_enabled'] = !empty($data['gst_enabled']) && $data['gst_enabled'] == 1 ? 1 : 0;
            // Set default to 0 (disabled) - can be enabled via bulk toggle
            if (!isset($data['gst_enabled'])) {
                $data['gst_enabled'] = 0;
            }
            $result = $this->customer->savecustomer($data);
            if ($result['status'] === true) {
                $this->session->set_flashdata("msg", $result['message']);
            } else {
                $this->session->set_flashdata("err_msg", $result['message']);
            }
            redirect('customers/addcustomer/');
        }
        if ($this->input->post('updatecustomer') !== NULL) {
            $data = $this->input->post();
            unset($data['updatecustomer']);
            $user = getuser();
            // GST Configuration - Now handled via bulk toggle in customers list
            // Handle GST enabled checkbox - commented out as GST is now managed via bulk toggle
            // $data['gst_enabled'] = !empty($data['gst_enabled']) && $data['gst_enabled'] == 1 ? 1 : 0;
            // Don't update gst_enabled from form - keep existing value (managed via bulk toggle)
            unset($data['gst_enabled']);
            //print_pre($data,true);
            $result = $this->customer->updatecustomer($data);
            if ($result['status'] === true) {
                $this->session->set_flashdata("msg", $result['message']);
            } else {
                $this->session->set_flashdata("err_msg", $result['message']);
            }
            redirect('customers/');
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function updatepackagerequest()
    {
        $id = $this->input->post('id');
        $status = $this->input->post('status');
        $getpackage = $this->db->get_where('customer_packages', ["md5(concat('customer-package-',id))" => $id]);
        if ($getpackage->num_rows() > 0) {
            $package = $getpackage->unbuffered_row('array');
            if ($status == 1) {
                $this->db->update('customer_packages', ['status' => 0], [
                    'user_id' => $package['user_id'],
                    'firm_id' => $package['firm_id']
                ]);
            }

            $result = $this->db->update('customer_packages', ['status' => $status], ['id' => $package['id']]);
            if ($result) {
                $this->session->set_flashdata("msg", "Package Switch Request Approved Successfully!");
            } else {
                $error = $this->db->error();
                $this->session->set_flashdata("err_msg", $error['message']);
            }
        }
    }

    public function updatefirmstatus()
    {
        $id = $this->input->post('id');
        $status = $this->input->post('status');
        $firm = $this->customer->getfirms(["md5(concat('firm-id-',t1.id))" => $id, 'request' => 1, 'status' => 1], 'single');
        if (!empty($firm)) {
            $message = $status == 1 ? "Firm Deleted Successfully" : "Firm Delete Request Rejected!";
            $request = $status == 1 ? 1 : 2;
            $status = $status == 1 ? 0 : 1;
            logupdateoperations('firms', ['status' => $status, 'request' => $request], ['id' => $firm['id']]);
            $result = $this->db->update('firms', ['status' => $status, 'request' => $request], ['id' => $firm['id']]);
            if ($result) {
                $this->session->set_flashdata("msg", $message);
            } else {
                $error = $this->db->error();
                $this->session->set_flashdata("err_msg", $error['message']);
            }
        }
    }

    public function updatepackagestatus()
    {
        $id = $this->input->post('id');
        $status = $this->input->post('status');
        $package_type = $this->input->post('package_type'); // 'service' or 'accountancy'
        
        $is_account_work = ($package_type === 'accountancy');
        
        if ($is_account_work) {
            // Handle Account Work packages (customer_packages)
            $package = $this->db->get_where(
                'customer_packages',
                ["md5(concat('package-id-',id))" => $id, 'request' => 1, 'status' => 1]
            )->unbuffered_row('array');
            
            if (!empty($package)) {
                $message = $status == 1 ? "Account Work Package Deleted Successfully" : "Account Work Package Delete Request Rejected!";
                $request = $status == 1 ? 1 : 2;

                if ($status == 1) {
                    // Approve: Delete the package (set status to 0)
                    logupdateoperations('customer_packages', ['request' => $request, 'status' => 0], ['id' => $package['id']]);
                    $result = $this->db->update('customer_packages', ['request' => $request, 'status' => 0], ['id' => $package['id']]);
                } else {
                    // Reject: Just update request to 2
                    logupdateoperations('customer_packages', ['request' => $request], ['id' => $package['id']]);
                    $result = $this->db->update('customer_packages', ['request' => $request], ['id' => $package['id']]);
                }

                if ($result) {
                    $this->session->set_flashdata("msg", $message);
                } else {
                    $error = $this->db->error();
                    $this->session->set_flashdata("err_msg", $error['message']);
                }
            }
        } else {
            // Handle regular service packages (service_packages)
            $package = $this->customer->getservicepackage(["md5(concat('package-id-',t1.id))" => $id, 't1.request' => 1], 'single');
            if (!empty($package)) {
                $message = $status == 1 ? "Package Deleted Successfully" : "Package Delete Request Rejected!";
                $request = $status == 1 ? 1 : 2;

                if ($status == 1) {
                    // Approve: Delete the package record
                    logupdateoperations('service_packages', ['request' => $request], ['id' => $package['id']]);
                    $result = $this->db->delete('service_packages', ['id' => $package['id']]);
                } else {
                    // Reject: Just update request to 2
                    logupdateoperations('service_packages', ['request' => $request], ['id' => $package['id']]);
                    $result = $this->db->update('service_packages', ['request' => $request], ['id' => $package['id']]);
                }

                if ($result) {
                    $this->session->set_flashdata("msg", $message);
                } else {
                    $error = $this->db->error();
                    $this->session->set_flashdata("err_msg", $error['message']);
                }
            }
        }
    }

    public function getpurchases()
    {
        $user_id = $this->input->post('user_id');
        $year = $this->input->post('year');
        if (!empty($year)) {
            $years = getyearmonthvalues($year);
            $data['years'] = $years;
            $where = array('t1.user_id' => $user_id, 't1.date>=' => $years['year1'] . '-04-01', 't1.date<=' => $years['year2'] . '-03-31');
            $data['purchases'] = $this->service->getpurchases($where);
        }
        $data['services'] = $this->master->getservices();
        $this->load->view('customer/servicetable', $data);
    }

    public function getuserfirms()
    {
        $user_id = $this->input->post('user_id');
        $firms = $this->customer->getfirms(['t1.user_id' => $user_id, 't1.status' => 1]);
        $options = "<option value=''>Select Firm</option>";
        if (!empty($firms)) {
            foreach ($firms as $firm) {
                $options .= "<option value='" . $firm['id'] . "'>" . $firm['name'] . "</option>";
            }
        }
        echo $options;
    }

    public function getfirmdetails()
    {
        $user_id = $this->input->post('user_id');
        $firm_id = $this->input->post('firm_id');
        $year = $this->input->post('year');
        if (!empty($user_id) && !empty($year) && !empty($firm_id)) {
            $yearval = getyearmonthvalues($year);
            $year1 = $yearval['year1'];
            $year2 = $yearval['year2'];
            $from = "$year1-04-01";
            $to = "$year2-03-31";
            $data = array();
            
            // Get wallet & credit limit balance
            $this->load->model('Wallet_model', 'wallet');
            $data['credit_limit'] = $this->wallet->get_available_credit($user_id);
            $data['wallet_balance'] = $this->wallet->getwalletbalance($user_id);
            
            // Get package details (Active Package)
            $where = array('user_id' => $user_id, 'firm_id' => $firm_id, 'status' => 1);
            $query = $this->db->order_by('purchase_date', 'DESC')->get_where('customer_packages', $where);
            $data['cpackage'] = null;
            $active_pkg_id = 0;
            if ($query->num_rows() > 0) {
                $cpackage = $query->unbuffered_row('array');
                $data['cpackage'] = $cpackage;
                $active_pkg_id = $cpackage['id'];
                
                // Ensure financial year monthly records exist and are sanitized
                ensure_accountancy_fy_months($user_id, $firm_id, $year, $cpackage['package_id']);

                $where2 = "t1.user_id='$user_id' and t1.firm_id='$firm_id' and t1.date>='$from' and t1.date<='$to'";
                $data['accountancy'] = $this->service->getturnoverswithpayment($where2);
                $turnovers = !empty($data['accountancy']) ? array_column($data['accountancy'], 'turnover') : array(0);
                $turnover = array_sum($turnovers);
                $data['total_turnover'] = $turnover;
                $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                $data['package'] = $this->master->getpackages(['name' => $name, 'turnover>' => $turnover], 'single');
            } else {
                $data['accountancy'] = array();
            }

            // Get all Monthly packages (Exclude active package)
            $where_pending = array(
                'user_id' => $user_id,
                'firm_id' => $firm_id,
                'year' => $year,
                'package_type' => 'Monthly',
                'status' => 1
            );
            $this->db->where($where_pending);
            if ($active_pkg_id > 0) {
                $this->db->where('id !=', $active_pkg_id);
            }
            $data['pending_monthly'] = $this->db->order_by('purchase_date', 'ASC')
                                                ->get('customer_packages')
                                                ->result_array();

            $this->load->view('customer/firmdetailstable', $data);
        } else {
            echo '';
        }
    }
    public function renewaccountancy()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized access!']);
            return;
        }

        $id = $this->input->post('id');
        $amount = $this->input->post('amount');
        $user_id = $this->input->post('user_id');
        $firm_id = $this->input->post('firm_id');
        $date = $this->input->post('date');
        $acc_fee = $this->input->post('acc_fee') ?: 0;
        $late_fee = $this->input->post('late_fee') ?: 0;
        $payment_method = $this->input->post('payment_method') ?: 'Wallet';

        if (empty($id) || empty($amount) || empty($user_id) || empty($firm_id)) {
            echo json_encode(['status' => false, 'message' => 'Missing required fields!']);
            return;
        }

        $this->load->model('Wallet_model', 'wallet');

        // Verify balance
        if ($payment_method == 'Credit Limit') {
            $available_credit = $this->wallet->get_available_credit($user_id);

            if ($available_credit < $amount) {
                echo json_encode(['status' => false, 'message' => 'Insufficient Credit Limit. Available credit is ₹' . number_format($available_credit, 2)]);
                return;
            }
        } else {
            $wallet_balance = $this->wallet->getwalletbalance($user_id);

            if ($wallet_balance < $amount) {
                echo json_encode(['status' => false, 'message' => 'Insufficient wallet balance. Current balance is ₹' . number_format($wallet_balance, 2)]);
                return;
            }
        }

        // Insert payment record
        $payment_data = array(
            'user_id' => $user_id,
            'firm_id' => $firm_id,
            'acc_date' => $date,
            'amount' => $amount,
            'acc_fee' => $acc_fee,
            'late_fee' => $late_fee,
            'payment_mode' => $payment_method,
            'added_on' => date('Y-m-d H:i:s'),
            'updated_on' => date('Y-m-d H:i:s')
        );

        if ($this->db->insert('acc_payment', $payment_data)) {
            // Update auto_debit_status in accountancy table
            $this->db->update('accountancy', ['auto_debit_status' => 'Confirmed'], ['id' => $id]);
            
            // Add notification
            $this->common->savenotification(array(
                'user_id' => (int) $user_id,
                'type' => 'payment',
                'message' => '₹' . number_format((float) $amount, 2) . ' deducted from your ' . $payment_method . ' for accountancy renewal.',
            ));

            echo json_encode(['status' => true, 'message' => 'Renewal successful. ₹' . number_format($amount, 2) . ' deducted from ' . $payment_method . '.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to record payment. Please try again.']);
        }
    }

    public function renewmonthlypackage()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            echo json_encode(['status' => false, 'message' => 'Unauthorized access!']);
            return;
        }

        $id = $this->input->post('id');
        $amount = (float)$this->input->post('amount');
        $user_id = $this->input->post('user_id');
        $payment_method = $this->input->post('payment_method') ?: 'Wallet';

        if (empty($id) || empty($amount) || empty($user_id)) {
            echo json_encode(['status' => false, 'message' => 'Missing required fields!']);
            return;
        }

        $this->load->model('Wallet_model', 'wallet');

        // Verify balance
        if ($payment_method == 'Credit Limit') {
            $available_credit = $this->wallet->get_available_credit($user_id);

            if ($available_credit < $amount) {
                echo json_encode(['status' => false, 'message' => 'Insufficient Credit Limit. Available credit is ₹' . number_format($available_credit, 2)]);
                return;
            }
        } else {
            $wallet_balance = $this->wallet->getwalletbalance($user_id);

            if ($wallet_balance < $amount) {
                echo json_encode(['status' => false, 'message' => 'Insufficient wallet balance. Current balance is ₹' . number_format($wallet_balance, 2)]);
                return;
            }
        }

        $this->db->trans_start();

        // 1. Update package
        $update_data = [
            'payment_status' => 1,
            'updated_on' => date('Y-m-d H:i:s')
        ];
        $this->db->update('customer_packages', $update_data, ['id' => $id]);

        // 2. Fetch package info
        $pkg = $this->db->get_where('customer_packages', ['id' => $id])->unbuffered_row('array');
        if (!empty($pkg)) {
            $payment_data = array(
                'user_id' => $user_id,
                'firm_id' => $pkg['firm_id'],
                'acc_date' => date('Y-m-d', strtotime($pkg['purchase_date'])),
                'amount' => $amount,
                'payment_mode' => $payment_method,
                'added_on' => date('Y-m-d H:i:s'),
                'updated_on' => date('Y-m-d H:i:s')
            );
            $this->db->insert('acc_payment', $payment_data);
            
            $current_month_start = date('Y-m-01', strtotime($pkg['purchase_date']));
            $acc_record = $this->db->get_where('accountancy', [
                'user_id' => $user_id,
                'firm_id' => $pkg['firm_id'],
                'date' => $current_month_start
            ])->unbuffered_row('array');
            
            if (!empty($acc_record)) {
                $existing_other_fee = (float)($acc_record['other_fee'] ?? 0);
                $new_other_fee = $existing_other_fee + $amount;
                $this->db->update('accountancy', [
                    'other_fee' => $new_other_fee,
                    'updated_on' => date('Y-m-d H:i:s')
                ], ['id' => $acc_record['id']]);
            } else {
                $acc_data = [
                    'user_id' => $user_id,
                    'firm_id' => $pkg['firm_id'],
                    'date' => $current_month_start,
                    'other_fee' => $amount,
                    'added_on' => date('Y-m-d H:i:s'),
                    'updated_on' => date('Y-m-d H:i:s')
                ];
                $this->db->insert('accountancy', $acc_data);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            echo json_encode(['status' => false, 'message' => 'Failed to process renewal.']);
        } else {
            $this->common->savenotification(array(
                'user_id' => (int) $user_id,
                'type' => 'payment',
                'message' => '₹' . number_format($amount, 2) . ' deducted from your ' . $payment_method . ' for Monthly package renewal.',
            ));
            echo json_encode(['status' => true, 'message' => 'Renewal successful. ₹' . number_format($amount, 2) . ' deducted from ' . $payment_method . '.']);
        }
    }

    public function getcustomerreport()
    {
        $user_id = $this->input->post('user_id');
        $year = $this->input->post('year');
        $where = array('t1.user_id' => $user_id);
        if (!empty($year)) {
            $where['t1.year'] = $year;
        }
        $data['orders'] = $this->service->getpurchases($where);
        $this->load->view('customer/orderlist', $data);
    }

    public function uploadolddata($id = NULL)
    {
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            redirect('customers/');
        }
        $data['customer'] = $customer;
        $data['title'] = "Upload Old Data";
        $data['breadcrumb'] = array("customers/" => "Customers", "active" => "Upload Old Data");

        // Get all services
        $data['services'] = $this->master->getservices(array('status' => 1));

        // Get existing old data for this customer
        $where = array('t1.user_id' => $customer['user_id'], 't1.status' => 1);
        $data['old_data'] = $this->customer->getoldclientdata($where);

        $this->template->load('customer', 'uploadolddata', $data);
    }

    public function saveolddata()
    {
        if ($this->input->post('saveolddata') !== NULL) {
            $user = getuser();
            $data = $this->input->post();
            unset($data['saveolddata']);

            $customer = $this->customer->getcustomers(['md5(t1.id)' => $data['customer_id']], 'single');
            if (empty($customer)) {
                $this->session->set_flashdata("err_msg", "Customer not found!");
                redirect('customers/');
            }

            $data['user_id'] = $customer['user_id'];
            $data['uploaded_by'] = $user['id'];
            unset($data['customer_id']);

            // Handle file upload
            if (isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
                $upload_path = './assets/documents/old_data/';
                $allowed_types = 'gif|jpg|jpeg|png|pdf|doc|docx|xls|xlsx|zip|rar|csv|txt|7z';
                $file_name = generate_slug($customer['name'] . '-' . $data['service_id'] . '-' . time());

                $upload = upload_file('file', $upload_path, $allowed_types, $file_name);
                if ($upload['status'] === true) {
                    $data['file_path'] = $upload['path'];
                    $data['file_name'] = $_FILES['file']['name'];
                    $data['file_type'] = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                    $data['file_size'] = $_FILES['file']['size'];

                    $result = $this->customer->saveoldclientdata($data);
                    if ($result['status'] === true) {
                        $this->session->set_flashdata("msg", $result['message']);
                    } else {
                        $this->session->set_flashdata("err_msg", $result['message']);
                    }
                } else {
                    $this->session->set_flashdata("err_msg", "File Upload Error: " . $upload['msg']);
                }
            } else {
                $this->session->set_flashdata("err_msg", "Please select a file to upload!");
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function deleteolddata($id = NULL)
    {
        if ($id === NULL) {
            redirect('customers/');
        }
        $old_data = $this->customer->getoldclientdata(['md5(t1.id)' => $id], 'single');
        if (empty($old_data)) {
            $this->session->set_flashdata("err_msg", "Data not found!");
            redirect('customers/');
        }

        $result = $this->customer->deleteoldclientdata($old_data['id']);
        if ($result['status'] === true) {
            $this->session->set_flashdata("msg", $result['message']);
        } else {
            $this->session->set_flashdata("err_msg", $result['message']);
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function downloadolddata($id = NULL)
    {
        if ($id === NULL) {
            redirect('customers/');
        }
        $old_data = $this->customer->getoldclientdata(['md5(t1.id)' => $id], 'single');
        if (empty($old_data)) {
            $this->session->set_flashdata("err_msg", "Data not found!");
            redirect('customers/');
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
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function walletrecharge()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }
        $data['title'] = "Wallet Recharge";
        $data['breadcrumb'] = array("customers/" => "Customers", "active" => "Wallet Recharge");
        $where = array();
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            $where['md5(t1.added_by)'] = $this->session->user;
        }
        $data['customers'] = customer_dropdown($where);
        $this->template->load('customer', 'walletrecharge', $data);
    }

    public function savewalletrecharge()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            $this->session->set_flashdata("err_msg", "Unauthorized access!");
            redirect('customers/');
        }

        if ($this->input->post('savewalletrecharge') !== NULL) {
            $user_id = $this->input->post('user_id');
            $amount = $this->input->post('amount');
            $payment_method = $this->input->post('payment_method');
            $remarks = $this->input->post('remarks');
            $date = $this->input->post('date');

            // Validation
            if (empty($user_id) || empty($amount) || empty($date)) {
                $this->session->set_flashdata("err_msg", "Please fill all required fields!");
                redirect('customers/walletrecharge/');
            }

            if (!is_numeric($amount) || $amount <= 0) {
                $this->session->set_flashdata("err_msg", "Please enter a valid amount!");
                redirect('customers/walletrecharge/');
            }

            // Verify customer exists
            $customer = $this->customer->getcustomers(['t1.user_id' => $user_id], 'single');
            if (empty($customer)) {
                $this->session->set_flashdata("err_msg", "Customer not found!");
                redirect('customers/walletrecharge/');
            }

            // Prepare data for wallet recharge
            $data = array(
                'user_id' => $user_id,
                'date' => $date,
                'amount' => $amount
            );

            // Add remarks if provided
            if (!empty($payment_method)) {
                $remark_text = "Admin Recharge via " . $payment_method;
                if (!empty($remarks)) {
                    $remark_text .= " - " . $remarks;
                }
                $data['remarks'] = $remark_text;
            } elseif (!empty($remarks)) {
                $data['remarks'] = "Admin Recharge - " . $remarks;
            }

            // Add wallet recharge
            $result = $this->wallet->adminrecharge($data);
            if ($result['status'] === true) {
                $this->common->savenotification(array(
                    'user_id' => (int) $user_id,
                    'type' => 'payment',
                    'message' => '₹' . number_format((float) $amount, 2) . ' credited to your wallet.',
                ));
                $this->session->set_flashdata("msg", $result['message']);
            } else {
                $this->session->set_flashdata("err_msg", $result['message']);
            }
        }
        redirect('customers/walletrecharge/');
    }

    public function walletrechargelist()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }
        $data['title'] = "Wallet Recharge History";
        $data['breadcrumb'] = array("customers/" => "Customers", "active" => "Wallet Recharge History");
        $data['datatable'] = true;
        $where = array();
        $data['recharges'] = $this->wallet->getwalletrecharges($where);
        $this->template->load('customer', 'walletrechargelist', $data);
    }

    public function bulkimport()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }
        $data['title'] = "Bulk Customer Import";
        $data['breadcrumb'] = array("customers/" => "Customers", "active" => "Bulk Import");

        // Get import results from session if available
        $import_results = $this->session->userdata('bulk_import_results');
        if (!empty($import_results)) {
            $data['import_results'] = $import_results;
            // Clear session data after displaying
            $this->session->unset_userdata('bulk_import_results');
        }

        $this->template->load('customer', 'bulkimport', $data);
    }

    public function downloadtemplate()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }

        $filename = 'customer_import_template.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array('Name', 'Mobile', 'Email', 'Address', 'State', 'District', 'Pincode'));

        // Sample row
        fputcsv($output, array('John Doe', '9876543210', 'john@example.com', '123 Main Street', 'Maharashtra', 'Mumbai', '400001'));

        fclose($output);
        exit;
    }

    public function processbulkimport()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }

        if ($this->input->post('bulkimport') !== NULL) {
            $user = getuser();
            $default_password = $this->input->post('default_password');

            // Check if file was uploaded
            if (empty($_FILES['csv_file']['tmp_name'])) {
                $this->session->set_flashdata("err_msg", "Please select a CSV file to upload!");
                redirect('customers/bulkimport/');
            }

            $file = $_FILES['csv_file'];

            // Validate file type
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_ext !== 'csv') {
                $this->session->set_flashdata("err_msg", "Only CSV files are allowed!");
                redirect('customers/bulkimport/');
            }

            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                $this->session->set_flashdata("err_msg", "File size exceeds 5MB limit!");
                redirect('customers/bulkimport/');
            }

            // Read CSV file
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                $this->session->set_flashdata("err_msg", "Failed to read CSV file!");
                redirect('customers/bulkimport/');
            }

            // Skip header row
            $header = fgetcsv($handle);

            $results = array(
                'success_count' => 0,
                'error_count' => 0,
                'duplicate_count' => 0,
                'total_count' => 0,
                'errors' => array(),
                'credentials' => array()
            );

            $row_num = 1;
            $max_rows = 200; // Limit to 200 customers per import

            while (($data = fgetcsv($handle)) !== false && $results['total_count'] < $max_rows) {
                $row_num++;
                $results['total_count']++;

                // Validate required fields
                if (empty($data[0]) || empty($data[1])) {
                    $results['error_count']++;
                    $results['errors'][] = "Row $row_num: Name and Mobile are required";
                    continue;
                }

                $customer_data = array(
                    'name' => trim($data[0]),
                    'mobile' => trim($data[1]),
                    'email' => !empty($data[2]) ? trim($data[2]) : '',
                    'address' => !empty($data[3]) ? trim($data[3]) : '',
                    'state' => !empty($data[4]) ? trim($data[4]) : '',
                    'district' => !empty($data[5]) ? trim($data[5]) : '',
                    'pincode' => !empty($data[6]) ? trim($data[6]) : '',
                    'added_by' => $user['id'],
                    'gst_enabled' => 0
                );

                // Validate mobile number
                if (!preg_match('/^[0-9]{10}$/', $customer_data['mobile'])) {
                    $results['error_count']++;
                    $results['errors'][] = "Row $row_num: Invalid mobile number (must be 10 digits)";
                    continue;
                }

                // Check for duplicate mobile
                $existing = $this->db->get_where('customers', array('mobile' => $customer_data['mobile']))->num_rows();
                if ($existing > 0) {
                    $results['duplicate_count']++;
                    continue;
                }

                // Handle state and district
                if (!empty($customer_data['state'])) {
                    $state = $this->db->get_where('area', array('name' => $customer_data['state'], 'parent_id' => 0))->row_array();
                    if (!empty($state)) {
                        $customer_data['parent_id'] = $state['id'];
                    }
                }

                if (!empty($customer_data['district']) && !empty($customer_data['parent_id'])) {
                    $district = $this->db->get_where('area', array('name' => $customer_data['district'], 'parent_id' => $customer_data['parent_id']))->row_array();
                    if (!empty($district)) {
                        $customer_data['area_id'] = $district['id'];
                    }
                }

                // Set password for customer (use default password or mobile number)
                $password = !empty($default_password) ? $default_password : $customer_data['mobile'];
                $customer_data['password'] = $password;

                // Save customer
                $result = $this->customer->savecustomer($customer_data);

                if ($result['status'] === true) {
                    $results['success_count']++;

                    // Store credentials for download
                    $results['credentials'][] = array(
                        'name' => $customer_data['name'],
                        'mobile' => $customer_data['mobile'],
                        'username' => $customer_data['mobile'],
                        'password' => $password,
                        'email' => $customer_data['email']
                    );
                } else {
                    $results['error_count']++;
                    $results['errors'][] = "Row $row_num: " . $result['message'];
                }
            }

            fclose($handle);

            // Store results in session for display
            $this->session->set_userdata('bulk_import_results', $results);
            $this->session->set_userdata('bulk_import_credentials', $results['credentials']);

            if ($results['success_count'] > 0) {
                $this->session->set_flashdata("msg", "Bulk import completed! {$results['success_count']} customers imported successfully.");
            } else {
                $this->session->set_flashdata("err_msg", "No customers were imported. Please check the errors below.");
            }
        }

        redirect('customers/bulkimport/');
    }

    public function downloadcredentials()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            redirect('customers/');
        }

        $credentials = $this->session->userdata('bulk_import_credentials');

        if (empty($credentials)) {
            $this->session->set_flashdata("err_msg", "No credentials available to download!");
            redirect('customers/bulkimport/');
        }

        $filename = 'customer_login_credentials_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array('Name', 'Mobile', 'Username', 'Password', 'Email'));

        // Data rows
        foreach ($credentials as $cred) {
            fputcsv($output, array(
                $cred['name'],
                $cred['mobile'],
                $cred['username'],
                $cred['password'],
                $cred['email']
            ));
        }

        fclose($output);
        exit;
    }

    public function deletecustomer()
    {
        // Only allow admin access
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'status' => false,
                'message' => 'Unauthorized access!'
            ]));
            return;
        }

        $id = $this->input->post('id');
        if (empty($id)) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'status' => false,
                'message' => 'Customer ID is required!'
            ]));
            return;
        }

        // Get customer by md5 hash
        $customer = $this->customer->getcustomers(['md5(t1.id)' => $id], 'single');
        if (empty($customer)) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'status' => false,
                'message' => 'Customer not found!'
            ]));
            return;
        }

        // Delete customer and all related data
        $result = $this->customer->deletecustomer($customer['id']);

        $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }
}
//url_title