<?php
class Wallet_model extends CI_Model{
	
	function __construct(){
		parent::__construct(); 
		$this->db->db_debug = false;
	}
	
    public function addtowallet($data){
        $merchant_transaction_id=generatetransactionid();
        $datetime=date('Y-m-d H:i:s');
        $data['merchant_transaction_id']=$merchant_transaction_id;
        $data['merchant_user_id']=$data['user_id'];
        $data['added_on']=$data['updated_on']=$datetime;
        if($this->db->insert("wallet",$data)){
            return array("status"=>true,"message"=>"Transaction Created! Proceed to Payment!",
                         'merchant_transaction_id'=>$merchant_transaction_id,'merchant_user_id'=>$data['merchant_user_id']);
        }
        else{
            $error=$this->db->error();
            return array("status"=>false,"message"=>$error['message']);
        }
    }
    
    public function updatepayment($data,$where){
        $where2=$where;
        $where2['status']=0;
        $datetime=date('Y-m-d H:i:s');
        $data['updated_on']=$datetime;
        if($this->db->get_where('wallet',$where2)->num_rows()!=0){
            if($this->db->update("wallet",$data,$where)){
                // Fetch user_id to trigger pending auto-debits collection
                $wrow = $this->db->get_where('wallet', $where)->unbuffered_row('array');
                if (!empty($wrow['user_id'])) {
                    $this->load->library('credit_service');
                    $this->credit_service->process_wallet_recovery($wrow['user_id'], $wrow['id'] ?? null);
                    $this->load->library('auto_debit_service');
                    $this->auto_debit_service->process_user_pending_auto_debits($wrow['user_id']);
                }
                return array("status"=>true,"message"=>"Payment Successful! Wallet Amount Updated!");
            }
            else{
                $error=$this->db->error();
                return array("status"=>false,"message"=>$error['message']);
            }
        }
        else{
            return array("status"=>false,"message"=>"Payment Already Approved!");
        }
    }
    
    public function getwallet($where=array(),$type="all"){
        $this->db->where($where);
        $query=$this->db->get("wallet");
        if($type=='all'){
            $array=$query->result_array();
        }
        else{
            $array=$query->unbuffered_row('array');
        }
        return $array;
    }
    
    public function getwalletbalance($user_id){
        $balance=0;
        $this->db->select_sum('amount');
        $this->db->where(['user_id'=>$user_id,'status'=>1]);
        $wallet=$this->db->get("wallet")->unbuffered_row()->amount;
        $wallet=!empty($wallet)?$wallet:0;
        $balance+=$wallet;
        
        $this->db->select_sum('amount');
        $this->db->where(['user_id'=>$user_id, 'type !=' => 'Credit limit']);
        $purchases=$this->db->get("purchases")->unbuffered_row()->amount;
        $purchases=!empty($purchases)?$purchases:0;
        $balance-=$purchases;
        
        $this->db->select_sum('amount');
        $this->db->where(['user_id'=>$user_id, 'payment_mode !=' => 'Credit Limit']);
        $acc_payment=$this->db->get("acc_payment")->unbuffered_row()->amount;
        $acc_payment=!empty($acc_payment)?$acc_payment:0;
        $balance-=$acc_payment;
        
        // Subtract security deposit
        $security_deposit=$this->getsecuritydeposit($user_id);
        $balance-=$security_deposit;
        
        // Round to 2 decimal places to avoid floating-point precision issues
        return round($balance, 2);
    }
    
