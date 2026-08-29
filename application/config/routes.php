<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'website';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['updatenotification'] = 'home/updatenotification';
$route['editpassword'] = 'home/editpassword';

$route['logout'] = 'login/logout';
$route['login.php'] = 'website/login';
$route['register.php'] = 'website/register';
$route['enterotp.php'] = 'website/enterotp';
$route['forgotpassword.php'] = 'website/forgotpassword';
$route['resetpassword.php'] = 'website/resetpassword';
$route['resendotp'] = 'login/resendotp';
$route['resendotp.php'] = 'login/resendotp';
$route['unsubscribe'] = 'home/unsubscribe';

$route['defaultimage'] = 'home/image';
$route['profileimage'] = 'home/image';

$route['mywallet'] = 'wallet/mywallet';

$route['bankstatement'] = 'profile/bankstatement';


/*------------------------------APIs-----------------------------*/
$route['api/register'] = 'api/account/register';
$route['api/verifyotp'] = 'api/account/verifyotp';
$route['api/login'] = 'api/account/login';
$route['api/sendotptomobile'] = 'api/account/sendotptomobile';
$route['api/updateregid'] = 'api/account/updateregid';
$route['api/resetpassword'] = 'api/account/resetpassword';
$route['api/changepassword'] = 'api/account/changepassword';

$route['api/getstates'] = 'api/common/getstates';
$route['api/getdistricts'] = 'api/common/getdistricts';

$route['api/getservices'] = 'api/common/getservices';
$route['api/getpackages'] = 'api/common/getpackages';
$route['api/getpackagedetails'] = 'api/common/getpackagedetails';
$route['api/getyears'] = 'api/common/getyears';
$route['api/getquarters'] = 'api/common/getquarters';
$route['api/getmonths'] = 'api/common/getmonths';
$route['api/getnotifications'] = 'api/common/getnotifications';
$route['api/updatenotification'] = 'api/common/updatenotification';

$route['api/getprofile'] = 'api/profile/getprofile';
$route['api/updateprofile'] = 'api/profile/updateprofile';
$route['api/saveaddress'] = 'api/profile/saveaddress';
$route['api/getaddress'] = 'api/profile/getaddress';
$route['api/savekyc'] = 'api/profile/savekyc';
$route['api/getkyc'] = 'api/profile/getkyc';

$route['api/getwallet'] = 'api/wallet/getwallet';
$route['api/addtowallet'] = 'api/wallet/addtowallet';
$route['api/updatepayment'] = 'api/wallet/updatepayment';
$route['api/gettransactions'] = 'api/wallet/gettransactions';
$route['api/getaccountancydues'] = 'api/wallet/getaccountancydues';
$route['api/makeaccountancypayment'] = 'api/wallet/makeaccountancypayment';

$route['api/selectpackage'] = 'api/services/selectpackage';
$route['api/switchpackage'] = 'api/services/switchpackage';
$route['api/mypackage'] = 'api/services/mypackage';
$route['api/buyservice'] = 'api/services/buyservice';
$route['api/getservicetypes'] = 'api/services/getservicetypes';
$route['api/getserviceoptions'] = 'api/services/getserviceoptions';
$route['api/myservices'] = 'api/services/myservices';
$route['api/getservicefields'] = 'api/services/getservicefields';
$route['api/getservicefieldsfororder'] = 'api/services/getservicefieldsfororder';
$route['api/saveformdata'] = 'api/services/saveformdata';
$route['api/formpreview'] = 'api/services/formpreview';
$route['api/getaccountworkpackagedetails'] = 'api/services/getaccountworkpackagedetails';
$route['api/renewpackage'] = 'api/services/renewpackage';

$route['api/createpackage'] = 'api/profile/createpackage';
$route['api/myservicepackage'] = 'api/profile/myservicepackage';
$route['api/getservicepackages'] = 'api/profile/getservicepackages';
$route['api/requestpackagedelete'] = 'api/profile/requestpackagedelete';
$route['api/requestdeleteaccountwork'] = 'api/profile/requestdeleteaccountwork';
$route['api/triggerautorenew'] = 'api/profile/triggerautorenew';

$route['api/addfirm'] = 'api/profile/addfirm';
$route['api/myfirms'] = 'api/profile/myfirms';
$route['api/deletefirm'] = 'api/profile/deletefirm';

$route['api/checkaccountancy'] = 'api/profile/checkaccountancy';
$route['api/savemonthlystatement'] = 'api/profile/savemonthlystatement';
$route['api/savesessdata'] = 'api/profile/savesessdata';
$route['api/getbankstatements'] = 'api/profile/getbankstatements';

$route['api/getchats'] = 'api/chat/getchats';
$route['api/getchatmessages'] = 'api/chat/getchatmessages';
$route['api/sendmessage'] = 'api/chat/sendmessage';
$route['api/newchat'] = 'api/chat/newchat';

$route['api/getreportgroups'] = 'api/reports/getreportgroups';
$route['api/getreports'] = 'api/reports/getreports';
$route['api/getaccountancyreports'] = 'api/reports/getaccountancyreports';
$route['api/getpaymentreport'] = 'api/reports/getpaymentreport';
$route['api/processaccountancypayment'] = 'api/reports/processaccountancypayment';
$route['api/getotherfeereport'] = 'api/reports/getotherfeereport';

$route['api/getpurchasedservices'] = 'api/reports/getpurchasedservices';
$route['api/getpendingservices'] = 'api/reports/getpendingservices';
$route['api/renewservice'] = 'api/reports/renewservice';
$route['api/getworkreport'] = 'api/reports/getworkreport';
$route['api/getworkreports'] = 'api/reports/getworkreports';
$route['api/getmonthlyservices'] = 'api/reports/getmonthlyservices';

$route['api/getorders'] = 'api/orders/getorders';
$route['api/getorderdetails'] = 'api/orders/getorderdetails';
$route['api/downloaddocument'] = 'api/orders/downloaddocument';

$route['api/getcertificates'] = 'api/profile/getcertificates';
$route['api/getolddata'] = 'api/profile/getolddata';

$route['api/initiatepayment'] = 'api/wallet/initiatepayment';

$route['payu/success'] = 'home/payu_success';
$route['payu/failure'] = 'home/payu_failure';

$route['api/getinvoices'] = 'api/invoices/getinvoices';
$route['api/getinvoicedetails'] = 'api/invoices/getinvoicedetails';
$route['api/downloadinvoice'] = 'api/invoices/downloadinvoice';

/*------------------------------APIs-----------------------------*/
