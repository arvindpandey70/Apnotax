<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Orders extends CI_Controller
{

    var $multiplier = 100000;

    function __construct()
    {
        parent::__construct();
        checklogin();
    }

    public function index()
    {
        $data['title'] = "Orders";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        // Allow admin and employee to see all orders, restrict only customers
        if ($this->session->role == 'customer') {
            $where['t1.status<'] = 3;
            $where['t1.status!='] = 1;
        }
        $data['orders'] = $this->service->getpurchases($where);
        $this->template->load('orders', 'list', $data);
    }

    public function myassessments()
    {
        $data['title'] = "Orders";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['datatable'] = true;
        $where = array();
        // Allow admin and employee to see all assessments, restrict only customers
        if ($this->session->role == 'customer') {
            $where['md5(a.user_id)'] = $this->session->user;
        }
        $data['orders'] = $this->employee->myassessments($where);
        $this->template->load('orders', 'myassessments', $data);
    }

    public function viewdocuments($id = NULL)
    {
        $order = $this->service->getpurchases(['md5(t1.id)' => $id], 'single');
        //print_pre($order,true);
        if (empty($order)) {
            redirect('orders/');
            exit;
        }
        $service_id = $order['service_id'];


        $documents = $this->service->getuploadeddocuments(['a.order_id' => $order['id']]);
        if (!empty($documents)) {
            foreach ($documents as $key => $value) {
                if (strpos($value['formvalue'], '/assets/') === 0) {
                    $documents[$key]['formvalue'] = file_url($value['formvalue']);
                }
            }
        } else {
            $documents = $this->master->getservicedocuments(['t1.service_id' => $service_id]);
        }

        $data['order'] = $order;
        $data['documents'] = $documents;
        $data['title'] = "View Documents";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        //print_pre($data,true);

        $where = "t1.role!='admin' && t1.role!='customer'";
        $employees = $this->account->getusers($where);

        $options = array('' => "Select Employee");
        if (!empty($employees)) {
            foreach ($employees as $employee) {
                $options[$employee['id']] = $employee['username'] . ' - ' . $employee['name'];
            }
        }

        $data['employees'] = $options;

        $getassigned = $this->db->get_where('order_assign', ['order_id' => $order['id'], 'status' => 0]);
        $assigned = array();
        if ($getassigned->num_rows() > 0) {
            $assigned = $getassigned->unbuffered_row('array');
        }
        $data['assigned'] = $assigned;

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

        $this->template->load('orders', 'viewdocuments', $data);
    }

    public function addturnover()
    {
        // Restrict access to admin and employee only
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('home/');
        }

        $data['title'] = "Add Turnover";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        //print_pre($data,true);

        $data['customers'] = customer_dropdown();
        $data['years'] = year_dropdown();
        $data['months'] = month_dropdown();

        $this->template->load('orders', 'addturnover', $data);
    }

    public function yearlyturnover()
    {
        // Restrict access to admin and employee only
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('home/');
        }

        $data['title'] = "Add Turnover";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        //print_pre($data,true);

        $data['customers'] = customer_dropdown();
        $data['years'] = year_dropdown();
        $data['months'] = month_dropdown();

        $this->template->load('orders', 'yearlyturnover', $data);
    }

    public function monthlyturnover()
    {
        // Restrict access to admin and employee only
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('home/');
        }

        $data['title'] = "Add Turnover";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        //print_pre($data,true);

        $data['customers'] = customer_dropdown();
        $data['years'] = year_dropdown();
        $data['months'] = month_dropdown();
        // Show only firms/customers who bought Account Work in Turnover basis
        $data['allcustomers'] = $this->customer->customerwithfirm_accountwork_turnover();

        $this->template->load('orders', 'monthlyturnover', $data);
    }

    public function turnoversheet()
    {
        // Restrict access to admin and employee only
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('home/');
        }

        $data['title'] = "Turnover Sheet";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        //print_pre($data,true);

        $data['customers'] = customer_dropdown();
        $data['years'] = year_dropdown();
        $data['months'] = month_dropdown();

        $this->template->load('orders', 'turnoversheet', $data);
    }

    public function acceptorder($id = NULL)
    {
        $order = $this->service->getpurchases(['md5(t1.id)' => $id], 'single');
        //print_pre($order,true);
        if (empty($order)) {
            redirect('orders/');
            exit;
        }
        
        if (isset($order['status']) && $order['status'] == 0) {
            $this->session->set_flashdata('err_msg', 'This service is pending renewal. Work cannot be done until it is renewed.');
            redirect('orders/');
            return;
        }
        $service_id = $order['service_id'];
        $user = getuser();
        $data = array(
            'order_id' => $order['id'],
            'user_id' => $user['id'],
            'done_by' => $user['id'],
            'status' => 0,
            'added_on' => date('Y-m-d H:i:s'),
            'updated_on' => date('Y-m-d H:i:s')
        );
        if ($this->db->insert('order_assign', $data)) {
            $this->db->update('purchases', ['status' => 3], ['id' => $order['id']]);
            $this->common->savenotification(array(
                'type' => 'order',
                'user_id' => (int) $order['user_id'],
                'order_id' => (int) $order['id'],
                'message' => 'Your order for "' . (!empty($order['service_name']) ? $order['service_name'] : 'service') . '" is now under assessment.',
            ));
            $this->session->set_flashdata(['msg' => 'Accepted for Assessment']);
        } else {
            $error = $this->db->error();
            $this->session->set_flashdata(['err_msg' => $error['message']]);
        }
        redirect('orders/');
    }

    public function assignemployee($id = NULL)
    {
        // Only allow admin and employee to assign orders (not customers)
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('orders/');
            return;
        }

        if ($this->input->post('assignemployee') !== NULL) {
            $data = $this->input->post();
            $user = getuser();
            
            // Check if order is pending
            $order_id = $data['order_id'];
            $order = $this->service->getpurchases(['t1.id' => $order_id], 'single');
            if (!empty($order) && isset($order['status']) && $order['status'] == 0) {
                $this->session->set_flashdata('err_msg', 'This service is pending renewal. Work cannot be done until it is renewed.');
                redirect('orders/');
                return;
            }
            
            $data['done_by'] = $user['id'];
            unset($data['assignemployee']);
            $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
            if ($this->db->insert('order_assign', $data)) {
                $this->db->update('purchases', ['status' => 3], ['id' => $data['order_id']]);
                $ord = $this->service->getpurchases(array('t1.id' => (int) $data['order_id']), 'single');
                if (!empty($ord)) {
                    $this->common->savenotification(array(
                        'type' => 'order',
                        'user_id' => (int) $ord['user_id'],
                        'order_id' => (int) $ord['id'],
                        'message' => 'Your order for "' . (!empty($ord['service_name']) ? $ord['service_name'] : 'service') . '" is now under assessment.',
                    ));
                }
                $this->session->set_flashdata(['msg' => 'Employee Assigned Successfully!']);
            } else {
                $error = $this->db->error();
                $this->session->set_flashdata(['err_msg' => $error['message']]);
            }
        }
        redirect('orders/');
    }

    public function uploadassessment($id = NULL)
    {
        $redirect = 'orders/';
        if ($this->input->post('uploadassessment') !== NULL) {
            $data = $this->input->post();
            $user = getuser();
            $data['user_id'] = $user['id'];
            unset($data['uploadassessment']);
            $redirect = 'orders/viewdocuments/' . md5($data['order_id']);
            $upload_path = './assets/service/assessments/';
            $allowed_types = 'pdf|xlsx|doc|docx';
            if (isset($_FILES['file']['tmp_name'])) {
                $order = $this->service->getpurchases(['t1.id' => $data['order_id']], 'single');
                if (!empty($order) && isset($order['status']) && $order['status'] == 0) {
                    $this->session->set_flashdata('err_msg', 'This service is pending renewal. Work cannot be done until it is renewed.');
                    redirect('orders/viewdocuments/' . md5($data['order_id']));
                    return;
                }
                $filename = $order['name'] . '-' . $order['service_name'] . '-assessment';
                $upload = upload_file('file', $upload_path, $allowed_types, $filename);
                if ($upload['status'] === true) {
                    $data['file'] = $upload['path'];
                    $data['firm_id'] = $this->db->get_where('formdata', ['order_id' => $data['order_id']])->unbuffered_row()->firm_id;
                    $data['date'] = date("Y-m-d");
                    $data['status'] = 1; // Mark assessment as completed
                    $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
                    if ($this->db->insert('assessments', $data)) {
                        $this->db->update('purchases', ['status' => 4], ['id' => $data['order_id']]);
                        $this->common->savenotification(array(
                            'type' => 'order',
                            'user_id' => (int) $order['user_id'],
                            'order_id' => (int) $order['id'],
                            'message' => 'Your assessment report for "' . (!empty($order['service_name']) ? $order['service_name'] : 'service') . '" is ready to view.',
                        ));
                        $this->session->set_flashdata(['msg' => 'Assessment Uploaded Successfully!']);
                    } else {
                        $error = $this->db->error();
                        $this->session->set_flashdata(['err_msg' => $error['message']]);
                    }
                } else {
                    $this->session->set_flashdata(['err_msg' => $upload['msg']]);
                }
            } else {
                $this->session->set_flashdata(['err_msg' => "File not Uploaded! Please Try Again"]);
            }
        }
        redirect($redirect);
    }

    public function markassignmentdone($id = NULL)
    {
        $order = $this->service->getpurchases(['md5(t1.id)' => $id], 'single');
        if (empty($order)) {
            $this->session->set_flashdata('err_msg', 'Order not found!');
            redirect('orders/');
            return;
        }

        if (isset($order['status']) && $order['status'] == 0) {
            $this->session->set_flashdata('err_msg', 'This service is pending renewal. Work cannot be done until it is renewed.');
            redirect('orders/viewdocuments/' . md5($order['id']));
            return;
        }

        // Check if assessment exists
        $getassessment = $this->db->get_where('assessments', ['order_id' => $order['id'], 'status' => 1]);
        if ($getassessment->num_rows() == 0) {
            // Fallback to status=0 for backward compatibility
            $getassessment = $this->db->get_where('assessments', ['order_id' => $order['id'], 'status' => 0]);
        }

        if ($getassessment->num_rows() == 0) {
            $this->session->set_flashdata('err_msg', 'Assessment not found!');
            redirect('orders/viewdocuments/' . md5($order['id']));
            return;
        }

        $assessment = $getassessment->unbuffered_row('array');

        // Check if assignment is already done
        $assignment_done = isset($assessment['assignment_done']) ? $assessment['assignment_done'] : 0;
        if ($assignment_done == 1) {
            $this->session->set_flashdata('err_msg', 'Assignment already marked as done!');
            redirect('orders/viewdocuments/' . md5($order['id']));
            return;
        }

        // Check if user has permission (assigned employee or admin)
        $getassigned = $this->db->get_where('order_assign', ['order_id' => $order['id'], 'status' => 0]);
        $assigned = array();
        if ($getassigned->num_rows() > 0) {
            $assigned = $getassigned->unbuffered_row('array');
        }

        $user = getuser();
        if ($this->session->role != 'admin' && $this->session->role != 'superadmin') {
            if (empty($assigned) || $assigned['user_id'] != $user['id']) {
                $this->session->set_flashdata('err_msg', 'You do not have permission to mark this assignment as done!');
                redirect('orders/viewdocuments/' . md5($order['id']));
                return;
            }
        }

        // Check if assignment_done column exists
        $check_column = $this->db->query("SHOW COLUMNS FROM `tf_assessments` LIKE 'assignment_done'");
        if ($check_column->num_rows() == 0) {
            $this->session->set_flashdata('err_msg', 'Assignment tracking feature is not available. Please run database migration.');
            redirect('orders/viewdocuments/' . md5($order['id']));
            return;
        }

        // Update assessment to mark assignment as done
        $update_data = array(
            'assignment_done' => 1,
            'assignment_done_date' => date('Y-m-d H:i:s'),
            'updated_on' => date('Y-m-d H:i:s')
        );

        if ($this->db->update('assessments', $update_data, ['id' => $assessment['id']])) {
            $this->session->set_flashdata('msg', 'Assignment marked as done successfully!');
        } else {
            $error = $this->db->error();
            $this->session->set_flashdata('err_msg', $error['message']);
        }

        redirect('orders/viewdocuments/' . md5($order['id']));
    }

    public function getpackage()
    {
        $user_id = $this->input->post('user_id');
        $where = array('user_id' => $user_id, 'status' => 1);
        $query = $this->db->get_where('customer_packages', $where);
        if ($query->num_rows() > 0) {
            $cpackage = $query->unbuffered_row('array');
            $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
        } else {
            $name = '';
        }
        echo $name;
    }

    public function getfirms()
    {
        $user_id = $this->input->post('user_id');
        $where = array('user_id' => $user_id, 'status' => 1, 'request !=' => 1);
        $this->db->where($where);
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get('firms');
        $options = array('' => 'Select Firm');
        if ($query->num_rows() > 0) {
            $firms = $query->result_array();
            foreach ($firms as $firm) {
                $options[$firm['id']] = $firm['name'];
            }
        }
        echo create_form_input('select', 'firm_id', "Firm", true, '', ['id' => 'firm_id'], $options);
    }

    public function getmonths()
    {
        $year = $this->input->post('year');
        $month = $this->input->post('month');
        if (empty($month)) {
            $month = '';
        }
        $options = month_dropdown($year);
        echo form_dropdown('month', $options, $month, array('class' => 'form-control', 'id' => 'month', 'required' => 'true'));
    }

    public function getyearlyreport()
    {
        $user_id = $this->input->post('user_id');
        $firm_id = $this->input->post('firm_id');
        $year = $this->input->post('year');
        if (!empty($user_id) && !empty($year)) {
            $yearval = getyearmonthvalues($year);
            $year1 = $yearval['year1'];
            $year2 = $yearval['year2'];
            $from = "$year1-04-01";
            $to = "$year2-03-31";
            $data = array();
            $where = array('user_id' => $user_id, 'status' => 1);
            $query = $this->db->get_where('customer_packages', $where);
            if ($query->num_rows() > 0) {
                $where2 = "t1.user_id='$user_id' and t1.firm_id='$firm_id' and t1.date>='$from' and t1.date<='$to'";
                $data['accountancy'] = $this->service->getturnoverswithpayment($where2);
                $turnovers = !empty($data['accountancy']) ? array_column($data['accountancy'], 'turnover') : array(0);
                $turnover = array_sum($turnovers);
                $data['total_turnover'] = $turnover;
                $cpackage = $query->unbuffered_row('array');
                $name = $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium';
                $fee_data = calculate_accountancy_fee($name, $turnover);
                $data['package'] = $fee_data['package'];
                $data['package_fee'] = $fee_data['fees'];
                $this->load->view('orders/acc_table', $data);
            } else {
                echo '<h3 class="text-danger">No Package Selected!</h3>';
            }
        }
    }

    public function saveturnover()
    {
        // Restrict access to admin and employee only
        if ($this->session->role == 'customer') {
            $this->session->set_flashdata('err_msg', 'Access Denied!');
            redirect('home/');
        }

        if ($this->input->post('saveturnover') !== NULL) {
            $data = $this->input->post();
            $this->session->set_flashdata(['user_id' => $data['user_id'], 'year' => $data['year']]);
            $where = array('user_id' => $data['user_id'], 'status' => 1);
            $query = $this->db->get_where('customer_packages', $where);
            if ($query->num_rows() > 0) {
                $cpackage = $query->unbuffered_row('array');
                $user = getuser();
                $data['added_by'] = $user['id'];
                $data['package_id'] = $cpackage['package_id'];
                $dateval = getyearmonthvalues($data['month']);
                $data['date'] = $dateval['start'];
                unset($data['id'], $data['year'], $data['month'], $data['saveturnover']);
                $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
                //print_pre($data,true);
                $result = $this->service->saveturnover($data);
                if ($result['status'] === true) {
                    $this->session->set_flashdata(['msg' => $result['message']]);
                } else {
                    $this->session->set_flashdata(['err_msg' => $result['message']]);
                }
            } else {
                $this->session->set_flashdata(['err_msg' => "Customer Does not have Active Package!"]);
            }
        } elseif ($this->input->post('updateturnover') !== NULL) {
            $data = $this->input->post();
            $this->session->set_flashdata(['user_id' => $data['user_id'], 'year' => $data['year']]);
            $user = getuser();
            $data['added_by'] = $user['id'];
            $dateval = getyearmonthvalues($data['month']);
            $data['date'] = $dateval['start'];
            $where = array('id' => $data['id']);
            unset($data['id'], $data['year'], $data['month'], $data['updateturnover']);
            $data['updated_on'] = date('Y-m-d H:i:s');
            //print_pre($data,true);
            $result = $this->service->updateturnover($data, $where);
            if ($result['status'] === true) {
                $this->session->set_flashdata(['msg' => $result['message']]);
            } else {
                $this->session->set_flashdata(['err_msg' => $result['message']]);
            }
        } elseif ($this->input->post('saveyearlyturnover') !== NULL) {
            $data = $this->input->post();
            $this->session->set_flashdata(['user_id' => $data['user_id'], 'year' => $data['year']]);
            $where = array('user_id' => $data['user_id'], 'status' => 1);
            $query = $this->db->get_where('customer_packages', $where);
            if ($query->num_rows() > 0) {
                $cpackage = $query->unbuffered_row('array');
                $user = getuser();
                $months = $data['month'];
                $turnovers = $data['turnover'];
                $due_dates = $data['due_date'];
                unset(
                    $data['id'],
                    $data['year'],
                    $data['month'],
                    $data['turnover'],
                    $data['due_date'],
                    $data['saveyearlyturnover']
                );
                foreach ($months as $key => $month) {
                    if (empty($turnovers[$key])) {
                        continue;
                    }
                    $data['added_by'] = $user['id'];
                    $data['package_id'] = $cpackage['package_id'];
                    $dateval = getyearmonthvalues($month);
                    $data['date'] = $dateval['start'];
                    $data['turnover'] = $turnovers[$key];
                    $data['due_date'] = $due_dates[$key];
                    $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
                    //print_pre($data,true);
                    $getprev = $this->db->get_where('accountancy', ['date' => $data['date'], 'user_id' => $data['user_id']]);
                    if ($getprev->num_rows() == 0) {
                        $result = $this->service->saveturnover($data);
                        //print_pre($result,true);
                        if ($result['status'] === true) {
                            $this->session->set_flashdata(['msg' => $result['message']]);
                        } else {
                            $this->session->set_flashdata(['err_msg' => $result['message']]);
                        }
                    } else {
                        $previous = $getprev->unbuffered_row('array');
                        $getpayment = $this->db->get_where(
                            'acc_payment',
                            ['user_id' => $data['user_id'], 'acc_date>=' => $data['date']]
                        );
                        if ($getpayment->num_rows() == 0) {
                            $id = $previous['id'];
                            $where = ['id' => $id];
                            unset($data['updated_on']);
                            $result = $this->service->updateturnover($data, $where);
                            if ($result['status'] === true) {
                                $this->session->set_flashdata(['msg' => $result['message']]);
                            } else {
                                $this->session->set_flashdata(['err_msg' => $result['message']]);
                            }
                        }
                    }
                }
            } else {
                $this->session->set_flashdata(['err_msg' => "Customer Does not have Active Package!"]);
            }
        } elseif ($this->input->post('savemonthlyturnover') !== NULL) {
            $user = getuser();
            $data = $this->input->post();
            $selectedYear = $data['year'];   // save before $data gets overwritten in the loop
            $allfirms = $data['firm_id'];
            $turnovers = $data['turnover'];
            $years = getyearmonthvalues($selectedYear);
            if (!empty($allfirms)) {
                foreach ($allfirms as $user_id => $firm_ids) {
                    $where = array('user_id' => $user_id, 'status' => 1);
                    $query = $this->db->get_where('customer_packages', $where);
                    if ($query->num_rows() > 0) {
                        $cpackage = $query->unbuffered_row('array');
                        foreach ($firm_ids as $firm_id) {
                            foreach ($turnovers[$user_id][$firm_id] as $key => $turnover) {
                                // Calculate date first
                                if ($key <= 8) {
                                    $month = $key + 4;
                                    $month = strlen($month) == 1 ? '0' . $month : $month;
                                    $month = $years['year1'] . $month;
                                } else {
                                    $month = $key - 8;
                                    $month = strlen($month) == 1 ? '0' . $month : $month;
                                    $month = $years['year2'] . $month;
                                }
                                $dateval = getyearmonthvalues($month);
                                $date = $dateval['start'];
                                
                                // Check if record exists
                                $getprev = $this->db->get_where('accountancy', [
                                    'date' => $date,
                                    'user_id' => $user_id,
                                    'firm_id' => $firm_id
                                ]);
                                
                                // If turnover is empty and record exists, delete it
                                if ((empty($turnover) && $turnover != 0) && $getprev->num_rows() > 0) {
                                    $previous = $getprev->unbuffered_row('array');
                                    $where = ['id' => $previous['id']];
                                    $result = $this->service->deleteturnover($where);
                                    if ($result['status'] === true) {
                                        $this->session->set_flashdata(['msg' => "Turnover Deleted Successfully!"]);
                                    } else {
                                        $this->session->set_flashdata(['err_msg' => $result['message']]);
                                    }
                                    continue;
                                }
                                
                                // Skip if empty and no record exists
                                if (empty($turnover) && $turnover != 0) {
                                    continue;
                                }
                                
                                $data = array();
                                $data['user_id'] = $user_id;
                                $data['firm_id'] = $firm_id;
                                $data['added_by'] = $user['id'];
                                $data['package_id'] = $cpackage['package_id'];
                                $data['date'] = $date;
                                $data['turnover'] = $turnover;
                                $data['due_date'] = date('Y-m-06', strtotime($dateval['start'] . ' next month'));;
                                $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
                                
                                //print_pre($data);
                                if ($getprev->num_rows() == 0) {
                                    $result = $this->service->saveturnover($data);
                                    //print_pre($result,true);
                                    if ($result['status'] === true) {
                                        $this->session->set_flashdata(['msg' => $result['message']]);
                                    } else {
                                        $this->session->set_flashdata(['err_msg' => $result['message']]);
                                    }
                                } else {
                                    $previous = $getprev->unbuffered_row('array');
                                    $getpayment = $this->db->get_where(
                                        'acc_payment',
                                        [
                                            'user_id' => $user_id,
                                            'firm_id' => $firm_id,
                                            'acc_date>=' => $data['date']
                                        ]
                                    );
                                    //if($getpayment->num_rows()==0){
                                    $id = $previous['id'];
                                    $where = ['id' => $id];
                                    unset($data['added_on']);
                                    $result = $this->service->updateturnover($data, $where);
                                    if ($result['status'] === true) {
                                        $this->session->set_flashdata(['msg' => $result['message']]);
                                    } else {
                                        $this->session->set_flashdata(['err_msg' => $result['message']]);
                                    }
                                    //}
                                }
                            }
                        }
                    }
                }
            }
            $this->session->set_flashdata(['year' => $selectedYear]);
            redirect('orders/monthlyturnover/');
        }
        redirect('orders/monthlyturnover/');
    }

    public function getturnover()
    {
        $id = $this->input->post('id');
        $where = array('id' => $id);
        $turnover = $this->service->getturnovers($where, 'single');
        if (!empty($turnover)) {
            $years = getyearly(date('Y', strtotime($turnover['date'])));
            $turnover['year'] = $years[0]['id'];
            $turnover['month'] = date('Ym', strtotime($turnover['date']));
        }
        echo json_encode($turnover);
    }

    public function deleteturnover()
    {
        $id = $this->input->post('id');
        $where = array('id' => $id);
        $turnover = $this->service->getturnovers($where, 'single');
        if (!empty($turnover)) {
            $years = getyearly(date('Y', strtotime($turnover['date'])));
            $this->session->set_flashdata(['user_id' => $turnover['user_id'], 'year' => $years[0]['id']]);
            $result = $this->service->deleteturnover($where);
            if ($result['status'] === true) {
                $this->session->set_flashdata(['msg' => $result['message']]);
            } else {
                $this->session->set_flashdata(['err_msg' => $result['message']]);
            }
        } else {
            $this->session->set_flashdata(['err_msg' => "Please Try Again!"]);
        }
    }

    public function getturnoverdata()
    {
        $year = $this->input->post('year');
        $result = array();
        if (!empty($year)) {
            $years = getyearmonthvalues($year);
            $where = array('date>=' => $years['year1'] . '-04-01', 'date<=' => $years['year2'] . '-03-31');
            $turnovers = $this->service->getturnovers($where);
            if (!empty($turnovers)) {
                foreach ($turnovers as $single) {
                    $month = date('m', strtotime($single['date']));
                    if ($month > 3) {
                        $month -= 4;
                    } else {
                        $month += 8;
                    }
                    $row = array(
                        'index1' => $single['user_id'],
                        'index2' => $single['firm_id'],
                        'monthindex' => $month,
                        'turnover' => $single['turnover']
                    );
                    $result[] = $row;
                }
            }
        }
        echo json_encode($result);
    }
}
//url_title