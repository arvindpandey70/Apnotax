<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
if (!function_exists('print_pre')) {
    function print_pre($data, $die = false)
    {
        echo PRE;
        print_r($data);
        echo "</pre>";
        if ($die) {
            die;
        }
    }
}

if (!function_exists('getuser')) {
    function getuser()
    {
        $CI = get_instance();
        $getuser = $CI->account->getuser(array("md5(id)" => $CI->session->user));
        if ($getuser['status'] == true) {
            return $getuser['user'];
        } else {
            redirect('/');
        }
    }
}

if (!function_exists('showrequest')) {
    function showrequest($array = array(), $stop = '')
    {
        echo PRE;
        if (empty($array) || in_array('post', $array))
            print_r($_POST);
        if (empty($array) || in_array('get', $array))
            print_r($_GET);
        if (empty($array) || in_array('files', $array))
            print_r($_FILES);
        if ($stop == 'die')
            die;
    }
}

if (!function_exists('logrequest')) {
    function logrequest()
    {
        if (REQUEST_LOG == TRUE) {
            $CI = get_instance();
            $post = $CI->input->post();
            $get = $CI->input->get();
            $post = array('post' => $_POST, 'get' => $_GET);
            if (!empty($_FILES)) {
                $post['files'] = $_FILES;
            }
            $post = json_encode($post);
            $server = json_encode($_SERVER);
            $cookie = json_encode($_COOKIE);
            $headers = function_exists('getallheaders') ? json_encode(getallheaders()) : array();
            $ip = get_visitor_IP();
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $CI->db->insert("request_log", array(
                "url" => $url,
                "ip_address" => $ip,
                "post" => $post,
                "server" => $server,
                "cookie" => $cookie,
                "headers" => $headers,
                "added_on" => date('Y-m-d H:i:s')
            ));
            //sleep(1);
        }
    }
}

if (!function_exists('getfiscaldates')) {
    function getfiscaldates($date = NULL)
    {
        $curyear = $date === NULL ? date('Y') : date('Y', strtotime($date));
        $curmonth = $date === NULL ? date('m') : date('m', strtotime($date));
        if ($curmonth > 3) {
            $start = "$curyear-04-01";
            $nextyear = $curyear + 1;
            $end = "$nextyear-03-31";
        } else {
            $prevyear = $curyear - 1;
            $start = "$prevyear-04-01";
            $end = "$curyear-03-31";
        }
        $result = array('from' => $start, 'to' => $end);
        return $result;
    }
}

if (!function_exists('getmonths')) {
    function getmonths($year = NULL)
    {
        if ($year === NULL) {
            $year = date('Y');
        }
        if (strlen($year) == 8) {
            $year = substr($year, 0, 4);
        } elseif (strlen($year) != 4) {
            return array();
        }

        $shortyear = substr($year, -2);
        $array = array();
        $start = $year . '-04-01';
        for ($i = 0; $i < 12; $i++) {
            $date = date('Y-m-d', strtotime($start . " +$i month"));
            $index = date('Ym', strtotime($date));
            $value = date('F-y', strtotime($date));
            $start_date = date('Y-m-01', strtotime($date));
            $end = date('Y-m-t', strtotime($date));
            $array[] = array('id' => $index, 'value' => $value, 'start' => $start_date, 'end' => $end);
        }
        return $array;
    }
}

if (!function_exists('getquarterly')) {
    function getquarterly($year = NULL)
    {
        if ($year === NULL) {
            $year = date('Y');
        }
        if (strlen($year) == 8) {
            $year = substr($year, 0, 4);
        }
        $shortyear = substr($year, -2);
        /*
                January, February, and March (Q1)
                April, May, and June (Q2)
                July, August, and September (Q3)
                October, November, and December (Q4)
            */
        $array = array();
        $index = $year . 'Q1';
        $value = 'Q1 (April-' . $shortyear . ' to June-' . $shortyear . ')';
        $array[] = array('id' => $index, 'value' => $value, 'start' => $year . '-04-01', 'end' => $year . '-06-30');
        $index = $year . 'Q2';
        $value = 'Q2 (July-' . $shortyear . ' to Sept-' . $shortyear . ')';
        $array[] = array('id' => $index, 'value' => $value, 'start' => $year . '-07-01', 'end' => $year . '-09-30');
        $index = $year . 'Q3';
        $value = 'Q3 (Oct-' . $shortyear . ' to Dec-' . $shortyear . ')';
        $array[] = array('id' => $index, 'value' => $value, 'start' => $year . '-10-01', 'end' => $year . '-12-31');
        $index = ($year + 1) . 'Q4';
        $value = 'Q4 (Jan-' . ($shortyear + 1) . ' to Mar-' . ($shortyear + 1) . ')';
        $array[] = array('id' => $index, 'value' => $value, 'start' => ($year + 1) . '-01-01', 'end' => ($year + 1) . '-03-31');
        return $array;
    }
}