    public function get_used_credit($user_id){
        $this->db->select_sum('amount');
        $this->db->where(['user_id' => $user_id, 'type' => 'Credit limit']);
        $this->db->where('status >=', 0);
        $res1 = $this->db->get("purchases");
        $row1 = $res1 ? $res1->unbuffered_row() : null;
        $purchases_credit = !empty($row1) && isset($row1->amount) ? (float)$row1->amount : 0.00;

        $this->db->select_sum('amount');
        $this->db->where(['user_id' => $user_id, 'payment_mode' => 'Credit Limit', 'status' => 1]);
        $res2 = $this->db->get("acc_payment");
        $row2 = $res2 ? $res2->unbuffered_row() : null;
        $acc_credit = !empty($row2) && isset($row2->amount) ? (float)$row2->amount : 0.00;

        // Also include manual ledger payments and receipts
        $this->db->select_sum('amount');
        $this->db->where(['user_id' => $user_id, 'type' => 'payment']);
        $res3 = $this->db->get("credit_limit_ledger");
        $row3 = $res3 ? $res3->unbuffered_row() : null;
        $ledger_payment = !empty($row3) && isset($row3->amount) ? (float)$row3->amount : 0.00;

        $this->db->select_sum('amount');
        $this->db->where(['user_id' => $user_id, 'type' => 'receipt']);
        $res4 = $this->db->get("credit_limit_ledger");
        $row4 = $res4 ? $res4->unbuffered_row() : null;
        $ledger_receipt = !empty($row4) && isset($row4->amount) ? (float)$row4->amount : 0.00;

        $net_used = $purchases_credit + $acc_credit + $ledger_payment - $ledger_receipt;
        return max(0.00, round($net_used, 2));
    }

    public function get_credit_ledger($user_id, $from_date = null, $to_date = null){
        $res_cust = $this->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;
        $credit_percent = $this->get_credit_percent($user_id);
        if ($credit_percent <= 0) {
            $credit_percent = 2.00;
        }
        $daily_rate = ($credit_percent / 100) / 30; // 30 day = percent value

        $day_payments = [];
        $day_receipts = [];
        $earliest_date = null;
        $latest_date = null;

        // a) tf_purchases
        $purchases = $this->db->select('id, date, amount, service, added_on')
                              ->where('user_id', $user_id)
                              ->where_in('type', ['Credit limit', 'Credit limit Paid'])
                              ->where('status >=', 0)
                              ->get('purchases')
                              ->result_array();
        if (!empty($purchases)) {
            foreach ($purchases as $p) {
                $d = !empty($p['date']) ? $p['date'] : date('Y-m-d', strtotime($p['added_on']));
                $amt = (float)$p['amount'];
                if ($amt > 0) {
                    $day_payments[$d][] = [
                        'source' => 'purchase',
                        'id' => $p['id'],
                        'amount' => $amt,
                        'remarks' => $p['service']
                    ];
                    if (!$earliest_date || $d < $earliest_date) $earliest_date = $d;
                    if (!$latest_date || $d > $latest_date) $latest_date = $d;
                }
            }
        }

        // b) tf_acc_payment
        $acc_payments = $this->db->select('id, date, amount, acc_date, added_on')
                                 ->where('user_id', $user_id)
                                 ->where('payment_mode', 'Credit Limit')
                                 ->where('status', 1)
                                 ->get('acc_payment')
                                 ->result_array();
        if (!empty($acc_payments)) {
            foreach ($acc_payments as $ap) {
                $d = !empty($ap['date']) ? $ap['date'] : date('Y-m-d', strtotime($ap['added_on']));
                $amt = (float)$ap['amount'];
                if ($amt > 0) {
                    $day_payments[$d][] = [
                        'source' => 'acc_payment',
                        'id' => $ap['id'],
                        'amount' => $amt,
                        'remarks' => 'Accountancy (' . $ap['acc_date'] . ')'
                    ];
                    if (!$earliest_date || $d < $earliest_date) $earliest_date = $d;
                    if (!$latest_date || $d > $latest_date) $latest_date = $d;
                }
            }
        }

        // c) tf_credit_limit_ledger
        $ledger_entries = $this->db->select('id, date, type, amount, remarks, added_on')
                                   ->where('user_id', $user_id)
                                   ->order_by('date', 'ASC')
                                   ->get('credit_limit_ledger')
                                   ->result_array();
        if (!empty($ledger_entries)) {
            foreach ($ledger_entries as $le) {
                $d = !empty($le['date']) ? $le['date'] : date('Y-m-d', strtotime($le['added_on']));
                $amt = (float)$le['amount'];
                if ($amt > 0) {
                    if ($le['type'] == 'receipt') {
                        $day_receipts[$d][] = [
                            'source' => 'ledger',
                            'id' => $le['id'],
                            'amount' => $amt,
                            'remarks' => $le['remarks']
                        ];
                    } else {
                        $day_payments[$d][] = [
                            'source' => 'ledger',
                            'id' => $le['id'],
                            'amount' => $amt,
                            'remarks' => $le['remarks']
                        ];
                    }
                    if (!$earliest_date || $d < $earliest_date) $earliest_date = $d;
                    if (!$latest_date || $d > $latest_date) $latest_date = $d;
                }
            }
        }

        // Determine start and end date
        $current_fy_start = (date('m') >= 4) ? date('Y-04-01') : date((date('Y') - 1) . '-04-01');
        $calc_start_date = $earliest_date ? min($earliest_date, $current_fy_start) : $current_fy_start;

        if (!empty($from_date)) {
            $calc_start_date = min($calc_start_date, $from_date);
        }

        $calc_end_date = date('Y-m-d');
        if ($latest_date && $latest_date > $calc_end_date) {
            $calc_end_date = $latest_date;
        }
        if (!empty($to_date)) {
            $calc_end_date = max($calc_end_date, $to_date);
        }

        $current = strtotime($calc_start_date);
        $end = strtotime($calc_end_date);
        $prev_total = 0.0;
        $all_rows = [];
        $total_receipts = 0.0;
        $total_payments = 0.0;
        $total_panel_interest = 0.0;

        $today_str = date('Y-m-d');

        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $outstanding = $prev_total;

            $receipt = 0.0;
            if (isset($day_receipts[$d])) {
                foreach ($day_receipts[$d] as $r) {
                    $receipt += (float)$r['amount'];
                }
            }

            $payment = 0.0;
            if (isset($day_payments[$d])) {
                foreach ($day_payments[$d] as $p) {
                    $payment += (float)$p['amount'];
                }
            }

            $total = $outstanding + $payment;
            $balance = max(0.0, $total - $receipt);

            $panel_interest = 0.0;
            if ($d <= $today_str && $balance > 0) {
                $panel_interest = round($balance * $daily_rate, 4);
            }

            $closing_total = round($balance + $panel_interest, 2);

            $all_rows[$d] = [
                'date' => $d,
                'outstanding' => $outstanding,
                'receipt' => $receipt,
                'total' => $total,
                'payment' => $payment,
                'balance' => $balance,
                'panel_interest' => $panel_interest,
                'closing_total' => $closing_total,
                'receipt_items' => isset($day_receipts[$d]) ? $day_receipts[$d] : [],
                'payment_items' => isset($day_payments[$d]) ? $day_payments[$d] : []
            ];

            $total_receipts += $receipt;
            $total_payments += $payment;
            $total_panel_interest += $panel_interest;

            $prev_total = $closing_total;
            $current = strtotime('+1 day', $current);
        }

