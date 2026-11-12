<?php
class Service_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
        $this->db->db_debug = false;
    }

    public function purchaseservices($data)
    {
        $datetime = date('Y-m-d H:i:s');
        $result = array("status" => true, "message" => "Purchase Added Successfully!");
        $parent_id = NULL;
        $name = !empty($data['name']) ? $data['name'] : '';
        unset($data['name']);
        foreach ($data as $single) {
            $service_name = !empty($single['service']) ? $single['service'] : '';
            // Keep service name in database (don't unset it)
            // Ensure all required fields have default values
            if (!isset($single['rate']) || $single['rate'] === null) {
                $single['rate'] = !empty($single['amount']) ? $single['amount'] : 0;
            }
            if (!isset($single['subtotal']) || $single['subtotal'] === null) {
                $single['subtotal'] = !empty($single['amount']) ? $single['amount'] : 0;
            }
            if (!isset($single['gst_amount']) || $single['gst_amount'] === null) {
                $single['gst_amount'] = 0;
            }
            if (!isset($single['gst_enabled']) || $single['gst_enabled'] === null) {
                $single['gst_enabled'] = 0;
            }

            $single['parent_id'] = $parent_id;
            $single['added_on'] = $datetime;
            $single['updated_on'] = $datetime;

            if ($this->db->insert("purchases", $single)) {
                $order_id = $this->db->insert_id();
                $parent_id = $parent_id === NULL ? $order_id : $parent_id;

                $amt = isset($single['amount']) ? (float) $single['amount'] : 0;
                $is_credit = isset($single['type']) && $single['type'] === 'Credit limit';
                $debit_source = $is_credit ? 'Credit Limit' : 'wallet';
                $notifydata = array(
                    "type" => "order",
                    "user_id" => $single['user_id'],
                    'order_id' => $order_id,
                    'message' => 'Your order for "' . $service_name . '" is placed. ₹' . number_format($amt, 2) . ' debited from ' . $debit_source . '.',
                    'added_on' => $datetime,
                    'updated_on' => $datetime
                );
                $this->common->savenotification($notifydata);
            } else {
                $error = $this->db->error();
                $result['status'] = false;
                $result['message'] = !empty($error['message']) ? $error['message'] : 'Failed to save purchase. Please check database columns.';
                // Log the error for debugging
                log_message('error', 'Purchase failed: ' . json_encode($error) . ' | Data: ' . json_encode($single));
                break; // Stop processing if one fails
            }
            $result['order_id'] = $parent_id;
        }
        return $result;
    }

    public function getpurchases($where = array(), $type = 'all')
    {
        // Include all purchase columns including service_option and service_option_display
        // Also include firm information for invoice generation
        $columns = "t1.*,t2.name,t2.mobile,t2.email,t3.name as service_name";
        $columns .= ",t4.name as firm_name,t4.gstin as firm_gstin";
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('purchases t1');
        $this->db->join('users t2', 't1.user_id=t2.id');
        $this->db->join('services t3', 't1.service_id=t3.id', 'left');
        $this->db->join('firms t4', 't1.firm_id=t4.id', 'left');
        $query = $this->db->get();

        // Check if query was successful
        if ($query === false) {
            // If query failed, try without firms join (for backward compatibility)
            $error = $this->db->error();
            log_message('error', 'getpurchases query failed: ' . json_encode($error));

            $this->db->reset_query();
            $columns = "t1.*,t2.name,t2.mobile,t2.email,t3.name as service_name";
            $this->db->select($columns);
            $this->db->where($where);
            $this->db->from('purchases t1');
            $this->db->join('users t2', 't1.user_id=t2.id');
            $this->db->join('services t3', 't1.service_id=t3.id', 'left');
            $query = $this->db->get();

            // If still fails, return empty array
            if ($query === false) {
                return ($type == 'all') ? array() : array();
            }
        }

        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            if ($query->num_rows() > 0) {
                $array = $query->unbuffered_row('array');
            } else {
                $array = array();
            }
        }
        return $array;
    }

    public function getpurchasedservices($where = array(), $type = 'all', $group_by_service = false)
    {
        // Reset query builder to avoid conflicts
        $this->db->reset_query();

        if ($group_by_service) {
            // When grouping by service_id, we need to handle MySQL's ONLY_FULL_GROUP_BY mode
            // Use a subquery to get the latest purchase for each service_id
            $prefix = $this->db->dbprefix;
            $purchases_table = $prefix . 'purchases';
            $services_table = $prefix . 'services';
            $assessments_table = $prefix . 'assessments';
            $formdata_table = $prefix . 'formdata';

            // Build WHERE clause for subquery
            $where_clause = "1=1";
            if (is_array($where)) {
                foreach ($where as $key => $value) {
                    $field = str_replace('t1.', '', $key);
                    $escaped_value = $this->db->escape($value);
                    $where_clause .= " AND t1.{$field} = {$escaped_value}";
                }
            } elseif (is_string($where) && !empty($where)) {
                // For string WHERE, use it as-is but ensure table prefix is correct
                $where_clause = $where;
            }

            // Subquery to get the latest purchase ID for each service_id
            $subquery = "(SELECT MAX(t1.id) as id 
                         FROM {$purchases_table} t1 
                         WHERE {$where_clause}
                         GROUP BY t1.service_id) as latest";

            // Main query with proper column selection
            // Use subqueries to get month and report values to avoid duplicates from multiple records
            $columns = "t1.*,t2.name as service_name,t2.slug as service_slug,t2.type,t1.type as purchased_type";
            $columns .= ",  case when t1.status=0 then 'Pending' 
                                when t1.status=1 then 'Complete' 
                                when t1.status=2 then 'Documents Uploaded' 
                                when t1.status=3 then 'Form Assessment in Progress' 
                                when t1.status=4 then 'Assessment Report Uploaded' 
                                else '' end as order_status";
            $columns .= ",  case when t1.status=4 then concat('" . base_url() . "',(SELECT t3.file FROM {$assessments_table} t3 WHERE t3.order_id = t1.id AND t3.status = 1 ORDER BY t3.id DESC LIMIT 1))
                                else '' end as report";
            $columns .= ", (SELECT MAX(t4.value) FROM {$formdata_table} t4 WHERE t4.order_id = t1.id AND t4.field LIKE '%-month' LIMIT 1) as month";

            $sql = "SELECT {$columns}
                    FROM {$purchases_table} t1
                    INNER JOIN {$subquery} ON t1.id = latest.id
                    LEFT JOIN {$services_table} t2 ON t1.service_id=t2.id
                    GROUP BY t1.id
                    ORDER BY t1.id DESC";

            $query = $this->db->query($sql);
        } else {
            // Standard query without grouping
            // Use subqueries in SELECT to get month and report values to avoid duplicates
            $prefix = $this->db->dbprefix;
            $formdata_table = $prefix . 'formdata';
            $assessments_table = $prefix . 'assessments';

            $columns = "t1.*,t2.name as service_name,t2.slug as service_slug,t2.type,t1.type as purchased_type";
            $columns .= ",  case when t1.status=0 then 'Pending' 
                                when t1.status=1 then 'Complete' 
                                when t1.status=2 then 'Documents Uploaded' 
                                when t1.status=3 then 'Form Assessment in Progress' 
                                when t1.status=4 then 'Assessment Report Uploaded' 
                                else '' end as order_status";
            $columns .= ",  case when t1.status=4 then concat('" . base_url() . "',(SELECT t3.file FROM {$assessments_table} t3 WHERE t3.order_id = t1.id AND t3.status = 1 ORDER BY t3.id DESC LIMIT 1))
                                else '' end as report";
            $columns .= ", (SELECT MAX(t4.value) FROM {$formdata_table} t4 WHERE t4.order_id = t1.id AND t4.field LIKE '%-month' LIMIT 1) as month";
            $this->db->select($columns, FALSE);

            // Handle both array and string where conditions
            if (is_array($where)) {
                $this->db->where($where);
            } elseif (!empty($where) && is_string($where)) {
                $this->db->where($where, NULL, FALSE);
            }

            $this->db->from('purchases t1');
            $this->db->join('services t2', 't1.service_id=t2.id', 'left');
            $this->db->group_by('t1.id');
            $this->db->order_by('t1.id', 'DESC');

            $query = $this->db->get();
        }

        // Check if query succeeded before calling result methods
        if ($query === FALSE) {
            $error = $this->db->error();
            log_message('error', 'getpurchasedservices query failed: ' . $error['message']);
            log_message('error', 'WHERE clause: ' . (is_string($where) ? $where : json_encode($where)));
            log_message('error', 'SQL: ' . $this->db->last_query());
            return ($type == 'all') ? array() : array();
        }

        // Log for debugging (only in development)
        if (ENVIRONMENT !== 'production') {
            log_message('debug', 'getpurchasedservices query executed successfully. Rows: ' . $query->num_rows());
            log_message('debug', 'SQL: ' . $this->db->last_query());
        }

        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function saveformdata($data)
    {
        $datetime = date('Y-m-d H:i:s');
        if (!empty($data)) {
            $where = array('user_id' => $data[0]['user_id'], 'order_id' => $data[0]['order_id']);
            if ($this->db->get_where('formdata', $where)->num_rows() == 0) {
                $order_id = $data[0]['order_id'];
                foreach ($data as $key => $value) {
                    $data[$key]['added_on'] = $datetime;
                    $data[$key]['updated_on'] = $datetime;
                }
                if ($this->db->insert_batch('formdata', $data)) {
                    $this->db->update('purchases', ['status' => 2, 'updated_on' => $datetime], ['id' => $order_id]);
                    $result = array("status" => true, "message" => "Formdata Saved Successfully!");
                } else {
                    $err = $this->db->error();
                    $result = array("status" => false, "message" => $err['message']);
                }
            } else {
                $result = array("status" => false, "message" => "Data already Saved!");
            }
        } else {
            $result = array("status" => false, "message" => "Data not provided!");
        }
        return $result;
    }

    public function getuploadeddocuments($where = array(), $type = "all", $columns = false)
    {
        if ($columns) {
            $columns = "t1.id";
        } else {
            $columns = "t1.id,t1.document_id,t1.display_name,t1.slug,t2.value,t2.file,ifnull(t2.file_type,'--') as file_type,";
            $columns .= "CASE WHEN t2.value=0 && t2.file=0 THEN '--' ";
            $columns .= " WHEN t2.value=1 && t2.file=0 THEN 'Value'";
            $columns .= " WHEN t2.value=1 && t2.file>0 THEN 'Value, File Upload'";
            $columns .= " WHEN t2.value=0 && t2.file>0 THEN 'File Upload' ELSE '--' END as type,";
            $columns .= "a.value as formvalue,t4.name as firm_name";
        }
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('formdata a');
        $this->db->join('docs_required t1', 'a.field_id=t1.id');
        $this->db->join('documents t2', 't1.document_id=t2.id');
        $this->db->join('services t3', 't1.service_id=t3.id');
        $this->db->join('firms t4', 'a.firm_id=t4.id', 'left');
        $this->db->order_by('t1.id');
        $query = $this->db->get();
        if ($type == 'all') {
            if (isset($where['t1.document_id'])) {
                unset($where['t1.document_id']);
            }
            $array = $query->result_array();

            $columns = "0  as id,0 as document_id,'a' as display_name,a.field as slug,1 as value,0 as file,'--' as file_type,";
            $columns .= "'Value' as type,a.value as formvalue";

            $this->db->select($columns);
            $this->db->where($where);
            $this->db->where(['a.field_id' => 0]);
            $this->db->from('formdata a');
            $this->db->join('services t1', 'a.service_id=t1.id');
            $this->db->order_by('t1.id');
            $query2 = $this->db->get();
            if ($query2->num_rows() > 0) {
                $array2 = $query2->result_array();
                foreach ($array2 as $key => $value) {
                    $formvalue = getyearmonthvalues($value['formvalue']);
                    $array2[$key]['id'] = $formvalue['id'];
                    $array2[$key]['display_name'] = $formvalue['name'];
                    $array2[$key]['formvalue'] = $formvalue['value'];
                }
                $array = array_merge($array, $array2);
            }
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function getservicereports($where = array(), $type = 'all', $year = NULL, $quarter = NULL, $month = NULL)
    {
        $columns = "t1.*,t2.name as service_name,t2.slug as service_slug,t2.type";
        $columns .= ",  case when t1.status=0 then 'Pending' 
                            when t1.status=1 then 'Complete' 
                            when t1.status=2 then 'Documents Uploaded' 
                            when t1.status=3 then 'Form Assessment in Progress' 
                            when t1.status=4 then 'Assessment Report Uploaded' 
                            else '' end as order_status";
        $columns .= ",  case when t1.status=4 then concat('" . base_url() . "',t3.file) 
                            else '' end as report";
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('purchases t1');
        $this->db->join('services t2', 't1.service_id=t2.id', 'left');
        $this->db->join('assessments t3', 't1.id=t3.order_id', 'left');
        $query = $this->db->get();
        $result = array();
        if ($type == 'all') {
            $array = $query->result_array();
            if (!empty($array)) {
                foreach ($array as $single) {
                    $yeararr = !empty($year) ? getyearmonthvalues($year) : array();
                    $quarterarr = !empty($quarter) ? getyearmonthvalues($quarter) : array();
                    $montharr = !empty($month) ? getyearmonthvalues($month) : array();
                    $formdata = $this->getuploadeddocuments(['a.order_id' => $single['id'], 't1.document_id' => 0]);
                    //print_pre($formdata);
                    //print_pre($yeararr);
                    //print_pre($quarterarr);
                    //print_pre($montharr);
                    if (!empty($formdata)) {
                        $status = true;
                        foreach ($formdata as $data) {
                            $slug = $single['service_slug'] . '-year';
                            if ($data['slug'] == $slug && !empty($yeararr)) {
                                $status = ($yeararr['value'] == $data['formvalue']) ? true : false;
                            }
                            $slug = $single['service_slug'] . '-month';
                            if ($data['slug'] == $slug && !empty($montharr)) {
                                $status = ($montharr['value'] == $data['formvalue']) ? true : false;
                            } elseif ($data['slug'] == $slug && !empty($quarterarr)) {
                                $status = ($quarterarr['value'] == $data['formvalue']) ? true : false;
                            }
                            if ($status === false) {
                                break;
                            }
                        }
                        if ($status === true) {
                            $result[] = $single;
                        }
                    } else {
                        if (!empty($yeararr)) {
                            $year1 = substr($year, 0, 4);
                            $year2 = substr($year, -4);
                            $from = $year1 . '04-01';
                            $to = $year2 . '03-31';
                            if ($single['date'] >= $from && $single['date'] <= $to) {
                                $result[] = $single;
                            }
                        }
                    }
                }
            }
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $result;
    }

    public function getreportgroups($where = array(), $type = 'all', $year = NULL, $quarter = NULL, $month = NULL)
    {
        $columns = "t1.*,t2.name as service_name,t2.slug as service_slug,t2.type";
        $columns .= ",  case when t1.status=0 then 'Pending' 
                            when t1.status=1 then 'Complete' 
                            when t1.status=2 then 'Documents Uploaded' 
                            when t1.status=3 then 'Form Assessment in Progress' 
                            when t1.status=4 then 'Assessment Report Uploaded' 
                            else '' end as order_status";
        $columns .= ",  case when t1.status=4 then concat('" . base_url() . "',t3.file) 
                            else '' end as report";
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('purchases t1');
        $this->db->join('services t2', 't1.service_id=t2.id', 'left');
        $this->db->join('assessments t3', 't1.id=t3.order_id', 'left');
        $query = $this->db->get();
        $result = array();
        if ($type == 'all') {
            $array = $query->result_array();
            if (!empty($array)) {
                foreach ($array as $single) {
                    $yeararr = !empty($year) ? getyearmonthvalues($year) : array();
                    $quarterarr = !empty($quarter) ? getyearmonthvalues($quarter) : array();
                    $montharr = !empty($month) ? getyearmonthvalues($month) : array();
                    $formdata = $this->getuploadeddocuments(['a.order_id' => $single['id'], 't1.document_id' => 0]);
                    //print_pre($formdata);
                    //print_pre($yeararr);
                    //print_pre($quarterarr);
                    //print_pre($montharr);
                    if (!empty($formdata)) {
                        $status = true;
                        foreach ($formdata as $data) {
                            $slug = $single['service_slug'] . '-year';
                            if ($data['slug'] == $slug && !empty($yeararr)) {
                                $status = ($yeararr['value'] == $data['formvalue']) ? true : false;
                            }
                            $slug = $single['service_slug'] . '-month';
                            if ($data['slug'] == $slug && !empty($montharr)) {
                                $status = ($montharr['value'] == $data['formvalue']) ? true : false;
                            } elseif ($data['slug'] == $slug && !empty($quarterarr)) {
                                $status = ($quarterarr['value'] == $data['formvalue']) ? true : false;
                            }
                            if ($status === false) {
                                break;
                            }
                        }
                        if ($status === true) {
                            $result[$single['service_slug']][] = $single;
                        }
                    } else {
                        if (!empty($yeararr)) {
                            $year1 = substr($year, 0, 4);
                            $year2 = substr($year, -4);
                            $from = $year1 . '04-01';
                            $to = $year2 . '03-31';
                            if ($single['date'] >= $from && $single['date'] <= $to) {
                                $result[$single['service_slug']][] = $single;
                            }
                        }
                    }
                }
            }
        } else {
            $array = $query->unbuffered_row('array');
        }
        if (!empty($result)) {
            $array = $result;
            $result = array();
            foreach ($array as $key => $value) {
                $service = $this->db->get_where('services', ['slug' => $key])->unbuffered_row('array');
                $single = array('service' => $service, 'data' => $value);
                $result[] = $single;
            }
        }
        return $result;
    }

    public function saveturnover($data)
    {
        if ($this->db->get_where('accountancy', array('user_id' => $data['user_id'], 'firm_id' => $data['firm_id'], 'date' => $data['date']))->num_rows() == 0) {
            if ($this->db->insert("accountancy", $data)) {
                return array("status" => true, "message" => "Turnover Added Successfully!");
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "Turnover Already Added!");
        }
    }

    public function getturnovers($where = array(), $type = 'all')
    {
        $query = $this->db->get_where('accountancy', $where);
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function getturnoverswithpayment($where = "1", $type = 'all')
    {
        /*$this->db->where($where);
        $this->db->where("t2.date <= t1.due_date");
        $this->db->select("t1.*,sum(amount) as paid");
        $this->db->group_by('t1.date');
        $this->db->from('accountancy t1');
        $this->db->join('acc_payment t2','t1.date=t2.acc_date','left');
        $query=$this->db->get();*/

        // Use dynamic table prefix from database config
        $prefix = $this->db->dbprefix;
        $accountancy_table = $prefix . 'accountancy';
        $payment_table = $prefix . 'acc_payment';

        $sql = "SELECT
                DATE_FORMAT(t1.date, '%Y-%m') AS month,
                t1.*,
                COALESCE(SUM(t2.amount), 0) AS paid,
                COALESCE(SUM(t2.acc_fee), 0) AS paid_acc_fee,
                MAX(t2.added_on) AS payment_date
            FROM
                {$accountancy_table} t1
            LEFT JOIN
                {$payment_table} t2 ON t1.user_id = t2.user_id AND t1.date = t2.acc_date AND t1.firm_id = t2.firm_id AND t2.status = 1
            WHERE
                $where
            GROUP BY
                DATE_FORMAT(t1.date, '%Y-%m'), t1.id
            ORDER BY
                month, t1.id;";

        $query = $this->db->query($sql);

        // Check if query succeeded before calling result methods
        if ($query === FALSE) {
            $error = $this->db->error();
            log_message('error', 'getturnoverswithpayment query failed: ' . $error['message'] . ' SQL: ' . $sql);
            log_message('error', 'Table prefix used: ' . $prefix);
            log_message('error', 'WHERE clause: ' . $where);
            return ($type == 'all') ? array() : array();
        }

        // Log successful query for debugging (only in development)
        if (ENVIRONMENT !== 'production') {
            log_message('debug', 'getturnoverswithpayment query executed successfully. Rows: ' . $query->num_rows());
        }

        if ($type == 'all') {
            $array = $query->result_array();
            if (!empty($array)) {
                $paids = array_column($array, 'paid');
                $paid = array_sum($paids);
                $this->db->select_sum('amount', 'paid');
                $where = str_replace('t1.', '', $where);
                $where = str_replace('date', 'acc_date', $where);
                $totalpaid = $this->db->get_where('acc_payment', $where)->unbuffered_row()->paid;
                if ($totalpaid > $paid) {
                    $single = array();
                    $single['id'] = 0;
                    $single['date'] = '';
                    $single['user_id'] = 4;
                    $single['package_id'] = 1;
                    $single['turnover'] = 0;
                    $single['due_date'] = '';
                    $single['added_by'] = 1;
                    $single['status'] = 0;
                    $single['added_on'] = date('Y-m-d H:i:s');
                    $single['updated_on'] = date('Y-m-d H:i:s');
                    $single['paid'] = $totalpaid - $paid;
                    $single['paid_acc_fee'] = 0;
                    $array[] = $single;
                }
            }
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function updateturnover($data, $where)
    {
        logupdateoperations("accountancy", $data, $where);
        if ($this->db->update("accountancy", $data, $where)) {
            return array("status" => true, "message" => "Turnover Updated Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function deleteturnover($where)
    {
        if ($this->db->delete("accountancy", $where)) {
            return array("status" => true, "message" => "Turnover Deleted Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function savebankstatement($data)
    {
        if ($this->db->get_where('bank_statements', array('user_id' => $data['user_id'], 'firm_id' => $data['firm_id'], 'year' => $data['year'], 'month' => $data['month']))->num_rows() == 0) {
            $data['added_on'] = $data['updated_on'] = date('Y-m-d H:i:s');
            if ($this->db->insert("bank_statements", $data)) {
                $bank_statement_id = $this->db->insert_id();
                return array("status" => true, "message" => "Bank Statement Added Successfully!", "bank_statement_id" => $bank_statement_id);
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "Bank Statement Already Added!");
        }
    }

    public function getbankstatements($where = array(), $type = "all", $order_by = "t1.id")
    {
        $columns = "t1.*,t2.name,t2.mobile,t2.email";
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->order_by($order_by);
        $this->db->from('bank_statements t1');
        $this->db->join('users t2', 't1.user_id=t2.id');
        $query = $this->db->get();
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function getincomebyservice($where = "1", $period = 'monthly', $service_id = NULL)
    {
        // Build date filter based on period
        $date_filter = "";
        if ($period == 'monthly') {
            $date_filter = "DATE_FORMAT(t1.date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        } elseif ($period == 'quarterly') {
            $quarter = ceil(date('n') / 3);
            $date_filter = "QUARTER(t1.date) = $quarter AND YEAR(t1.date) = YEAR(CURDATE())";
        } elseif ($period == 'yearly') {
            $date_filter = "YEAR(t1.date) = YEAR(CURDATE())";
        }

        // Use amount field (which includes GST if applicable)
        $columns = "t2.id as service_id, t2.name as service_name, 
                    COALESCE(SUM(t1.amount), 0) as total_amount,
                    COUNT(DISTINCT t1.id) as total_orders,
                    COUNT(DISTINCT t1.user_id) as total_customers";

        $this->db->select($columns);
        $this->db->where($where);
        if (!empty($date_filter)) {
            $this->db->where($date_filter, NULL, FALSE);
        }
        if (!empty($service_id)) {
            $this->db->where('t1.service_id', $service_id);
        }
        $this->db->from('purchases t1');
        $this->db->join('services t2', 't1.service_id = t2.id', 'left');
        $this->db->group_by('t2.id, t2.name');
        $this->db->order_by('total_amount', 'DESC');
        $query = $this->db->get();

        return $query->result_array();
    }

    public function getincomebyperiod($where = array(), $period = 'monthly', $service_id = NULL, $start_date = NULL, $end_date = NULL, $selected_month = NULL, $selected_quarter = NULL, $selected_year = NULL)
    {
        // Build date filter based on period
        $date_filter = "";
        if (!empty($start_date) && !empty($end_date)) {
            $date_filter = "t1.date >= '$start_date' AND t1.date <= '$end_date'";
        } elseif ($period == 'monthly') {
            // If a specific month is selected, use it; otherwise use current month
            if (!empty($selected_month)) {
                // selected_month format: YYYY-MM
                $date_filter = "DATE_FORMAT(t1.date, '%Y-%m') = '$selected_month'";
            } else {
                $date_filter = "DATE_FORMAT(t1.date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            }
        } elseif ($period == 'quarterly') {
            // If a specific quarter is selected, use it; otherwise use current quarter
            if (!empty($selected_quarter)) {
                // selected_quarter format: YYYY-Q1, YYYY-Q2, etc.
                list($year, $quarter) = explode('-Q', $selected_quarter);
                $quarter = (int)$quarter;
                // Calculate month range for the quarter
                $start_month = ($quarter - 1) * 3 + 1;
                $end_month = $quarter * 3;
                $date_filter = "YEAR(t1.date) = $year AND MONTH(t1.date) >= $start_month AND MONTH(t1.date) <= $end_month";
            } else {
                $quarter = ceil(date('n') / 3);
                $date_filter = "QUARTER(t1.date) = $quarter AND YEAR(t1.date) = YEAR(CURDATE())";
            }
        } elseif ($period == 'yearly') {
            // If a specific year is selected, use it; otherwise use current year
            if (!empty($selected_year)) {
                $date_filter = "YEAR(t1.date) = '$selected_year'";
            } else {
                $date_filter = "YEAR(t1.date) = YEAR(CURDATE())";
            }
        }
        // If period is empty or 'all', don't apply date filter

        // Use amount field (which includes GST if applicable)
        $columns = "t2.id as service_id, t2.name as service_name, 
                    COALESCE(SUM(t1.amount), 0) as total_amount,
                    COUNT(DISTINCT t1.id) as total_orders,
                    COUNT(DISTINCT t1.user_id) as total_customers";

        $this->db->select($columns);
        // Handle where clause
        if (!empty($where) && is_array($where)) {
            $this->db->where($where);
        } elseif (!empty($where) && is_string($where) && $where != "1") {
            $this->db->where($where, NULL, FALSE);
        }
        if (!empty($date_filter)) {
            $this->db->where($date_filter, NULL, FALSE);
        }
        if (!empty($service_id)) {
            $this->db->where('t1.service_id', $service_id);
        }
        $this->db->from('purchases t1');
        $this->db->join('services t2', 't1.service_id = t2.id', 'left');
        $this->db->group_by('t2.id, t2.name');
        $this->db->order_by('total_amount', 'DESC');
        $query = $this->db->get();

        return $query->result_array();
    }

    public function get_gst_sales_report($period = 'all', $selected_month = NULL, $selected_quarter = NULL, $selected_year = NULL, $report_type = 'b2b')
    {
        $has_purchase_date = $this->db->field_exists('date', 'purchases');
        $has_subtotal = $this->db->field_exists('subtotal', 'purchases');
        $has_gst_amount = $this->db->field_exists('gst_amount', 'purchases');
        $has_gst_rate = $this->db->field_exists('gst_rate', 'purchases');
        $has_invoice_no = $this->db->field_exists('invoice_no', 'invoices');
        $has_invoice_date = $this->db->field_exists('invoice_date', 'invoices');
        $has_firm_gstin = $this->db->field_exists('gstin', 'firms');
        $has_firm_parent_id = $this->db->field_exists('parent_id', 'firms');
        $has_customer_parent_id = $this->db->field_exists('parent_id', 'customers');

        $purchase_date_col = $has_purchase_date ? 't1.date' : 't1.added_on';
        $purchase_month_expr = "DATE_FORMAT({$purchase_date_col}, '%Y-%m')";
        $purchase_year_expr = "YEAR({$purchase_date_col})";
        $purchase_month_num_expr = "MONTH({$purchase_date_col})";
        $purchase_quarter_expr = "QUARTER({$purchase_date_col})";

        $gst_amount_expr = $has_gst_amount ? 'COALESCE(t1.gst_amount, 0)' : '0';
        $subtotal_expr = $has_subtotal ? 'COALESCE(t1.subtotal, (t1.amount - ' . $gst_amount_expr . '))' : '(t1.amount - ' . $gst_amount_expr . ')';
        $gst_rate_expr = $has_gst_rate
            ? "COALESCE(t1.gst_rate, CASE WHEN ({$subtotal_expr}) > 0 AND ({$gst_amount_expr}) > 0 THEN ROUND((({$gst_amount_expr}) * 100) / ({$subtotal_expr}), 2) ELSE 0 END)"
            : "CASE WHEN ({$subtotal_expr}) > 0 AND ({$gst_amount_expr}) > 0 THEN ROUND((({$gst_amount_expr}) * 100) / ({$subtotal_expr}), 2) ELSE 0 END";

        $invoice_no_expr = $has_invoice_no ? "COALESCE(t4.invoice_no, '')" : "''";
        $invoice_date_expr = $has_invoice_date ? "COALESCE(t4.invoice_date, {$purchase_date_col})" : $purchase_date_col;
        $recipient_gstin_expr = $has_firm_gstin ? "COALESCE(t3.gstin, '')" : "''";
        $recipient_pan_expr = "COALESCE(NULLIF(t7.pan, ''), NULLIF(t8.pan, ''), '')";

        $date_filter = "";
        if ($period == 'monthly') {
            if (!empty($selected_month)) {
                $date_filter = $purchase_month_expr . " = " . $this->db->escape($selected_month);
            } else {
                $date_filter = $purchase_month_expr . " = DATE_FORMAT(CURDATE(), '%Y-%m')";
            }
        } elseif ($period == 'quarterly') {
            if (!empty($selected_quarter) && strpos($selected_quarter, '-Q') !== false) {
                list($year, $quarter) = explode('-Q', $selected_quarter);
                $year = (int)$year;
                $quarter = (int)$quarter;
                $start_month = ($quarter - 1) * 3 + 1;
                $end_month = $quarter * 3;
                $date_filter = "{$purchase_year_expr} = {$year} AND {$purchase_month_num_expr} >= {$start_month} AND {$purchase_month_num_expr} <= {$end_month}";
            } else {
                $quarter = ceil(date('n') / 3);
                $date_filter = "{$purchase_quarter_expr} = {$quarter} AND {$purchase_year_expr} = YEAR(CURDATE())";
            }
        } elseif ($period == 'yearly') {
            if (!empty($selected_year)) {
                $year = (int)$selected_year;
                $date_filter = "{$purchase_year_expr} = {$year}";
            } else {
                $date_filter = "{$purchase_year_expr} = YEAR(CURDATE())";
            }
        }
        // When period is "all", no date filter is applied.

        // Place of supply should be buyer/customer state name.
        $place_of_supply_expr = "''";
        if ($has_customer_parent_id) {
            $place_of_supply_expr = "COALESCE(t5.name, '')";
        } elseif ($has_firm_parent_id) {
            $place_of_supply_expr = "COALESCE(t5.name, '')";
        }

        $columns = "t1.id as order_id,
                    t1.user_id,
                    {$purchase_date_col} as purchase_date,
                    t1.amount as invoice_value,
                    {$subtotal_expr} as taxable_value,
                    {$gst_rate_expr} as gst_rate,
                    COALESCE(t2.name, '') as receiver_name,
                    {$recipient_gstin_expr} as recipient_gstin,
                    {$recipient_pan_expr} as recipient_pan,
                    {$invoice_no_expr} as invoice_no,
                    {$invoice_date_expr} as invoice_date,
                    COALESCE({$place_of_supply_expr}, '') as place_of_supply";

        $this->db->select($columns, false);
        $this->db->from('purchases t1');
        $this->db->join('users t2', 't1.user_id = t2.id', 'left');
        if ($has_customer_parent_id) {
            $this->db->join('customers t6', 't1.user_id = t6.user_id', 'left');
        }
        // PAN from KYC: prefer firm-level KYC PAN, then user-level PAN
        $this->db->join('kyc t7', 't1.user_id = t7.user_id AND t1.firm_id = t7.firm_id', 'left');
        $this->db->join('kyc t8', 't1.user_id = t8.user_id AND (t8.firm_id = 0 OR t8.firm_id IS NULL)', 'left');
        $this->db->join('firms t3', 't1.firm_id = t3.id', 'left');
        $this->db->join('invoices t4', 't4.order_id = t1.id', 'left');
        if ($has_customer_parent_id) {
            $this->db->join('area t5', 't6.parent_id = t5.id', 'left');
        } elseif ($has_firm_parent_id) {
            $this->db->join('area t5', 't3.parent_id = t5.id', 'left');
        }
        if (!empty($date_filter)) {
            $this->db->where($date_filter, null, false);
        }
        // Strict split:
        // B2B -> include only rows where firm GSTIN exists
        // B2C -> include only rows where firm GSTIN missing and PAN exists
        if ($report_type === 'b2b') {
            $this->db->where("{$recipient_gstin_expr} !=", '');
        } elseif ($report_type === 'b2c') {
            $this->db->where("{$recipient_gstin_expr} =", '');
            $this->db->where("{$recipient_pan_expr} !=", '');
        }
        $this->db->order_by($purchase_date_col, 'DESC');
        $query = $this->db->get();

        if (!$query) {
            $error = $this->db->error();
            log_message('error', 'get_gst_sales_report query failed: ' . (!empty($error['message']) ? $error['message'] : 'Unknown DB error'));
            log_message('error', 'get_gst_sales_report SQL: ' . $this->db->last_query());
            return array();
        }

        return $query->result_array();
    }

    public function getcustomersbyservice($where = array(), $service_id = NULL, $start_date = NULL, $end_date = NULL)
    {
        // Select all columns from purchases, then add specific ones
        // This handles cases where GST columns may or may not exist
        $columns = "t1.*, 
                    t2.id as customer_id, t2.name as customer_name, t2.mobile, t2.email,
                    t3.id as service_id, t3.name as service_name";

        $this->db->select($columns);
        // Handle where clause
        if (!empty($where) && is_array($where)) {
            $this->db->where($where);
        } elseif (!empty($where) && is_string($where) && $where != "1") {
            $this->db->where($where, NULL, FALSE);
        }
        if (!empty($service_id)) {
            $this->db->where('t1.service_id', $service_id);
        }
        if (!empty($start_date) && !empty($end_date)) {
            $this->db->where("t1.date >= '$start_date' AND t1.date <= '$end_date'", NULL, FALSE);
        }
        $this->db->from('purchases t1');
        $this->db->join('users t2', 't1.user_id = t2.id', 'left');
        $this->db->join('services t3', 't1.service_id = t3.id', 'left');
        $this->db->order_by('t1.date', 'DESC');
        $this->db->order_by('t1.amount', 'DESC');
        $query = $this->db->get();

        return $query->result_array();
    }
}
