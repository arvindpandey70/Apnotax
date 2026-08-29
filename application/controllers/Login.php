<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {
    
    function __construct(){
		parent::__construct();
    }

	public function index(){
		loginredirect();
        if(isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']=='magazine.studionineconstructions.com') {
            $this->session->set_userdata('section','magazine');
        }
        $data['title']="Login";
        $data['page']="login";
        $this->load->view('includes/top-section',$data);
        $this->load->view('pages/login');
        $this->load->view('includes/bottom-section');
	}
    
	public function drowssaptide(){
        $data['title']="Edit Password";
        //$data['subtitle']="Sample Subtitle";
        $data['breadcrumb']=array();
		$this->template->load('pages','editpassword',$data);
	}
    
	public function validatelogin(){
        $redirect='login/';
        if($this->input->post('login')!==NULL){
            $data=$this->input->post();
            if(isset($data['role']) && $data['role']=='customer'){
                $redirect='login.php';
            }
            unset($data['login']);
            $result=$this->account->login($data);
            if($result['status']===true){
                $user=$result['user'];
                $this->startsession($user);
                loginredirect();
            }
            else{ 
                $this->session->set_flashdata('logerr',$result['message']);
                redirect($redirect);
            }
        }
        redirect($redirect);
	}
	
	public function register(){
        $redirect='register.php';
        if($this->input->post('register')!==NULL){
            $data=$this->input->post();
            $data['username']=$data['mobile'];
            unset($data['register']);
            //$this->db->trans_start();
            $result=$this->account->register($data);
            if($result['status']===true){  
                $mobile=$data['mobile'];
                $where=array("username"=>$mobile);
                $smsresult=$this->sendotp($where);
                if($smsresult['status']===false){

                }
                // Send OTP on user's email (do not pass OTP in URL)
                $otp = $smsresult['message'] ?? '';
                if (!empty($otp) && !empty($data['email'])) {
                    $this->load->helper('email');
                    $subject = 'OTP for Registration - ' . PROJECT_NAME;
                    $message  = '<p>Hi ' . htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p>';
                    $message .= '<p>Your OTP for ' . PROJECT_NAME . ' registration is: <b>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
                    $message .= '<p>OTP is valid for 30 minutes.</p>';
                    $message .= '<p>Regards,</p>';
                    $message .= '<p>' . htmlspecialchars(PROJECT_NAME, ENT_QUOTES, 'UTF-8') . '</p>';
                    sendemail($data['email'], $subject, $message);
                }
                unset($data['username'],$data['role'],$data['password']);
                $data['user_id']=$result['user_id'];
                $data['old']=$result['old'];
                $result=$this->customer->savecustomer($data);
                $this->session->set_userdata('mobile',$mobile);
                if(!empty($data['email'])){
                    $this->session->set_userdata('email',$data['email']);
                }
                $this->session->set_userdata('otp_purpose','register');
                $this->session->set_flashdata('msg','OTP has been sent to your registered contact details.');
                redirect('enterotp.php');
            }
            else{
                $this->session->set_flashdata('logerr',$result['message']);
                redirect($redirect);
            }
        }
        redirect($redirect);
	}

    public function sendforgototp(){
        if($this->input->post('sendotp')!==NULL){
            $email = trim((string)$this->input->post('email'));
            if(empty($email)){
                $this->session->set_flashdata('logerr','Please enter email address.');
                redirect('forgotpassword.php');
            }

            $where = array("email"=>$email, "role"=>"customer");
            $check = $this->account->getuser($where);
            if($check['status']!==true){
                $this->session->set_flashdata('logerr','Email not registered.');
                redirect('forgotpassword.php');
            }

            $user = $check['user'];
            $mobile = $user['mobile'] ?? '';
            if(empty($mobile)){
                $this->session->set_flashdata('logerr','No mobile linked with this email.');
                redirect('forgotpassword.php');
            }

            $result = $this->sendotp(array("username"=>$mobile));
            if($result['status']===true){
                if(!empty($user['email'])){
                    $otp = $result['message'] ?? '';
                    if(!empty($otp)){
                        $this->load->helper('email');
                        $subject = 'OTP for Password Reset - ' . PROJECT_NAME;
                        $name = !empty($user['name']) ? $user['name'] : $user['email'];
                        $message  = '<p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>';
                        $message .= '<p>Your OTP to reset password for ' . PROJECT_NAME . ' account is: <b>' . htmlspecialchars((string)$otp, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
                        $message .= '<p>OTP is valid for 30 minutes.</p>';
                        $message .= '<p>Regards,</p>';
                        $message .= '<p>' . htmlspecialchars(PROJECT_NAME, ENT_QUOTES, 'UTF-8') . '</p>';
                        @sendemail($user['email'], $subject, $message);
                    }
                }
                $this->session->set_userdata('mobile',$mobile);
                if(!empty($user['email'])){
                    $this->session->set_userdata('email',$user['email']);
                }
                $this->session->set_userdata('otp_purpose','forgot');
                $this->session->set_flashdata('msg','OTP has been sent to reset your password.');
                redirect('enterotp.php');
            }

            $this->session->set_flashdata('logerr',$result['message'] ?? 'Failed to send OTP.');
            redirect('forgotpassword.php');
        }
        redirect('forgotpassword.php');
    }

    public function resendotp(){
        $mobile = $this->session->mobile;
        if(empty($mobile)){
            $this->session->set_flashdata('err_msg', 'Session expired. Please try registering or submitting forgot password again.');
            redirect('enterotp.php');
        }

        $result = $this->sendotp(array("username" => $mobile));
        if($result['status'] === true){
            $otp = $result['message'] ?? '';
            $email = $this->session->email;
            if(empty($email)){
                $check = $this->account->getuser(array("mobile" => $mobile));
                if($check['status'] === true){
                    $email = $check['user']['email'] ?? '';
                }
            }
            if(!empty($email) && !empty($otp)){
                $this->load->helper('email');
                $subject = 'Resend OTP - ' . PROJECT_NAME;
                $message  = '<p>Your new OTP for ' . PROJECT_NAME . ' is: <b>' . htmlspecialchars((string)$otp, ENT_QUOTES, 'UTF-8') . '</b>.</p>';
                $message .= '<p>OTP is valid for 30 minutes.</p>';
                $message .= '<p>Regards,</p><p>' . htmlspecialchars(PROJECT_NAME, ENT_QUOTES, 'UTF-8') . '</p>';
                @sendemail($email, $subject, $message);
            }
            $this->session->set_flashdata('msg', 'A new OTP has been sent successfully.');
        } else {
            $this->session->set_flashdata('err_msg', $result['message'] ?? 'Failed to resend OTP.');
        }
        redirect('enterotp.php');
    }
    
    public function verifyotp(){
        if($this->input->post('verifyotp')!==NULL){
            $otp=$this->input->post('otp');
            $username=$this->session->mobile;
            if(empty($username)){
                $this->session->set_flashdata('err_msg','Session expired. Please request a new OTP.');
                redirect('enterotp.php');
            }
            $where['username']=$username;
            //$this->db->trans_start();
            $result=$this->account->verifyotp($otp,$where);
            if($result['status']===true){
                $result=$result['result'];
                $otpPurpose = $this->session->otp_purpose;
                if($otpPurpose==='forgot'){
                    $this->session->set_userdata('forgot_verified_mobile',$username);
                    $this->session->unset_userdata(array('mobile','email','otp_purpose'));
                    redirect('resetpassword.php');
                }
                else{
                    $this->startsession($result);
                    $this->session->unset_userdata(array('mobile','email','otp_purpose'));
                    redirect('home/');
                }
            }
            else{
                $error=$result['message'];
                $this->session->set_flashdata('err_msg',$error);
            }
        }
        redirect('enterotp.php');
    }

    public function resetpassword(){
        if($this->input->post('updatepassword')!==NULL){
            $mobile = $this->session->forgot_verified_mobile;
            $password = (string)$this->input->post('password');
            $repassword = (string)$this->input->post('repassword');
            if(empty($mobile)){
                $this->session->set_flashdata('logerr','Session expired. Please verify OTP again.');
                redirect('forgotpassword.php');
            }
            if(empty($password) || empty($repassword)){
                $this->session->set_flashdata('logerr','Please enter password and confirm password.');
                redirect('resetpassword.php');
            }
            if($password!==$repassword){
                $this->session->set_flashdata('logerr','Password and confirm password do not match.');
                redirect('resetpassword.php');
            }

            $result = $this->account->updatepassword(array("password"=>$password),array("username"=>$mobile,"role"=>"customer"));
            if($result['status']===true){
                $this->session->unset_userdata('forgot_verified_mobile');
                $this->session->set_flashdata('logerr','Password updated successfully. Please login.');
                redirect('login.php');
            }
            $this->session->set_flashdata('logerr',$result['message'] ?? 'Failed to update password.');
            redirect('resetpassword.php');
        }

        if(empty($this->session->forgot_verified_mobile)){
            redirect('forgotpassword.php');
        }
        redirect('resetpassword.php');
    }
	public function logout(){
        $url='login/';
		if($this->session->user!==NULL){
            if($this->session->role=='customer'){
                $url='login.php';
            }
			$data=array("user","name","role","project","desig","year","firm");
			$this->session->unset_userdata($data);
		}	
		redirect($url);
	}
	
	public function startsession($result){
		$data['user']=md5($result['id']);
		$data['name']=$result['name'];
		$data['emp_id']=$result['emp_id'];
		$data['role']=$result['role'];
		$data['project']=PROJECT_NAME;
		$this->session->set_userdata($data);
	}
	
    public function updatepassword(){
        if($this->input->post('updatepassword')!==NULL){
            $password=$this->input->post('password');
            $username=$this->input->post('username');
            $result=$this->account->updatepassword(array("password"=>$password),array("username"=>$username,"role"=>"admin"));
            if($result['status']===true){
                $this->session->set_flashdata('msg',$result['message']);
            }
            else{
                $error=$result['message'];
                $this->session->set_flashdata('err_msg',$error);
            }
        }
        redirect('');
    }
    
    public function updatesender(){
        $sender=$this->input->post('sender');
		write_file('./sender.txt',$sender);
        redirect('profile/');
    }
    
    public function sendotp($where){
        $array=$this->account->createotp($where);
        if($array['status']===true){
            $result=$array['result'];
            $mobile=$result['mobile'];
            $name=$result['name'];
            $otp=$result['otp'];
            $type=$result['type'];

            $this->session->set_userdata('dev_otp', $otp);

            // Send SMS if helper function sendsms is active and configured
            if(function_exists('sendsms')){
                $smsMsg = "$otp is your OTP for " . PROJECT_NAME . ". Valid for 30 minutes.";
                @sendsms($mobile, $smsMsg);
            }
            return array("status"=>true,"message"=>$otp);
        }
        else{
            return $array;
        }
    }
    
}
