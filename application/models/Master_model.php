<?php
class Master_model extends CI_Model
{
    protected $service_options_table_checked = false;
    protected $synced_default_options = array();

    function __construct()
    {
        parent::__construct();
        $this->db->db_debug = false;
    }

    public function addservice($data)
    {
        if ($this->db->get_where('services', array('name' => $data['name']))->num_rows() == 0) {
            if ($this->db->insert("services", $data)) {
                $service_id = $this->db->insert_id();
                return array("status" => true, "message" => "Service Added Successfully!", 'service_id' => $service_id);
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "Service Already Added!");
        }
    }

    public function getservices($where = array(), $type = "all")
    {
        $this->db->where($where);
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get("services");
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function deleteservice($id)
    {
        $where = array("id" => $id);
        if ($this->db->delete("services", $where)) {
            return array("status" => true, "message" => "Service Deleted Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function updateservice($data)
    {
        $id = $data['id'];
        unset($data['id']);
        $where = array("id" => $id);
        if ($this->db->get_where('services', array('name' => $data['name'], "id!=" => $id))->num_rows() == 0) {
            if ($this->db->update("services", $data, $where)) {
                return array("status" => true, "message" => "Service Updated Successfully!");
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "Service Already Added!");
        }
    }

    public function getpackages($where = array(), $type = "all")
    {
        // Handle special case for turnover-based package selection
        if (isset($where['turnover>']) && !empty($where['turnover>'])) {
            $client_turnover = $where['turnover>'];
            $package_name = isset($where['name']) ? $where['name'] : '';

            // Remove turnover> from where clause for base query
            unset($where['turnover>']);

            // Get all packages with matching name
            $this->db->where($where);
            $this->db->order_by('turnover', 'ASC');
            $this->db->order_by('id', 'ASC');
            $query = $this->db->get("packages");
            $all_packages = $query->result_array();

            if (empty($all_packages)) {
                return $type == 'all' ? array() : null;
            }

            // If client turnover > 10000000, use ">100 Lac Per 100 Lac" package
            if ($client_turnover > 10000000) {
                foreach ($all_packages as $package) {
                    if (strpos($package['remarks'], '>100 Lac Per 100 Lac') !== false) {
                        return $type == 'all' ? array($package) : $package;
                    }
                }
            }

            // For turnover <= 10000000, find the highest package with turnover <= client turnover
            // Exclude ">100 Lac Per 100 Lac" packages
            $matching_packages = array();
            foreach ($all_packages as $package) {
                if (strpos($package['remarks'], '>100 Lac Per 100 Lac') === false) {
                    if ($package['turnover'] <= $client_turnover) {
                        $matching_packages[] = $package;
                    }
                }
            }

            if (!empty($matching_packages)) {
                // Return the package with highest turnover
                $selected = end($matching_packages);
                return $type == 'all' ? array($selected) : $selected;
            }

            // Fallback: return first package if no match found
            return $type == 'all' ? array($all_packages[0]) : $all_packages[0];
        }

        // Standard query for other cases
        $this->db->where($where);
        $this->db->order_by('id', 'ASC');
        $query = $this->db->get("packages");
        $result = array();
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function addpackage($data)
    {
        if ($this->db->insert("packages", $data)) {
            $package_id = $this->db->insert_id();
            return array("status" => true, "message" => "Package Added Successfully!", 'package_id' => $package_id);
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function updatepackage($data)
    {
        $id = $data['id'];
        unset($data['id']);
        $where = array("id" => $id);
        if ($this->db->update("packages", $data, $where)) {
            return array("status" => true, "message" => "Package Updated Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function deletepackage($id)
    {
        $where = array("id" => $id);
        if ($this->db->delete("packages", $where)) {
            return array("status" => true, "message" => "Package Deleted Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function getdocuments($where = array(), $type = "all")
    {
        $this->db->where($where);
        $query = $this->db->get("documents");
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function addreqdocuments($data)
    {
        if ($this->db->get_where('docs_required', array('service_id' => $data[0]['service_id']))->num_rows() == 0) {
            if ($this->db->insert_batch("docs_required", $data)) {
                return array("status" => true, "message" => "Required Documents Added Successfully!");
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "Required Documents Already Added!");
        }
    }

    public function getservicedocuments($where = array(), $type = "all", $columns = false)
    {
        if ($columns) {
            $columns = "t1.id";
        } else {
            $columns = "t1.id,t1.document_id,t1.display_name,t1.slug,t2.value,t2.file,ifnull(t2.file_type,'--') as file_type,";
            $columns .= "CASE WHEN t2.value=0 && t2.file=0 THEN '--' ";
            $columns .= " WHEN t2.value=1 && t2.file=0 THEN 'Value'";
            $columns .= " WHEN t2.value=1 && t2.file>0 THEN 'Value, File Upload'";
            $columns .= " WHEN t2.value=0 && t2.file>0 THEN 'File Upload' ELSE '--' END as type,";
            $columns .= " t2.pattern";
        }
        $this->db->select($columns);
        $this->db->where($where);
        $this->db->from('docs_required t1');
        $this->db->join('documents t2', 't1.document_id=t2.id');
        $this->db->join('services t3', 't1.service_id=t3.id');
        $this->db->order_by('t1.id');
        $query = $this->db->get();
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    public function updatereqdocuments($data)
    {
        $bulk = array();
        foreach ($data as $single) {
            if ($single['id'] == '') {
                unset($single['status'], $single['type']);
                $bulk[] = $single;
            } elseif ($single['status'] == 1) {
                unset($single['status'], $single['type']);
                $check = $this->db->get_where('docs_required', $single)->num_rows();
                if ($check == 0) {
                    $this->db->update('docs_required', $single, ['id' => $single['id']]);;
                }
            } elseif ($single['status'] == 0) {
                $this->db->delete('docs_required', ['id' => $single['id']]);;
            }
        }
        if (!empty($bulk)) {
            $this->db->insert_batch("docs_required", $bulk);
        }
        return array('status' => true, 'message' => '');
    }

    public function deletereqdocuments($service_id)
    {
        $where = array('service_id' => $service_id);
        if ($this->db->delete('docs_required', $where)) {
            return array("status" => true, "message" => "Required Documents Deleted Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    public function addsalarypercent($data)
    {
        $status = TRUE;
        $msg = "Salary Percent Added Successfully!";
        $getlast = $this->db->get_where("commission_percent", ['status' => 1]);
        if ($getlast->num_rows() > 0) {
            $msg = "Salary Percent Updated Successfully!";
            $last = $getlast->unbuffered_row('array');
            if ($last['percent'] == $data['percent']) {
                $status = false;
            }
        }
        if ($status) {
            $datetime = date('Y-m-d H:i:s');
            $update = $this->db->update("commission_percent", ['status' => 0, 'updated_on' => $datetime], ['status' => 1]);
            $data['added_on'] = $data['updated_on'] = $datetime;
            if ($this->db->insert("commission_percent", $data)) {
                $service_id = $this->db->insert_id();
                return array("status" => true, "message" => $msg);
            } else {
                $error = $this->db->error();
                return array("status" => false, "message" => $error['message']);
            }
        } else {
            return array("status" => false, "message" => "No Changes Done!");
        }
    }

    public function getsalarypercents($where = array(), $type = "all", $order_by = "id desc")
    {
        $this->db->where($where);
        $this->db->order_by($order_by);
        $query = $this->db->get("commission_percent");
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    /**
     * Ensure service_options table exists before querying or seeding defaults.
     */
    protected function ensure_service_options_table()
    {
        if ($this->service_options_table_checked) {
            return;
        }
        if (!$this->db->table_exists('service_options')) {
            $prefix = $this->db->dbprefix;
            $this->db->query("CREATE TABLE IF NOT EXISTS `{$prefix}service_options` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `service_id` int(11) NOT NULL,
                `option_key` varchar(50) NOT NULL,
                `display_name` varchar(100) NOT NULL,
                `rate` float NOT NULL,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `added_on` datetime NOT NULL,
                `updated_on` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `service_id` (`service_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
        $this->service_options_table_checked = true;
    }

    /**
     * Default service options catalog keyed by canonical service slug.
     */
    protected function get_default_service_options_catalog()
    {
        $catalog = array(
            'gst-return' => array(
                array('option_key' => 'monthly', 'display_name' => 'Monthly', 'rate' => null),
                array('option_key' => 'quarterly', 'display_name' => 'Quarterly', 'rate' => null),
            ),
            'income-tax-return' => array(
                array('option_key' => 'audit', 'display_name' => 'Audit', 'rate' => 3000),
                array('option_key' => 'non-audit', 'display_name' => 'Non Audit', 'rate' => 1500),
                array('option_key' => 'capital-gain', 'display_name' => 'Capital Gain', 'rate' => 5000),
            ),
            'project-report-for-cash-credit-loan' => array(
                array('option_key' => 'Loan amount 5Lac', 'display_name' => 'Loan amount 5Lac', 'rate' => 2500),
                array('option_key' => 'Loan amount 10Lac', 'display_name' => 'Loan amount 10Lac', 'rate' => 3500),
                array('option_key' => 'Loan amount 20Lac', 'display_name' => 'Loan amount 20Lac', 'rate' => 4000),
                array('option_key' => 'Loan amount 30Lac', 'display_name' => 'Loan amount 30Lac', 'rate' => 6000),
                array('option_key' => 'Loan amount 50Lac', 'display_name' => 'Loan amount 50Lac', 'rate' => 7500),
                array('option_key' => 'Loan amount above 50Lac', 'display_name' => 'Loan amount above 50Lac', 'rate' => 10000),
            ),
            'tax-audit-legal' => array(
                array('option_key' => 'GTO 1cr', 'display_name' => 'GTO 1cr', 'rate' => 8000),
                array('option_key' => 'GTO 2cr', 'display_name' => 'GTO 2cr', 'rate' => 10000),
                array('option_key' => 'GTO 3cr', 'display_name' => 'GTO 3cr', 'rate' => 12000),
                array('option_key' => 'GTO 5cr', 'display_name' => 'GTO 5cr', 'rate' => 15000),
                array('option_key' => 'GTO 10cr', 'display_name' => 'GTO 10cr', 'rate' => 20000),
                array('option_key' => 'GTO 20cr', 'display_name' => 'GTO 20cr', 'rate' => 25000),
                array('option_key' => 'GTO above 50cr', 'display_name' => 'GTO above 50cr', 'rate' => 40000),
            ),
            'vat-audit' => array(
                array('option_key' => 'GTO 50cr', 'display_name' => 'GTO 50cr', 'rate' => 30000),
            ),
            'project-report-for-term-loan' => array(
                array('option_key' => '5L', 'display_name' => '5L', 'rate' => 5000),
                array('option_key' => '10L', 'display_name' => '10L', 'rate' => 7000),
                array('option_key' => '20L', 'display_name' => '20L', 'rate' => 10000),
            ),
        );

        $name_aliases = array(
            'gst return' => 'gst-return',
            'income tax return' => 'income-tax-return',
            'project report for cash credit loan' => 'project-report-for-cash-credit-loan',
            'tax audit & legal' => 'tax-audit-legal',
            'vat audit' => 'vat-audit',
            'project report for term loan' => 'project-report-for-term-loan',
        );

        return array('catalog' => $catalog, 'name_aliases' => $name_aliases);
    }

    /**
     * Seed default service options when missing in service_options table (e.g. fresh/unsynced server DB).
     */
    public function ensure_default_service_options($services)
    {
        if (empty($services) || !is_array($services)) {
            return;
        }

        $defs = $this->get_default_service_options_catalog();
        $catalog = $defs['catalog'];
        $name_aliases = $defs['name_aliases'];

        $matched_services = array();
        foreach ($services as $svc) {
            if (empty($svc['id'])) {
                continue;
            }
            $sid = (int)$svc['id'];
            if (isset($this->synced_default_options[$sid])) {
                continue;
            }

            $slug_key = strtolower(trim((string)($svc['slug'] ?? '')));
            $name_key = strtolower(trim(preg_replace('/\s+/', ' ', (string)($svc['name'] ?? ''))));

            $canonical = null;
            if ($slug_key !== '' && isset($catalog[$slug_key])) {
                $canonical = $slug_key;
            } elseif ($name_key !== '' && isset($name_aliases[$name_key])) {
                $canonical = $name_aliases[$name_key];
            }

            if ($canonical !== null) {
                $matched_services[$sid] = array(
                    'service' => $svc,
                    'canonical' => $canonical,
                );
            }
        }

        if (empty($matched_services)) {
            return;
        }

        $this->ensure_service_options_table();

        $check_ids = array_keys($matched_services);
        $q = $this->db->select('DISTINCT(service_id) as service_id', false)
                      ->where_in('service_id', $check_ids)
                      ->get('service_options');

        $existing_ids = array();
        if ($q && is_object($q)) {
            foreach ($q->result_array() as $row) {
                $existing_ids[(int)$row['service_id']] = true;
            }
        }

        $now = date('Y-m-d H:i:s');
        $to_insert = array();

        foreach ($matched_services as $sid => $entry) {
            $this->synced_default_options[$sid] = true;
            if (isset($existing_ids[$sid])) {
                continue;
            }

            $svc = $entry['service'];
            $base_rate = isset($svc['rate']) && (float)$svc['rate'] > 0 ? (float)$svc['rate'] : 500.0;
            foreach ($catalog[$entry['canonical']] as $opt) {
                $to_insert[] = array(
                    'service_id' => $sid,
                    'option_key' => $opt['option_key'],
                    'display_name' => $opt['display_name'],
                    'rate' => $opt['rate'] !== null ? (float)$opt['rate'] : $base_rate,
                    'status' => 1,
                    'added_on' => $now,
                    'updated_on' => $now,
                );
            }
        }

        if (!empty($to_insert)) {
            $this->db->insert_batch('service_options', $to_insert);
        }
    }

    /**
     * Get active service options for a single service, seeding defaults if missing.
     */
    public function get_active_service_options($service_id)
    {
        $service_id = (int)$service_id;
        if ($service_id <= 0) {
            return array();
        }

        $options = $this->getserviceoptions(array('service_id' => $service_id, 'status' => 1), 'all');
        if (empty($options) && !isset($this->synced_default_options[$service_id])) {
            $svc = $this->getservices(array('id' => $service_id), 'single');
            if (!empty($svc)) {
                $this->ensure_default_service_options(array($svc));
                $options = $this->getserviceoptions(array('service_id' => $service_id, 'status' => 1), 'all');
            }
        }
        return $options;
    }

    /**
     * Get service options (for dynamic pricing like Income Tax Return)
     */
    public function getserviceoptions($where = array(), $type = "all")
    {
        $this->ensure_service_options_table();
        $this->db->where($where);
        $this->db->order_by('id', 'ASC');
        $query = $this->db->get("service_options");
        if (!$query || !is_object($query)) {
            return $type == 'all' ? array() : null;
        }
        if ($type == 'all') {
            $array = $query->result_array();
        } else {
            $array = $query->unbuffered_row('array');
        }
        return $array;
    }

    /**
     * Get service options as key-value array (option_key => rate)
     */
    public function getserviceoptionspricing($service_id)
    {
        $options = $this->getserviceoptions(array('service_id' => $service_id, 'status' => 1), 'all');
        $pricing = array();
        $display_names = array();
        if (!empty($options)) {
            foreach ($options as $option) {
                $pricing[$option['option_key']] = $option['rate'];
                $display_names[$option['option_key']] = $option['display_name'];
            }
        }
        return array('pricing' => $pricing, 'display_names' => $display_names);
    }

    /**
     * Add service option
     */
    public function addserviceoption($data)
    {
        $datetime = date('Y-m-d H:i:s');
        $data['added_on'] = $datetime;
        $data['updated_on'] = $datetime;
        if ($this->db->insert("service_options", $data)) {
            $option_id = $this->db->insert_id();
            return array("status" => true, "message" => "Service Option Added Successfully!", 'option_id' => $option_id);
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    /**
     * Update service option
     */
    public function updateserviceoption($data)
    {
        $id = $data['id'];
        unset($data['id']);
        $data['updated_on'] = date('Y-m-d H:i:s');
        $where = array("id" => $id);
        if ($this->db->update("service_options", $data, $where)) {
            return array("status" => true, "message" => "Service Option Updated Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }

    /**
     * Delete service option
     */
    public function deleteserviceoption($id)
    {
        $where = array("id" => $id);
        if ($this->db->delete("service_options", $where)) {
            return array("status" => true, "message" => "Service Option Deleted Successfully!");
        } else {
            $error = $this->db->error();
            return array("status" => false, "message" => $error['message']);
        }
    }
}
