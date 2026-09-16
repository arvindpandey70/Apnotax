<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Home extends CI_Controller
{

    var $multiplier = 100000;

    function __construct()
    {
        parent::__construct();
        logrequest();
        //checkcookie();
    }

    public function index()
    {
        $this->triggercreditpayment();
        checklogin();
        $this->employee->generatecommission();
        $data = ['title' => 'Dashboard'];
        $data['breadcrumb'] = array("active" => "Dashboard");
        $data['nocard'] = true;
        $data['alertify'] = true;
        if ($this->session->role == 'customer') {
            $user = getuser();
            $data['user'] = $user;
            
            $this->load->model('Wallet_model', 'wallet');
            $data['available_credit_limit'] = $this->wallet->get_available_credit($user['id']);
        } elseif ($this->session->role != 'admin') {
            $user = getuser();
            $data['balances'] = $this->employee->getemployeebalance($user['emp_id']);
        }
        $this->template->load('pages', 'home', $data);
    }

    public function workreports()
    {
        checklogin();
        if ($this->session->role != 'customer') {
            redirect('home/');
        }
        $user = getuser();
        $year = $this->session->year;
        $firm_id = $this->session->firm;

        $data = ['title' => 'Work Reports'];
        $data['breadcrumb'] = array("active" => "Work Reports");
        $data['datatable'] = true;
        $data['user'] = $user;

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
        $data['workreports'] = $query->result_array();

        $this->template->load('pages', 'workreports', $data);
    }

    public function updatenotification()
    {
        $id = $this->input->post('id');
        $action = $this->input->post('action');
        $value = ($action === 'delete') ? 2 : 1;
        $notification = $this->common->getnotifications(["md5(concat('notify-',t1.id))" => $id], 'single');
        if (empty($notification)) {
            return;
        }
        $role = $this->session->role;
        if ($role === 'customer') {
            $user = getuser();
            if (empty($user['id']) || (int) $notification['user_id'] !== (int) $user['id']) {
                return;
            }
            $field = 'user_status';
        } else {
            $field = 'admin_status';
        }
        $this->common->updatenotification([$field => $value, 'id' => $notification['id']]);
    }

    public function triggerautodebit()
    {
        $this->load->library('auto_debit_service');
        $this->auto_debit_service->process_all_due_auto_debits();
    }

    /**
     * Auto-renew service packages whose expiry_date has passed.
     *
     * For each expired package (expiry_date <= today, payment_status = 0):
     *   – If wallet balance >= bill_amount → create purchase rows, generate
     *     invoice, extend expiry to the next cycle (payment_status stays 0
     *     so the cycle repeats automatically on the next expiry).
     *   – Otherwise → skip (it will appear in Pending Services for manual renewal).
     */
    private function _autoRenewExpiredPackages()
    {
        $this->load->library('auto_debit_service');
        $this->auto_debit_service->process_all_due_auto_debits();
    }

    private function _autoRenewExpiredAccountWork()
    {
        // Handled centrally in Auto_debit_service
    }

    public function savesessdata()
    {
        $year = $this->input->post('year');
        $firm = $this->input->post('firm');
        $user = getuser();
        $data['user'] = $user;
        $where = array("t1.user_id" => $user['id'], 't1.status' => 1, 't1.request!=' => 1, 't1.id' => $firm);
        $firm = $this->customer->getfirms($where, 'single');
        if (!empty($firm)) {
            $this->session->set_userdata(['year' => $year, 'firm' => $firm['id']]);
            echo 1;
        } else {
            echo 0;
        }
    }

    public function paymentresponse()
    {
        // This method was used earlier for PhonePe payment redirection.
        // Kept for backward compatibility – now simply redirects to wallet page.
        redirect('wallet/mywallet/');
    }

    public function unsubscribe()
    {
        echo "<h1>You have unsubscribed successfully</h1>";
    }

    public function imager()
    {
        $path = './assets/images/contact-img.webp';
        $path = file_url('/assets/images/contact-img.webp');
        $path = file_url('/images/slider1.jpg');

        $this->load->library('imager');
        //$result=$this->imager->checkSupportedFormat('webp');
        //var_dump($result);
        /*
        $result=$this->imager->readImage($path);
        var_dump($result);*/
        //$result=$this->imager->createImage();
        //var_dump($result);
        //$images=array('images/about.webp','images/about-bk.webp','images/business.png');
        //$result=$this->imager->createAnimationwithimage($images);
        //var_dump($result);

        //$result=$this->imager->createAnimation();
        //var_dump($result);

        //$result=$this->imager->getImageDimensions($path);
        //var_dump($result);

        //$result=$this->imager->readColors($path);
        //var_dump($result);

        //$result=$this->imager->encodeImage($path);
        //var_dump($result);

        //$result=$this->imager->encodeImageByMediaType($path);
        //var_dump($result);

        //$result=$this->imager->encodeImageByPath($path);
        //var_dump($result);

        //$result=$this->imager->encodeImageByExtension($path);
        //var_dump($result);

        //$result=$this->imager->encodeImageShortcut($path);
        //var_dump($result);

        /*$path='./new-image.png';
        $image=$this->imager->readImage($path);
        $result=$this->imager->saveImage($image,'./new-image.jpg');
        var_dump($result);*/

        /*$path='./new-image.png';
        $result=$this->imager->resizeImage($path);
        var_dump($result);*/

        $path = './new-image.png';
        $result = $this->imager->scaleImage($path);
        var_dump($result);
    }

    public function image()
    {
        $letter = !empty($this->input->get('letter')) ? $this->input->get('letter') : 'P';
        create_letter_image($letter);
    }

    public function editpassword()
    {
        $getuser = $this->account->getuser(array("md5(id)" => $this->session->user));
        if ($getuser['status'] === true) {
            $data['user'] = $getuser['user'];
        } else {
            redirect('home/');
        }
        $data['title'] = "Edit Password";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb'] = array();
        $data['alertify'] = true;
        $this->template->load('pages', 'editpassword', $data);
    }

    public function updatepassword()
    {
        if ($this->input->post('updatepassword') !== NULL) {
            $password = $this->input->post('password');
            $repassword = $this->input->post('repassword');
            $user = $this->session->user;
            if ($password == $repassword) {
                $result = $this->account->updatepassword(array("password" => $password), array("md5(id)" => $user));
                if ($result['status'] === true) {
                    $this->session->set_flashdata('msg', $result['message']);
                } else {
                    $error = $result['message'];
                    $this->session->set_flashdata('err_msg', $error);
                }
            } else {
                $this->session->set_flashdata('err_msg', "Password Do not Match!");
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    public function testphonepe()
    {
        $amount = 100;
        $mobile = 7739576693;
        $redirecturl = base_url('home/redirecturl');
        $callbackurl = base_url('home/callbackurl');
        //$callbackurl='https://webhook.site/7112c20b-8fec-4fef-9cb7-b61656414bfa';
        $transaction_id = 'PHPSDK' . date("ymdHis") . "payPageTest";
        $this->session->set_userdata('transaction_id', $transaction_id);
        $data = array(
            'mobile' => $mobile,
            'amount' => $amount,
            'callbackurl' => $callbackurl,
            'redirecturl' => $redirecturl,
            'user_id' => random_string('alnum', 16),
            'transaction_id' => $transaction_id
        );
        $this->load->helper('phonepe');
        $url = createTransaction($data);
        redirect($url);
    }

    public function redirecturl()
    {
        $result = $this->input->post();
        print_pre($result);
        $result = $this->input->get();
        print_pre($result);
        $result = $this->input->cookie();
        print_pre($result);
        //$result=$this->input->server();
        //print_pre($result);
        $result = $this->input->raw_input_stream;
        print_pre($result);
        $result = $this->input->request_headers();
        print_pre($result);
    }

    public function callbackurl()
    {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1;node=block");
        header("X-XSS-Type-Options: nosniff");
        // Retrieve the raw POST data
        $postData = file_get_contents("php://input");

        // Parse JSON data (if applicable)
        $decodedData = json_decode($postData, true);

        // Log the raw POST data
        file_put_contents("./webhook_log.txt", $postData . PHP_EOL, FILE_APPEND);

        // Log the decoded data (if applicable)
        if ($decodedData !== null) {
            file_put_contents("./webhook_log.txt", print_r($decodedData, true) . PHP_EOL, FILE_APPEND);
        }

        // Your webhook logic goes here
        // Handle the incoming data as needed

        // Respond to the webhook provider (optional)
        echo "Webhook received successfully.";
    }

    public function payu_success()
    {
        // Handle PayU payment success callback - No login required
        $this->load->library('Payu_lib');
        $this->load->model('Wallet_model', 'wallet');

        $post_data = $this->input->post();
        $this->restorePayuWalletSessionFromPost($post_data);

        if (empty($post_data)) {
            $this->load->view('payment/payu_result', [
                'is_success' => false,
                'title' => 'Payment Failed',
                'message' => 'Invalid payment response. Please contact support.',
                'details' => [],
                'redirect_url' => $this->walletDashboardUrl(),
                'redirect_seconds' => 6,
            ]);
            return;
        }

        // Verify hash
        $is_valid = $this->payu_lib->verify_hash($post_data);
        
        if (!$is_valid) {
            $this->load->view('payment/payu_result', [
                'is_success' => false,
                'title' => 'Payment Verification Failed',
                'message' => 'Payment verification failed. Please contact support.',
                'details' => $this->buildPayuDisplayDetails($post_data),
                'redirect_url' => $this->walletDashboardUrl(),
                'redirect_seconds' => 6,
            ]);
            return;
        }

        $status = isset($post_data['status']) ? strtolower($post_data['status']) : '';
        $txnid = isset($post_data['txnid']) ? $post_data['txnid'] : '';
        $merchant_transaction_id = $txnid;

        if ($status == 'success') {
            // Payment successful
            $payment_details = json_encode($post_data);
            
            $wallet = $this->wallet->getwallet(['merchant_transaction_id' => $merchant_transaction_id], 'single');
            
            if (!empty($wallet)) {
                $data = ['status' => 1, 'payment_details' => $payment_details];
                $result = $this->wallet->updatepayment($data, ['id' => $wallet['id']]);
                
                if (!empty($result['status'])) {
                    $message = !empty($result['message']) ? $result['message'] : 'Wallet recharged successfully.';
                    $this->load->view('payment/payu_result', [
                        'is_success' => true,
                        'title' => 'Payment Successful',
                        'message' => $message,
                        'details' => $this->buildPayuDisplayDetails($post_data),
                        'redirect_url' => $this->walletDashboardUrl(),
                        'redirect_seconds' => 6,
                    ]);
                } else {
                    $error_msg = !empty($result['message']) ? $result['message'] : 'Payment captured but wallet update failed. Please contact support.';
                    $this->load->view('payment/payu_result', [
                        'is_success' => false,
                        'title' => 'Payment Captured, Wallet Update Failed',
                        'message' => $error_msg,
                        'details' => $this->buildPayuDisplayDetails($post_data),
                        'redirect_url' => $this->walletDashboardUrl(),
                        'redirect_seconds' => 6,
                    ]);
                }
            } else {
                $this->load->view('payment/payu_result', [
                    'is_success' => false,
                    'title' => 'Payment Captured, Transaction Not Found',
                    'message' => 'Payment captured but transaction not found. Please contact support.',
                    'details' => $this->buildPayuDisplayDetails($post_data),
                    'redirect_url' => $this->walletDashboardUrl(),
                    'redirect_seconds' => 6,
                ]);
            }
        } else {
            $this->load->view('payment/payu_result', [
                'is_success' => false,
                'title' => 'Payment Failed',
                'message' => 'Payment failed or cancelled. Please try again.',
                'details' => $this->buildPayuDisplayDetails($post_data),
                'redirect_url' => $this->walletDashboardUrl(),
                'redirect_seconds' => 6,
            ]);
        }
    }

    public function payu_failure()
    {
        // Handle PayU payment failure callback - No login required
        $post_data = $this->input->post();
        $this->restorePayuWalletSessionFromPost($post_data);

        $error_msg = isset($post_data['error_Message']) ? $post_data['error_Message'] : 'Payment failed or cancelled. Please try again.';

        $this->load->view('payment/payu_result', [
            'is_success' => false,
            'title' => 'Payment Failed',
            'message' => $error_msg,
            'details' => $this->buildPayuDisplayDetails($post_data),
            'redirect_url' => $this->walletDashboardUrl(),
            'redirect_seconds' => 6,
        ]);
    }

    /**
     * Customer wallet URL (see routes: mywallet -> wallet/mywallet).
     */
    private function walletDashboardUrl()
    {
        return base_url('mywallet/');
    }

    /**
     * PayU returns via cross-site POST; session cookie is often not sent, so checklogin()
     * would send the user to "/". Re-establish session when txn matches a pending wallet row.
     */
    private function restorePayuWalletSessionFromPost($post_data)
    {
        if (empty($post_data) || !is_array($post_data)) {
            return;
        }
        if ($this->session->user !== NULL && $this->session->project == PROJECT_NAME) {
            return;
        }
        $udf2 = isset($post_data['udf2']) ? (string) $post_data['udf2'] : '';
        if ($udf2 !== 'wallet_topup') {
            return;
        }
        $txnid = isset($post_data['txnid']) ? (string) $post_data['txnid'] : '';
        if ($txnid === '' || empty($post_data['udf1'])) {
            return;
        }
        $user_id = (int) $post_data['udf1'];
        if ($user_id < 1) {
            return;
        }
        $wallet = $this->wallet->getwallet(['merchant_transaction_id' => $txnid], 'single');
        if (empty($wallet) || (int) $wallet['user_id'] !== $user_id) {
            return;
        }
        $result = $this->account->getuser(['id' => $user_id]);
        if ($result['status'] !== true || empty($result['user'])) {
            return;
        }
        $user = $result['user'];
        if (($user['role'] ?? '') !== 'customer') {
            return;
        }
        $data = [
            'user' => md5($user['id']),
            'name' => $user['name'],
            'emp_id' => $user['emp_id'],
            'role' => $user['role'],
            'project' => PROJECT_NAME,
        ];
        $this->session->set_userdata($data);
    }

    private function buildPayuDisplayDetails($post_data = [])
    {
        if (empty($post_data) || !is_array($post_data)) {
            return [];
        }

        $fields = [
            'txnid' => 'Transaction ID',
            'mihpayid' => 'PayU Payment ID',
            'status' => 'Status',
            'amount' => 'Amount',
            'mode' => 'Payment Mode',
            'bank_ref_num' => 'Bank Ref No',
            'bankcode' => 'Bank Code',
            'productinfo' => 'Product Info',
            'firstname' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'addedon' => 'Added On',
            'error' => 'Error Code',
            'error_Message' => 'Error Message',
        ];

        $details = [];
        foreach ($fields as $key => $label) {
            if (isset($post_data[$key]) && $post_data[$key] !== '') {
                $details[] = [
                    'label' => $label,
                    'value' => (string) $post_data[$key],
                ];
            }
        }

        return $details;
    }

    public function redirecturl2()
    {
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1;node=block");
        header("X-XSS-Type-Options: nosniff");
        $rawdata = file_get_contents("php://input");
        $data = json_decode($rawdata, true);
        print_pre($data);
        $this->load->helper('phonepe');
        $result = checkPaymentStatus($this->session->transaction_id);
        print_pre($result);
    }

    public function testmail()
    {
        $email = $this->input->get('email');
        $agentname = $adminname = "Atal";
        $agentemail = $adminemail = ($email === NULL) ? ["atal.prateek@tripledotss.com"] : ["atal.prateek@tripledotss.com", $email];
        $name = "Lead Name";
        $mobile = $mobile = "Lead Mobile";
        $service = "Lead Service";
        $source = "Lead Source";
        if ($this->input->get('type') == 'admin') {
            $this->adminmail($adminname, $adminemail, $name, $mobile, $service, $source);
        }
        if ($this->input->get('type') == 'agent') {
            $this->agentmail($agentname, $agentemail, $name, $mobile, $service);
        }
    }

    private function adminmail($adminname, $adminemail, $name, $mobile, $service, $source)
    {
        $this->load->helper('email');
        $subject = "Lead Assigned to Admin";
        $message = "<p>Hello " . htmlspecialchars($adminname) . ",</p>"
            . "<p>A new lead has been assigned.</p>"
            . "<p><strong>Name:</strong> " . htmlspecialchars($name) . "<br>"
            . "<strong>Mobile:</strong> " . htmlspecialchars($mobile) . "<br>"
            . "<strong>Service:</strong> " . htmlspecialchars($service) . "<br>"
            . "<strong>Source:</strong> " . htmlspecialchars($source) . "</p>";
        return sendemail($adminemail, $subject, $message);
    }

    private function agentmail($agentname, $agentemail, $name, $mobile, $service)
    {
        $this->load->helper('email');
        $subject = "Lead Assigned to Agent";
        $message = "<p>Hello " . htmlspecialchars($agentname) . ",</p>"
            . "<p>A new lead has been assigned.</p>"
            . "<p><strong>Name:</strong> " . htmlspecialchars($name) . "<br>"
            . "<strong>Mobile:</strong> " . htmlspecialchars($mobile) . "<br>"
            . "<strong>Service:</strong> " . htmlspecialchars($service) . "</p>";
        return sendemail($agentemail, $subject, $message);
    }

    public function runquery()
    {
        $query = array(
            "ALTER TABLE `tf_acc_payment` ADD `year` VARCHAR(20) NOT NULL AFTER `firm_id`;"
        );
        foreach ($query as $sql) {
            if (!$this->db->query($sql)) {
                print_r($this->db->error());
            }
        }
    }

    public function clearlogs()
    {
        $query = array(
            'TRUNCATE `tf_request_log`;'
        );
        foreach ($query as $sql) {
            if (!$this->db->query($sql)) {
                print_r($this->db->error());
            }
        }
    }

    public function matchcolumns()
    {
        $tables = $this->db->query("show tables;")->result_array();
        foreach ($tables as $table) {
            $tablename = $table['Tables_in_' . DB_NAME];
            $columns = $this->db->query("DESC $tablename;")->result_array();
            echo "<h1>$tablename</h1>";
            echo "<table border='1' cellspacing='0' cellpadding='5'>";
            echo "<tr>";
            foreach ($columns[0] as $key => $value) {
                echo "<td>$key</td>";
            }
            echo "</tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                foreach ($column as $key => $value) {
                    echo "<td>$value</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    public function alldata($token = '')
    {
        $this->load->library('alldata');
        $this->alldata->viewall($token);
    }

    public function gettable()
    {
        $this->load->library('alldata');
        $this->alldata->gettable();
    }

    public function updatedata()
    {
        $this->load->library('alldata');
        $this->alldata->updatedata();
    }
    public function triggercreditpayment()
    {
        // Auto deduct from wallet on the 6th or later of the month
        if (date('d') >= 6) {
            $first_day_of_month = date('Y-m-01 00:00:00');
            
            $this->db->where('type', 'Credit limit');
            $this->db->where('added_on <', $first_day_of_month);
            $purchases = $this->db->get('purchases')->result_array();
            
            if (!empty($purchases)) {
                foreach ($purchases as $p) {
                    // Update type so it gets picked up by wallet calculation (deducting from wallet)
                    // and won't be processed again next time
                    $this->db->update('purchases', ['type' => 'Credit limit Paid'], ['id' => $p['id']]);
                    
                    // Add notification
                    $amount = number_format($p['amount'], 2);
                    $this->common->savenotification(array(
                        "type" => "credit_payment",
                        "user_id" => $p['user_id'],
                        'order_id' => $p['id'],
                        'message' => '₹' . $amount . ' automatically deducted from your wallet for Credit Limit usage on ' . date('d M Y', strtotime($p['added_on'])) . '.',
                        'added_on' => date('Y-m-d H:i:s'),
                        'updated_on' => date('Y-m-d H:i:s')
                    ));
                }
            }
        }
    }
}
