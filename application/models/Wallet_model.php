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
        $purchases_credit = $this->db->get("purchases")->unbuffered_row()->amount;
        $purchases_credit = !empty($purchases_credit) ? (float)$purchases_credit : 0.00;

        $this->db->select_sum('amount');
        $this->db->where(['user_id' => $user_id, 'payment_mode' => 'Credit Limit']);
        $acc_credit = $this->db->get("acc_payment")->unbuffered_row()->amount;
        $acc_credit = !empty($acc_credit) ? (float)$acc_credit : 0.00;

        return round($purchases_credit + $acc_credit, 2);
    }

    public function get_available_credit($user_id){
        $customer = $this->db->get_where('customers', ['user_id' => $user_id])->unbuffered_row('array');
        $credit_limit = !empty($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0.00;
        $used_credit = $this->get_used_credit($user_id);
        $available = $credit_limit - $used_credit;
        return max(0.00, round($available, 2));
    }
    
    public function getsecuritydeposit($user_id){
        $this->db->select_sum('amount');
        $this->db->where(['user_id'=>$user_id]);
        $security=$this->db->get("security_deposit")->unbuffered_row()->amount;
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
    
    
}
