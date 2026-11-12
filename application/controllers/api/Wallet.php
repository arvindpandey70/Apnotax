<?php
defined('BASEPATH') or exit('No direct script access allowed');
//include Rest Controller library
use chriskacerguis\RestServer\RestController;

class Wallet extends RestController
{
    function __construct()
    {
        parent::__construct();
        logrequest();
    }

    public function getwallet_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $balance = $this->wallet->getwalletbalance($user['id']);;
                $this->response([
                    'status' => true,
                    'balance' => $balance
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


    public function addtowallet_post()
    {
        $token = $this->post('token');
        $amount = $this->post('amount');
        if (!empty($token) && !empty($amount)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $date = date('Y-m-d');
                $data = array('user_id' => $user['id'], 'date' => $date, 'amount' => $amount);
                $result = $this->wallet->addtowallet($data);
                if ($result['status'] === true) {
                    $this->response($result, RestController::HTTP_OK);
                } else {
                    $message = $result['message'];
                    $this->response([
                        'status' => false,
                        'message' => $message
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

    public function initiatepayment_post()
    {
        $token = $this->post('token');
        $amount = $this->post('amount');
        $merchant_transaction_id = $this->post('merchant_transaction_id');

        if (!empty($token) && !empty($amount) && !empty($merchant_transaction_id)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                // Use PayU for initiating payment
                $this->load->library('Payu_lib');

                // Get user details
                $customer = $this->customer->getcustomers(['t1.user_id' => $user['id']], 'single');
                $firstname = !empty($customer['name']) ? $customer['name'] : 'Customer';
                $email = !empty($customer['email']) ? $customer['email'] : $user['email'];
                $phone = !empty($customer['mobile']) ? $customer['mobile'] : '';

                $udf = [
                    'udf1' => $user['id'],
                    'udf2' => 'wallet_topup',
                    'udf3' => $merchant_transaction_id,
                    'udf4' => 'api',
                ];

                $paymentParams = $this->payu_lib->prepare_payment_params(
                    $amount,
                    $merchant_transaction_id,
                    'Wallet Top-up',
                    $firstname,
                    $email,
                    $phone,
                    $udf
                );

                $this->response([
                    'status'                 => true,
                    'gateway'                => 'payu',
                    'payment_url'            => $this->payu_lib->get_payment_url(),
                    'payment_params'         => $paymentParams,
                    'merchant_transaction_id' => $merchant_transaction_id,
                    'amount'                 => $amount,
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

    public function updatepayment_post()
    {
        $token = $this->post('token');
        $details = $this->post('details');
        if (!empty($token) && !empty($details)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $array = json_decode($details, true);
                if ($array['code'] == 'PAYMENT_SUCCESS') {
                    $data = array('status' => 1, 'payment_details' => $details);
                    $where = array('merchant_transaction_id' => $array['data']['merchantTransactionId'], 'user_id' => $user['id']);
                    $wallet = $this->wallet->getwallet($where, 'single');
                    if (!empty($wallet)) {
                        $result = $this->wallet->updatepayment($data, $where);
                        if ($result['status'] === true) {
                            $amt = isset($wallet['amount']) ? (float) $wallet['amount'] : 0;
                            $this->common->savenotification(array(
                                'user_id' => (int) $user['id'],
                                'type' => 'payment',
                                'message' => 'Wallet top-up of ₹' . number_format($amt, 2) . ' was successful.',
                            ));
                            $this->response($result, RestController::HTTP_OK);
                        } else {
                            $message = $result['message'];
                            $this->response([
                                'status' => false,
                                'message' => $message
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "Transaction not Found! Try Again!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $this->common->savenotification(array(
                        'user_id' => (int) $user['id'],
                        'type' => 'payment',
                        'message' => 'Wallet payment could not be completed. Please try again.',
                    ));
                    $this->response([
                        'status' => false,
                        'message' => "Payment Failed! Try Again!"
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

    public function gettransactions_post()
    {
        $token = $this->post('token');
        if (!empty($token)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where1 = array('user_id' => $user['id'], 'status' => 1);
                $where2 = array('t1.user_id' => $user['id']);
                $order_by = "added_on desc";
                $transactions = $this->wallet->gettransactions($where1, $where2, $order_by);
                if (!empty($transactions)) {
                    $this->response([
                        'status' => TRUE,
                        'transactions' => $transactions
                    ], RestController::HTTP_OK);
                } else {
                    $message = "No Transactions Available!";
                    $this->response([
                        'status' => false,
                        'message' => $message
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

    public function getaccountancydues_post()
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
                        $total_fees = $total_paid = $total_penalty = $total_days = 0;
                        $outstanding = $total = 0;
                        $turnover_calc = calculate_turnover_monthly_fees($accountancy, $name);
                        $monthly_fees = $turnover_calc['monthly_fees'];
                        foreach ($accountancy as $idx => $single) {
                            $days = $paid = $penalty = 0;
                            $paid = $single['paid'];
                            $outstanding = $total;
                            $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;
                            $balance = $outstanding + $acc_fees;
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
                            if ($balance < 0) {
                                $balance = 0;
                            }
                            $total = $balance + $penalty;
                            $total_fees += $acc_fees;
                            $total_paid += $paid;
                            $month = $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--';
                            $due_date = $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--';
                        }
                        $total_dues = $total_fees + $total_penalty - $total_paid;
                        $this->response([
                            'status' => true,
                            'total_dues' => $total_dues
                        ], RestController::HTTP_OK);
                    } else {
                        $this->response([
                            'status' => false,
                            'message' => "No Dues!"
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $message = "No Package Selected!";
                    $this->response([
                        'status' => false,
                        'message' => $message
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

    public function makeaccountancypayment_post()
    {
        $token = $this->post('token');
        $year = $this->post('year');
        $firm_id = $this->post('firm_id');
        $amount = $this->post('amount');
        if (!empty($token) && !empty($year) && !empty($firm_id) && !empty($amount)) {
            $user = $this->account->verify_token($token);
            if (!empty($user) && is_array($user) && $user['role'] == 'customer') {
                $where = array('user_id' => $user['id'], 'status' => 1);

                $balance = $this->wallet->getwalletbalance($user['id']);

                if ($balance >= $amount) {
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
                        $currentmonth = '';
                        if (!empty($accountancy)) {
                            $total_fees = $total_paid = $total_penalty = $total_days = 0;
                            $outstanding = $total = 0;
                            $turnover_calc = calculate_turnover_monthly_fees($accountancy, $name);
                            $monthly_fees = $turnover_calc['monthly_fees'];
                            foreach ($accountancy as $idx => $single) {
                                $days = $paid = $penalty = 0;
                                $paid = $single['paid'];
                                $outstanding = $total;
                                $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;
                                if ($single['date'] != '') {
                                    $currentmonth = $single['date'];
                                }
                                $balance = $outstanding + $acc_fees;
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
                                if ($balance < 0) {
                                    $balance = 0;
                                }
                                $total = $balance + $penalty;
                                $total_fees += $acc_fees;
                                $total_paid += $paid;
                                $month = $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--';
                                $due_date = $single['due_date'] != '' ? date('d-m-Y', strtotime($single['due_date'])) : '--';
                            }
                            $total_dues = $total_fees + $total_penalty - $total_paid;
                            $data = array(
                                'date' => $date,
                                'user_id' => $user_id,
                                'firm_id' => $firm_id,
                                'acc_date' => $currentmonth,
                                'amount' => $amount
                            );
                            $result = $this->wallet->makeaccountancypayment($data);
                            if ($result['status'] == true) {
                                $this->common->savenotification(array(
                                    'user_id' => (int) $user_id,
                                    'type' => 'payment',
                                    'message' => 'Accountancy payment of ₹' . number_format((float) $amount, 2) . ' was successful.',
                                ));
                                $this->response([
                                    'status' => true,
                                    'message' => $result['message']
                                ], RestController::HTTP_OK);
                            } else {
                                $this->response([
                                    'status' => true,
                                    'message' => $result['message']
                                ], RestController::HTTP_OK);
                            }
                        } else {
                            $this->response([
                                'status' => false,
                                'message' => "No Dues!"
                            ], RestController::HTTP_OK);
                        }
                    } else {
                        $message = "No Package Selected!";
                        $this->response([
                            'status' => false,
                            'message' => $message
                        ], RestController::HTTP_OK);
                    }
                } else {
                    $remaining = $amount - $balance;
                    $this->response([
                        'status' => false,
                        'amount' => $remaining,
                        'message' => "Add to Wallet"
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