if (!function_exists('getyearly')) {
    function getyearly($start = '2023')
    {
        $array = array();
        while ($start < date('Y', strtotime('next year'))) {
            $index = $start . ($start + 1);
            $value = 'TY ' . $start . '-' . substr(($start + 1), -2);
            $array[] = array('id' => $index, 'value' => $value);
            $start++;
        }
        return $array;
    }
}

if (!function_exists('getyearmonthvalues')) {
    function getyearmonthvalues($value)
    {
        $result = array();
        if (strlen($value) == 8) {
            $start = substr($value, 0, 4);
            $end = substr($value, -4);
            $index = $value;
            $value = 'TY ' . $start . '-' . substr(($end), -2);
            $result = array('id' => $index, 'name' => 'Year', 'value' => $value, 'year1' => $start, 'year2' => $end);
        } elseif (strlen($value) == 6) {
            $year = substr($value, 0, 4);
            $index = $value;
            if (strpos($value, 'Q') === false) {
                $m = substr($value, -2);
                if ($m < 4) {
                    $year--;
                }
                $months = getmonths($year);
                $name = 'Month';
            } else {
                // For quarters, Q4 belongs to the previous financial year
                // e.g., 2026Q4 should use getquarterly(2025) which creates 2026Q4
                $quarter_num = substr($value, -1);
                if ($quarter_num == '4') {
                    $year--; // Q4 is part of previous year's financial year
                }
                $months = getquarterly($year);
                $name = 'Quarter';
            }
            $ids = array_column($months, 'id');
            $key = array_search($index, $ids);
            if ($key !== false && isset($months[$key])) {
                $value = $months[$key]['value'];
                $start = $months[$key]['start'];
                $end = $months[$key]['end'];
                $result = array('id' => $index, 'name' => $name, 'value' => $value, 'start' => $start, 'end' => $end);
            }
        }
        return $result;
    }
}

if (!function_exists('strWordCut')) {
    function strWordCut($string, $length, $end = '....')
    {
        $string = strip_tags($string);

        if (strlen($string) > $length) {

            // truncate string
            $stringCut = substr($string, 0, $length);

            // make sure it ends in a word so assassinate doesn't become ass...
            $string = substr($stringCut, 0, strrpos($stringCut, ' ')) . $end;
        }
        return $string;
    }
}

if (!function_exists('findDateIndices')) {
    function findDateIndices($dates, $startDate, $endDate)
    {
        $indices = [];
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        foreach ($dates as $index => $date) {
            $currentDate = strtotime($date);
            if ($currentDate >= $startDate && $currentDate <= $endDate) {
                $indices[] = $index;
            }
        }

        return $indices;
    }
}

if (! function_exists('createZip')) {
    function createZip($sourceDir, $zipFileName)
    {
        $sourceDir = realpath($sourceDir);
        // Create a new ZipArchive object
        $zip = new ZipArchive();

        // Create and open the zip file
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add files from the source directory to the zip archive
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourceDir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }

            // Close the zip file
            $zip->close();

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir));

            echo "Zip archive created successfully.";
        } else {
            echo "Failed to create the zip archive.";
        }
    }
}