        $display_from = !empty($from_date) ? $from_date : $calc_start_date;
        $display_to = !empty($to_date) ? $to_date : $calc_end_date;

        $filtered_rows = [];
        foreach ($all_rows as $d => $row) {
            if ($d >= $display_from && $d <= $display_to) {
                $filtered_rows[] = $row;
            }
        }

        $latest_balance = $prev_total;
        $available_credit = max(0.0, $credit_limit - $latest_balance);
        if ($available_credit < 0) $available_credit = 0.0;

        return [
            'customer' => $customer,
            'credit_limit' => $credit_limit,
            'credit_percent' => $credit_percent,
            'daily_rate' => $daily_rate,
            'from_date' => $display_from,
            'to_date' => $display_to,
            'rows' => $filtered_rows,
            'totals' => [
                'receipt' => $total_receipts,
                'payment' => $total_payments,
                'panel_interest' => $total_panel_interest
            ],
            'latest_balance' => $latest_balance,
            'available_credit' => $available_credit
        ];
    }

    public function get_credit_percent($user_id){
        $res_cust = $this->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        if (!empty($customer) && isset($customer['credit_percent']) && $customer['credit_percent'] !== '' && $customer['credit_percent'] !== null) {
            return (float)$customer['credit_percent'];
        }
        $res_glob = $this->db->get_where('credit_limit_percentage', ['status' => 1]);
        $global = $res_glob ? $res_glob->unbuffered_row('array') : null;
        if (!empty($global) && isset($global['percent'])) {
            return (float)$global['percent'];
        }
        return 0.00;
    }

    public function get_used_credit_tax($user_id){
        $used_credit = $this->get_used_credit($user_id);
        $percent = $this->get_credit_percent($user_id);
        return round(($used_credit * $percent) / 100, 2);
    }

    public function get_total_used_credit_with_tax($user_id){
        $used_credit = $this->get_used_credit($user_id);
        $tax = $this->get_used_credit_tax($user_id);
        return round($used_credit + $tax, 2);
    }

    public function get_available_credit($user_id){
        $res_cust = $this->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;
        $total_used_with_tax = $this->get_total_used_credit_with_tax($user_id);
        $available = $credit_limit - $total_used_with_tax;
        return max(0.00, round($available, 2));
    }
    
    public function getsecuritydeposit($user_id){
        $this->db->select_sum('amount');
        $this->db->where(['user_id'=>$user_id]);
        $res_sec = $this->db->get("security_deposit");
        $row_sec = $res_sec ? $res_sec->unbuffered_row() : null;
        $security = !empty($row_sec) && isset($row_sec->amount) ? $row_sec->amount : 0;
        return !empty($security)?$security:0;
    }
    
    public function addsecuritydeposit($data){
        $datetime=date('Y-m-d H:i:s');
        $data['added_on']=$data['updated_on']=$datetime;
        if($this->db->insert("security_deposit",$data)){
            return array("status"=>true,"message"=>"Security Deposit Added Successfully!");
        }
        else{
            $error=$this->db->error();
            return array("status"=>false,"message"=>$error['message']);
        }
    }
    
    public function updatesecuritydeposit($data){
        $datetime=date('Y-m-d H:i:s');
        $data['updated_on']=$datetime;
        $id=$data['id'];
        unset($data['id']);
        $where=array("id"=>$id);
        if($this->db->update("security_deposit",$data,$where)){
            return array("status"=>true,"message"=>"Security Deposit Updated Successfully!");
        }
        else{
            $error=$this->db->error();
            return array("status"=>false,"message"=>$error['message']);
        }
    }
    
    public function getsecuritydeposits($where=array(),$type="all"){
        $columns="t1.*,t2.name as customer_name,t2.mobile";
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('security_deposit t1');
        $this->db->join('customers t2','t1.user_id=t2.user_id','left');
        $this->db->order_by('t1.added_on','desc');
        $query=$this->db->get();
        if($type=='all'){
            $array=$query->result_array();
        }
        else{
            $array=$query->unbuffered_row('array');
        }
        return $array;
    }
    
    public function deletesecuritydeposit($id){
        if($this->db->delete("security_deposit",array("id"=>$id))){
            return array("status"=>true,"message"=>"Security Deposit Deleted Successfully!");
        }
        else{
            $error=$this->db->error();
            return array("status"=>false,"message"=>$error['message']);
        }
    }
    
    public function adminrecharge($data){
        $merchant_transaction_id=generatetransactionid();
        $datetime=date('Y-m-d H:i:s');
        $data['merchant_transaction_id']=$merchant_transaction_id;
        $data['merchant_user_id']=$data['user_id'];
        $data['status']=1; // Directly approved for admin recharge
        $data['added_on']=$data['updated_on']=$datetime;
        if($this->db->insert("wallet",$data)){
            $wallet_insert_id = $this->db->insert_id();
            if (!empty($data['user_id'])) {
                $this->load->library('credit_service');
                $this->credit_service->process_wallet_recovery($data['user_id'], $wallet_insert_id);
            }
            if (!empty($data['user_id'])) {
                $this->load->library('auto_debit_service');
                $this->auto_debit_service->process_user_pending_auto_debits($data['user_id']);
            }
            return array("status"=>true,"message"=>"Wallet Recharged Successfully!");
        }
        else{
            $error=$this->db->error();
            return array("status"=>false,"message"=>$error['message']);
        }
    }
    
    public function gettransactions($where1=array(),$where2=array(),$order_by="date"){
        // Check if remarks column exists in wallet table
        $table_name = $this->db->dbprefix('wallet');
        $columns_check = $this->db->query("SHOW COLUMNS FROM `{$table_name}` LIKE 'remarks'");
        $has_remarks = $columns_check->num_rows() > 0;
        
        if($has_remarks){
            $columns1="concat('transaction-',md5(concat('transaction',id))) as id,date,amount,
                        merchant_transaction_id as transaction_id,COALESCE(remarks,'') as remarks,updated_on as added_on,'credit' as trans_type,'topup' as type,'' as service_name";
        } else {
            $columns1="concat('transaction-',md5(concat('transaction',id))) as id,date,amount,
                        merchant_transaction_id as transaction_id,'' as remarks,updated_on as added_on,'credit' as trans_type,'topup' as type,'' as service_name";
        }
        $this->db->select($columns1);
        $this->db->where($where1);
        $this->db->from('wallet');
        $sql1=$this->db->get_compiled_select();
        
        $columns2="concat('purchase-',md5(concat('purchase',t1.id))) as id,t1.date,t1.amount,
                    md5(concat('purchase',t1.id)) as transaction_id,concat('Purchased ',t2.name) as remarks,t1.added_on,'debit' as trans_type,'service_purchase' as type,t2.name as service_name";
        $this->db->select($columns2);
        $this->db->where($where2);
        $this->db->from('purchases t1');
        $this->db->join('services t2','t1.service_id=t2.id');
        $sql2=$this->db->get_compiled_select();
        
        $columns3="concat('acc_payment-',md5(concat('acc_payment',t1.id))) as id,t1.date,t1.amount,
                    md5(concat('acc_payment',t1.id)) as transaction_id,concat('Accountancy Payment of ',MONTHNAME(t2.date),'-',YEAR(t2.date)) as remarks,t1.added_on,'debit' as trans_type,'acc_payment' as type,'' as service_name";
        $this->db->select($columns3);
        $this->db->where($where2);
        $this->db->from('acc_payment t1');
        $this->db->join('accountancy t2','t1.acc_date=t2.date and t1.firm_id=t2.firm_id');
        $sql3=$this->db->get_compiled_select();
        
        // Security Deposit transactions excluded from wallet view
        // $columns4="concat('security_deposit-',md5(concat('security_deposit',t1.id))) as id,t1.date,t1.amount,
        //             md5(concat('security_deposit',t1.id)) as transaction_id,COALESCE(t1.remarks,'Security Deposit') as remarks,t1.added_on,'debit' as trans_type,'security_deposit' as type";
        // $this->db->select($columns4);
        // $this->db->where($where2);
        // $this->db->from('security_deposit t1');
        // $sql4=$this->db->get_compiled_select();
        
        $query = $this->db->query($sql1 . ' UNION ' . $sql2.' UNION ' . $sql3.' ORDER BY '.$order_by);

        // Get the result
        $result = $query->result_array();
        return $result;
    }
    
    public function makeaccountancypayment($data){
        $datetime=date('Y-m-d H:i:s');
        $data['added_on']=$data['updated_on']=$datetime;
        $month=date('m',strtotime($data['acc_date']));
        $year=date('Y',strtotime($data['acc_date']));
        
        // Check if payment already exists for this month using proper query builder
        $this->db->where('user_id', $data['user_id']);
        $this->db->where('firm_id', $data['firm_id']);
        $this->db->where('year', $data['year']);
        $this->db->where("MONTH(acc_date) = $month", null, false);
        $this->db->where("YEAR(acc_date) = $year", null, false);
        $existing = $this->db->get('acc_payment');
        
        if($existing->num_rows()==0){
            if($this->db->insert("acc_payment",$data)){
                return array("status"=>true,"message"=>"Accountancy Payment Done Successfully");
            }
            else{
                $error=$this->db->error();
                return array("status"=>false,"message"=>$error['message']);
            }
        }
        else{
            return array("status"=>false,"message"=>"Accountancy Payment already paid for this month!");
        }
    }
    
    public function getwalletrecharges($where=array(),$type="all"){
        $columns="t1.*,t2.name as customer_name,t2.mobile as customer_mobile,t2.email as customer_email";
        $this->db->select($columns);
        $this->db->from('wallet t1');
        $this->db->join('customers t2','t1.user_id=t2.user_id','left');
        $this->db->where('t1.status',1); // Only show approved recharges
        
        // Check if remarks column exists and filter only admin recharges
        $table_name = $this->db->dbprefix('wallet');
        $columns_check = $this->db->query("SHOW COLUMNS FROM `{$table_name}` LIKE 'remarks'");
        $has_remarks = $columns_check->num_rows() > 0;
        
        if($has_remarks){
            // Only show transactions where remarks contains "Admin Recharge"
            $this->db->where("(t1.remarks LIKE '%Admin Recharge%' OR t1.remarks LIKE '%admin recharge%')");
        } else {
            // If remarks column doesn't exist, we can't filter, so return empty
            // This ensures we only show admin recharges when remarks column is available
            $this->db->where('1=0'); // Return no results if remarks column doesn't exist
        }
        
        if(!empty($where)){
            $this->db->where($where);
        }
        $this->db->order_by('t1.added_on','desc');
        $query=$this->db->get();
        if($type=='all'){
            $array=$query->result_array();
        }
        else{
            $array=$query->unbuffered_row('array');
        }
        return $array;
    }
    
    

    public function get_credit_monthly_ledger($user_id, $fy_start_year = null){
        $res_cust = $this->db->get_where('customers', ['user_id' => $user_id]);
        $customer = $res_cust ? $res_cust->unbuffered_row('array') : null;
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;
        $credit_percent = $this->get_credit_percent($user_id);
        if ($credit_percent <= 0) {
            $credit_percent = 2.00;
        }
        $daily_rate = ($credit_percent / 100) / 30;

        if (empty($fy_start_year)) {
            $fy_start_year = (date('m') >= 4) ? (int)date('Y') : ((int)date('Y') - 1);
        } else {
            $fy_start_year = (int)$fy_start_year;
        }
        $fy_end_year = $fy_start_year + 1;

        $day_payments = [];
        $day_receipts = [];

        // a) purchases
        $purchases = $this->db->select('id, date, amount, service, added_on')
                              ->where('user_id', $user_id)
                              ->where_in('type', ['Credit limit', 'Credit limit Paid'])
                              ->where('status >=', 0)
                              ->get('purchases')
                              ->result_array();
        if (!empty($purchases)) {
            foreach ($purchases as $p) {
                $d = !empty($p['date']) ? $p['date'] : date('Y-m-d', strtotime($p['added_on']));
                $amt = (float)$p['amount'];
                if ($amt > 0) {
                    $day_payments[$d][] = [
                        'source' => 'purchase',
                        'id' => $p['id'],
                        'amount' => $amt,
                        'remarks' => $p['service']
                    ];
                }
            }
        }

        // b) acc_payment
        $acc_payments = $this->db->select('id, date, amount, acc_date, added_on')
                                 ->where('user_id', $user_id)
                                 ->where('payment_mode', 'Credit Limit')
                                 ->where('status', 1)
                                 ->get('acc_payment')
                                 ->result_array();
        if (!empty($acc_payments)) {
            foreach ($acc_payments as $ap) {
                $d = !empty($ap['date']) ? $ap['date'] : date('Y-m-d', strtotime($ap['added_on']));
                $amt = (float)$ap['amount'];
                if ($amt > 0) {
                    $day_payments[$d][] = [
                        'source' => 'acc_payment',
                        'id' => $ap['id'],
                        'amount' => $amt,
                        'remarks' => 'Accountancy (' . $ap['acc_date'] . ')'
                    ];
                }
            }
        }

        // c) credit_limit_ledger
        $ledger_entries = $this->db->select('id, date, type, amount, remarks, added_on')
                                   ->where('user_id', $user_id)
                                   ->order_by('date', 'ASC')
                                   ->get('credit_limit_ledger')
                                   ->result_array();
        if (!empty($ledger_entries)) {
            foreach ($ledger_entries as $le) {
                $d = !empty($le['date']) ? $le['date'] : date('Y-m-d', strtotime($le['added_on']));
                $amt = (float)$le['amount'];
                if ($amt > 0) {
                    if ($le['type'] == 'receipt') {
                        $day_receipts[$d][] = [
                            'source' => 'ledger',
                            'id' => $le['id'],
                            'amount' => $amt,
                            'remarks' => $le['remarks']
                        ];
                    } else {
                        $day_payments[$d][] = [
                            'source' => 'ledger',
                            'id' => $le['id'],
                            'amount' => $amt,
                            'remarks' => $le['remarks']
                        ];
                    }
                }
            }
        }

        $month_keys = [
            sprintf('%04d-04', $fy_start_year) => ['name' => 'April ' . $fy_start_year, 'short' => 'Apr ' . $fy_start_year, 'm' => 4, 'y' => $fy_start_year],
            sprintf('%04d-05', $fy_start_year) => ['name' => 'May ' . $fy_start_year, 'short' => 'May ' . $fy_start_year, 'm' => 5, 'y' => $fy_start_year],
            sprintf('%04d-06', $fy_start_year) => ['name' => 'June ' . $fy_start_year, 'short' => 'Jun ' . $fy_start_year, 'm' => 6, 'y' => $fy_start_year],
            sprintf('%04d-07', $fy_start_year) => ['name' => 'July ' . $fy_start_year, 'short' => 'Jul ' . $fy_start_year, 'm' => 7, 'y' => $fy_start_year],
            sprintf('%04d-08', $fy_start_year) => ['name' => 'August ' . $fy_start_year, 'short' => 'Aug ' . $fy_start_year, 'm' => 8, 'y' => $fy_start_year],
            sprintf('%04d-09', $fy_start_year) => ['name' => 'September ' . $fy_start_year, 'short' => 'Sep ' . $fy_start_year, 'm' => 9, 'y' => $fy_start_year],
            sprintf('%04d-10', $fy_start_year) => ['name' => 'October ' . $fy_start_year, 'short' => 'Oct ' . $fy_start_year, 'm' => 10, 'y' => $fy_start_year],
            sprintf('%04d-11', $fy_start_year) => ['name' => 'November ' . $fy_start_year, 'short' => 'Nov ' . $fy_start_year, 'm' => 11, 'y' => $fy_start_year],
            sprintf('%04d-12', $fy_start_year) => ['name' => 'December ' . $fy_start_year, 'short' => 'Dec ' . $fy_start_year, 'm' => 12, 'y' => $fy_start_year],
            sprintf('%04d-01', $fy_end_year)   => ['name' => 'January ' . $fy_end_year, 'short' => 'Jan ' . $fy_end_year, 'm' => 1, 'y' => $fy_end_year],
            sprintf('%04d-02', $fy_end_year)   => ['name' => 'February ' . $fy_end_year, 'short' => 'Feb ' . $fy_end_year, 'm' => 2, 'y' => $fy_end_year],
            sprintf('%04d-03', $fy_end_year)   => ['name' => 'March ' . $fy_end_year, 'short' => 'Mar ' . $fy_end_year, 'm' => 3, 'y' => $fy_end_year],
        ];

        $fy_start_date = sprintf('%04d-04-01', $fy_start_year);
        $fy_end_date = sprintf('%04d-03-31', $fy_end_year);

        $all_dates = array_merge(array_keys($day_payments), array_keys($day_receipts));
        sort($all_dates);
        $overall_earliest = !empty($all_dates) ? $all_dates[0] : $fy_start_date;

        $calc_start = min($overall_earliest, $fy_start_date);
        $current = strtotime($calc_start);
        $end = strtotime($fy_end_date);
        $prev_total = 0.0;

        $monthly_buckets = [];
        foreach ($month_keys as $k => $info) {
            $monthly_buckets[$k] = [
                'month_key' => $k,
                'month_name' => $info['name'],
                'month_short' => $info['short'],
                'outstanding' => 0.0,
                'receipt' => 0.0,
                'total' => 0.0,
                'payment' => 0.0,
                'balance' => 0.0,
                'panel_interest' => 0.0,
                'closing_total' => 0.0,
                'days' => []
            ];
        }

        $today_str = date('Y-m-d');
        $current_ym = date('Y-m');

        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $ym = date('Y-m', $current);

            $outstanding = $prev_total;

            $receipt = 0.0;
            if (isset($day_receipts[$d])) {
                foreach ($day_receipts[$d] as $r) {
                    $receipt += (float)$r['amount'];
                }
            }

            $payment = 0.0;
            if (isset($day_payments[$d])) {
                foreach ($day_payments[$d] as $p) {
                    $payment += (float)$p['amount'];
                }
            }

            // Customer Due Balance: Outstanding Due + New Usage (Payment) - Repayments (Receipt)
            $total = $outstanding + $payment;
            $balance = max(0.0, $total - $receipt);

            // Panel Interest (Late Fine): Only for elapsed days up to TODAY
            $panel_interest = 0.0;
            if ($d <= $today_str && $balance > 0) {
                $panel_interest = round($balance * $daily_rate, 4);
            }

            $closing_total = round($balance + $panel_interest, 2);

            if (isset($monthly_buckets[$ym])) {
                if (empty($monthly_buckets[$ym]['days'])) {
                    $monthly_buckets[$ym]['outstanding'] = $outstanding;
                }
                $monthly_buckets[$ym]['receipt'] += $receipt;
                $monthly_buckets[$ym]['payment'] += $payment;
                $monthly_buckets[$ym]['panel_interest'] += $panel_interest;
                $monthly_buckets[$ym]['closing_total'] = $closing_total;
                $monthly_buckets[$ym]['days'][] = [
                    'date' => $d,
                    'outstanding' => $outstanding,
                    'receipt' => $receipt,
                    'total' => $total,
                    'payment' => $payment,
                    'balance' => $balance,
                    'panel_interest' => $panel_interest,
                    'closing_total' => $closing_total,
                    'receipt_items' => isset($day_receipts[$d]) ? $day_receipts[$d] : [],
                    'payment_items' => isset($day_payments[$d]) ? $day_payments[$d] : []
                ];
            }

            $prev_total = $closing_total;
            $current = strtotime('+1 day', $current);
        }

        $totals = [
            'receipt' => 0.0,
            'payment' => 0.0,
            'panel_interest' => 0.0
        ];
        $months_result = [];
        $running_prev = 0.0;
        foreach ($monthly_buckets as $k => $m) {
            $is_future = ($k > $current_ym);
            if (!empty($m['days'])) {
                $running_prev = $m['closing_total'];
            } else {
                $m['outstanding'] = $running_prev;
                $m['closing_total'] = $running_prev;
            }
            $m['total'] = $m['outstanding'] + $m['payment'];
            $m['balance'] = max(0.0, $m['total'] - $m['receipt']);
            $m['is_future'] = $is_future;

            $totals['receipt'] += $m['receipt'];
            $totals['payment'] += $m['payment'];
            $totals['panel_interest'] += $m['panel_interest'];
            $months_result[] = $m;
        }

        // Live due balance as of today
        $latest_balance = $running_prev;
        $available_credit = max(0.0, $credit_limit - $latest_balance);

        return [
            'customer' => $customer,
            'credit_limit' => $credit_limit,
            'credit_percent' => $credit_percent,
            'daily_rate' => $daily_rate,
            'financial_year' => $fy_start_year . '-' . $fy_end_year,
            'fy_start_year' => $fy_start_year,
            'fy_end_year' => $fy_end_year,
            'rows' => $months_result,
            'totals' => $totals,
            'latest_balance' => $latest_balance,
            'available_credit' => $available_credit
        ];
    }

}