if (! function_exists('get_visitor_IP')) {
    /**
     * Get the real IP address from visitors proxy. e.g. Cloudflare
     *
     * @return string IP
     */
    function get_visitor_IP()
    {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }

        // Sometimes the `HTTP_CLIENT_IP` can be used by proxy servers
        $ip = @$_SERVER['HTTP_CLIENT_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Sometimes the `HTTP_X_FORWARDED_FOR` can contain more than IPs 
        $forward_ips = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        if ($forward_ips) {
            $all_ips = explode(',', $forward_ips);

            foreach ($all_ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}

if (!function_exists('url_exists')) {
    function url_exists($url)
    {
        return curl_init($url) !== false;
    }
}

if (!function_exists('getsidebar')) {
    function getsidebar()
    {
        $CI = get_instance();
        $class = $CI->router->fetch_class();
        $method = $CI->router->fetch_method();
        $role = $CI->session->role;
        $sidebar = array();
        $result = array('status' => false);
        if ($role != 'superadmin' && $role != 'admin' && $role != '') {
            $getsections = $CI->db->get_where('roles', ['slug' => $role]);
            if ($getsections->num_rows() > 0) {
                $sections = $getsections->unbuffered_row()->sections;
                $sections = explode(',', $sections);
                if (!empty($sections)) {
                    foreach ($sections as $section) {
                        $menu = $not = array();
                        if ($section == 'Lead Box') {
                            $submenu = array();
                            $menu = array('title' => 'Lead', 'icon' => 'assets/images/people.svg', 'link' => 'leadbox/', 'active' => ['leadbox'], 'not' => $not);
                            if ($class == 'leadbox') {
                                if ($method == 'index' || $method == 'createlead') {
                                    $result['status'] = true;
                                }
                            }
                        }
                        if (!empty($menu)) {
                            $sidebar[] = $menu;
                        }
                    }
                }
            }
        } else {
            $result['status'] = true;
        }
        if ($class == 'home' && $method == 'index') {
            $result['status'] = true;
        }
        if ($class == 'home' && $method == 'editpassword') {
            $result['status'] = true;
        }
        if (!empty($sidebar)) {
            $result['sidebar'] = $sidebar;
        }
        $result['status'] = true;
        return $result;
    }
}

if (!function_exists('generatetransactionid')) {
    function generatetransactionid($table = "wallet")
    {
        $merchant_transaction_id = strtoupper(random_string('alpha', 5)) . random_string('numeric', 5);
        $CI = get_instance();
        if ($CI->db->get_where($table, array("merchant_transaction_id" => $merchant_transaction_id))->num_rows() == 0) {
            return $merchant_transaction_id;
        } else {
            return generatetransactionid($table);
        }
    }
}

function simpleCipherEncrypt($text, $key)
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz';
    $encryptedText = '';

    for ($i = 0; $i < strlen($text); $i++) {
        $char = strtolower($text[$i]);
        $index = strpos($alphabet, $char);

        if ($index !== false) {
            $newIndex = ($index + $key) % strlen($alphabet);
            $encryptedText .= ($text[$i] === strtoupper($text[$i])) ? strtoupper($alphabet[$newIndex]) : $alphabet[$newIndex];
        } else {
            $encryptedText .= $text[$i];
        }
    }

    return $encryptedText;
}

function simpleCipherDecrypt($encryptedText, $key)
{
    return simpleCipherEncrypt($encryptedText, -$key);
}

if (!function_exists('logupdateoperations')) {
    function logupdateoperations($table, $data, $where)
    {
        $CI = get_instance();
        $class = $CI->router->class;
        $method = $CI->router->method;
        $ref = array('class' => $class, 'method' => $method);
        $CI->load->library('DBOperations');
        $CI->dboperations->log_update($table, $data, $where, $ref);
    }
}

if (!function_exists('logdeleteoperations')) {
    function logdeleteoperations($table, $where, $parent_id = NULL)
    {
        $CI = get_instance();
        $class = $CI->router->class;
        $method = $CI->router->method;
        $ref = array('class' => $class, 'method' => $method);
        $CI->load->library('DBOperations');
        return $CI->dboperations->log_delete($table, $where, $ref, $parent_id);
    }
}

if (!function_exists('checkaccountancy')) {
    function checkaccountancy($user, $firm_id, $year = NULL)
    {
        $CI = get_instance();
        $year = empty($year) ? $CI->session->year : $year;
        return 1;
    }
}

if (!function_exists('checkfirmservice')) {
    function checkfirmservice($user, $firm_id)
    {
        $CI = get_instance();
        $status = false;
        $where = array('user_id' => $user['id'], 'status' => 1, 'firm_id' => $firm_id);
        $query = $CI->db->get_where('customer_packages', $where);
        if ($query->num_rows() > 0) {
            $status = true;
        }
        if ($status === false) {
            $where = "t1.user_id='$user[id]' and t1.firm_id='$firm_id'";
            $purchases = $CI->service->getpurchases($where);
            if (!empty($purchases)) {
                $status = true;
            }
        }
        return $status;
    }
}

if (!function_exists('checkservicepurchase')) {
    function checkservicepurchase($service, $user, $firm_id, $year = NULL)
    {
        $CI = get_instance();
        // Use provided year, or try to get from session, or use current year
        if (empty($year)) {
            if (isset($CI->session) && !empty($CI->session->year)) {
                $year = $CI->session->year;
            } else {
                // Fallback to current financial year if no session
                $currentYear = date('Y');
                $year = $currentYear . ($currentYear + 1); // Format: "20232024"
            }
        }
        $service_for = $service['service_for'];
        $types = explode(',', $service['type']);
        $status = true;
        $message = '';
        if ($service['id'] == 1) {
            $where = array('user_id' => $user['id'], 'status' => 1, 'firm_id' => $firm_id);
            if (!empty($year)) {
                $where['year'] = $year;
            }
            $query = $CI->db->get_where('customer_packages', $where);
            if ($query->num_rows() > 0) {
                $status = false;
                $message = "You have already selected a Package!";
            }
        } elseif ($service['type'] == 'Once') {
            $where = "t1.user_id='$user[id]' and t1.service_id='$service[id]'";
            // Always check firm_id if provided (purchases always have firm_id)
            if (!empty($firm_id)) {
                $where .= " and t1.firm_id='$firm_id'";
            }
            $purchases = $CI->service->getpurchases($where);
            if (!empty($purchases)) {
                $status = false;
                $message = "You have already Purchased " . $service['name'] . "!";
            }
        } elseif ($types[0] == 'Yearly') {
            $where = "t1.user_id='$user[id]' and t1.service_id='$service[id]' and t1.year='$year' and t1.type='Yearly'";
            // Always check firm_id if provided (purchases always have firm_id)
            if (!empty($firm_id)) {
                $where .= " and t1.firm_id='$firm_id'";
            }
            $purchases = $CI->service->getpurchases($where);
            // Check annual limit: Maximum 1 yearly purchase per year
            if (!empty($purchases) && count($purchases) >= 1) {
                $status = false;
                $years = getyearmonthvalues($year);
                $message = "Annual limit reached! You can purchase yearly services maximum 1 time per year for " . $years['value'] . "!";
            }
        } else {
            //print_pre($service);
            if ($types[0] == 'Quarterly') {
                $where = "t1.user_id='$user[id]' and t1.service_id='$service[id]' and t1.year='$year' 
                            and t1.type='Quarterly'";
                // Always check firm_id if provided (purchases always have firm_id)
                if (!empty($firm_id)) {
                    $where .= " and t1.firm_id='$firm_id'";
                }
                $purchases = $CI->service->getpurchases($where);
                // Check annual limit: Maximum 4 quarterly purchases per year
                if (!empty($purchases) && count($purchases) >= 4) {
                    $status = false;
                    $years = getyearmonthvalues($year);
                    $message = "Annual limit reached! You can purchase quarterly services maximum 4 times per year for " . $years['value'] . "!";
                }
            } elseif ($types[0] == 'Monthly') {
                $where = "t1.user_id='$user[id]' and t1.service_id='$service[id]' and t1.year='$year' 
                            and t1.type='Monthly'";
                // Always check firm_id if provided (purchases always have firm_id)
                if (!empty($firm_id)) {
                    $where .= " and t1.firm_id='$firm_id'";
                }
                $purchases = $CI->service->getpurchases($where);
                //print_pre($purchases);
                // Check annual limit: Maximum 12 monthly purchases per year
                if (!empty($purchases) && count($purchases) >= 12) {
                    $status = false;
                    $years = getyearmonthvalues($year);
                    $message = "Annual limit reached! You can purchase monthly services maximum 12 times per year for " . $years['value'] . "!";
                }
            }
        }
        $type = !empty($types) ? implode(',', $types) : '';
        $service['type'] = $type;
        $service['message'] = $message;
        $service['buy_status'] = $status;
        return $service;
    }
}

if (!function_exists('get_financial_year')) {
    function get_financial_year($date = NULL)
    {
        if ($date === NULL) {
            $ts = time();
        } else if (is_numeric($date) && strlen((string)$date) == 8) {
            $year1 = (int)substr((string)$date, 0, 4);
            $year2 = (int)substr((string)$date, 4, 4);
            return array(
                'id' => (string)$date,
                'value' => 'TY ' . $year1 . '-' . substr((string)$year2, -2),
                'year1' => (string)$year1,
                'year2' => (string)$year2,
                'start' => "$year1-04-01",
                'end' => "$year2-03-31"
            );
        } else {
            $ts = strtotime($date);
            if ($ts === false) {
                $ts = time();
            }
        }

        $m = (int)date('n', $ts);
        $y = (int)date('Y', $ts);

        if ($m >= 4) {
            $year1 = $y;
            $year2 = $y + 1;
        } else {
            $year1 = $y - 1;
            $year2 = $y;
        }

        $id = $year1 . $year2;
        $value = 'TY ' . $year1 . '-' . substr((string)$year2, -2);
        return array(
            'id' => (string)$id,
            'value' => $value,
            'year1' => (string)$year1,
            'year2' => (string)$year2,
            'start' => "$year1-04-01",
            'end' => "$year2-03-31"
        );
    }
}

if (!function_exists('ensure_accountancy_fy_months')) {
    function ensure_accountancy_fy_months($user_id, $firm_id, $year, $package_id = 0)
    {
        if (empty($user_id) || empty($firm_id) || empty($year)) {
            return;
        }

        $CI = &get_instance();
        $fy = get_financial_year($year);
        $year1 = (int)$fy['year1'];
        $year2 = (int)$fy['year2'];

        $now = time();
        $cur_y = (int)date('Y', $now);
        $cur_m = (int)date('n', $now);

        if ($cur_y < $year1 || ($cur_y == $year1 && $cur_m < 4)) {
            $max_index = 1;
        } else if ($cur_y > $year2 || ($cur_y == $year2 && $cur_m >= 4)) {
            $max_index = 12;
        } else {
            if ($cur_y == $year1) {
                $max_index = $cur_m - 3;
            } else {
                $max_index = $cur_m + 9;
            }
        }

        if ($max_index < 1) $max_index = 1;
        if ($max_index > 12) $max_index = 12;

        $datetime = date('Y-m-d H:i:s');

        for ($idx = 1; $idx <= $max_index; $idx++) {
            if ($idx <= 9) {
                $m = $idx + 3;
                $y = $year1;
            } else {
                $m = $idx - 9;
                $y = $year2;
            }

            $month_start = sprintf('%04d-%02d-01', $y, $m);
            $next_m = $m + 1;
            $next_y = $y;
            if ($next_m > 12) {
                $next_m = 1;
                $next_y++;
            }
            $default_due_date = sprintf('%04d-%02d-06', $next_y, $next_m);

            $existing = $CI->db->get_where('accountancy', [
                'user_id' => $user_id,
                'firm_id' => $firm_id,
                'date'    => $month_start
            ])->unbuffered_row('array');

            if (empty($existing)) {
                $acc_data = array(
                    'user_id'           => $user_id,
                    'firm_id'           => $firm_id,
                    'package_id'        => $package_id,
                    'date'              => $month_start,
                    'turnover'          => 0,
                    'other_fee'         => 0,
                    'due_date'          => $default_due_date,
                    'added_by'          => $user_id,
                    'status'            => 1,
                    'auto_debit_status' => 'Pending',
                    'added_on'          => $datetime,
                    'updated_on'        => $datetime
                );
                $CI->db->insert('accountancy', $acc_data);
            } else {
                $updates = array();
                if (empty($existing['due_date']) || $existing['due_date'] == '0000-00-00' || strtotime($existing['due_date']) === false) {
                    $updates['due_date'] = $default_due_date;
                }
                if (empty($existing['package_id']) && !empty($package_id)) {
                    $updates['package_id'] = $package_id;
                }
                if (!empty($updates)) {
                    $updates['updated_on'] = $datetime;
                    $CI->db->update('accountancy', $updates, ['id' => $existing['id']]);
                }
            }
        }
    }
}

