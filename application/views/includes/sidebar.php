<!--APP-SIDEBAR-->
<div class="sticky">
    <div class="app-sidebar__overlay" data-bs-toggle="sidebar"></div>
    <div class="app-sidebar">
        <div class="side-header">
            <a class="header-brand1" href="<?= base_url('home/'); ?>">
                <img src="<?= file_url('assets/images/logo.png'); ?>" class="header-brand-img desktop-logo" style="height:50px;" alt="logo">
                <img src="<?= file_url('assets/images/logo.png'); ?>" class="header-brand-img toggle-logo" alt="logo">
                <img src="<?= file_url('assets/images/fav.png'); ?>" class="header-brand-img light-logo" alt="logo">
                <img src="<?= file_url('assets/images/logo.png'); ?>" class="header-brand-img light-logo1" style="height:50px;" alt="logo">
            </a>
            <!-- LOGO -->
        </div>
        <div class="main-sidemenu">
            <div class="slide-left disabled" id="slide-left"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z" />
                </svg></div>
            <ul class="side-menu">
                <li class="sub-category">
                    <h3>Main</h3>
                </li>
                <li class="slide">
                    <a class="side-menu__item <?= activate_menu('home'); ?>" data-bs-toggle="slide" href="<?= base_url('home/'); ?>"><i class="side-menu__icon fe fe-home"></i><span class="side-menu__label">Dashboard</span></a>
                </li>
                <?php if ($this->session->role == 'superadmin' || $this->session->role == 'admin') { ?>
                    <li class="slide <?= activate_dropdown('masterkey') ?>">
                        <a class="side-menu__item <?= activate_dropdown('masterkey') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-key"></i><span class="side-menu__label">Masterkey</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Masterkey</a></li>
                            <li><a href="<?= base_url('masterkey/'); ?>" class="slide-item <?= activate_menu('masterkey'); ?>"> Services</a></li>
                            <li><a href="<?= base_url('masterkey/serviceoptions/'); ?>" class="slide-item <?= activate_menu('masterkey/serviceoptions'); ?>"> Service Options</a></li>
                            <li><a href="<?= base_url('masterkey/documents/'); ?>" class="slide-item <?= activate_menu('masterkey/documents'); ?>"> Documents Required</a></li>
                            <li><a href="<?= base_url('masterkey/documentlist/'); ?>" class="slide-item <?= activate_menu('masterkey/documentlist'); ?>"> Documents Required List</a></li>
                            <li><a href="<?= base_url('masterkey/packages/'); ?>" class="slide-item <?= activate_menu('masterkey/packages'); ?>"> Packages</a></li>
                            <li><a href="<?= base_url('masterkey/salarypercent/'); ?>" class="slide-item <?= activate_menu('masterkey/salarypercent'); ?>"> Employee Salary Percent</a></li>
                            <li><a href="<?= base_url('masterkey/securityamount/'); ?>" class="slide-item <?= activate_menu('masterkey/securityamount'); ?>"> Security Amount</a></li>
                        </ul>
                    </li>
                    <li class="slide <?= activate_dropdown('customers') ?>">
                        <a class="side-menu__item <?= activate_dropdown('customers') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-users"></i><span class="side-menu__label">Customers</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Customers</a></li>
                            <li><a href="<?= base_url('customers/'); ?>" class="slide-item <?= activate_menu('customers'); ?>"> Customers</a></li>
                            <li><a href="<?= base_url('customers/addcustomer/'); ?>" class="slide-item <?= activate_menu('customers/addcustomer'); ?>"> Add Customer</a></li>
                            <li><a href="<?= base_url('customers/bulkimport/'); ?>" class="slide-item <?= activate_menu('customers/bulkimport'); ?>"> Bulk Import Customers</a></li>
                            <li><a href="<?= base_url('customers/walletrecharge/'); ?>" class="slide-item <?= activate_menu('customers/walletrecharge'); ?>"> Wallet Recharge</a></li>
                            <li><a href="<?= base_url('customers/walletrechargelist/'); ?>" class="slide-item <?= activate_menu('customers/walletrechargelist'); ?>"> Wallet Recharge History</a></li>
                            <li><a href="<?= base_url('customers/customerpurchases/'); ?>" class="slide-item <?= activate_menu('customers/customerpurchases'); ?>"> Customer Purchases</a></li>
                            <li><a href="<?= base_url('customers/customerfirmdetails/'); ?>" class="slide-item <?= activate_menu('customers/customerfirmdetails'); ?>"> Customer Firm Details</a></li>
                            <li><a href="<?= base_url('customers/packageswitchrequests/'); ?>" class="slide-item <?= activate_menu('customers/packageswitchrequests'); ?>"> Customer Package Switch Request</a></li>
                            <li><a href="<?= base_url('customers/firmdeleterequests/'); ?>" class="slide-item <?= activate_menu('customers/firmdeleterequests'); ?>"> Firm Delete Request</a></li>
                            <li><a href="<?= base_url('customers/firmeditrequests/'); ?>" class="slide-item <?= activate_menu('customers/firmeditrequests'); ?>"> Firm Edit Request</a></li>
                            <li><a href="<?= base_url('customers/packagedeleterequests/'); ?>" class="slide-item <?= activate_menu('customers/packagedeleterequests'); ?>"> Package Delete Request</a></li>
                            <li><a href="<?= base_url('customers/customerwisereport/'); ?>" class="slide-item <?= activate_menu('customers/customerwisereport'); ?>"> Customer Wise Report</a></li>
                        </ul>
                    </li>
                    <li class="slide <?= activate_dropdown('orders') ?>">
                        <a class="side-menu__item <?= activate_dropdown('orders') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-file-text"></i><span class="side-menu__label">Orders</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Orders</a></li>
                            <li><a href="<?= base_url('orders/'); ?>" class="slide-item <?= activate_menu('orders'); ?>"> Orders</a></li>
                            <?php /*?><li><a href="<?= base_url('orders/yearlyturnover/'); ?>" class="slide-item <?= activate_menu('orders/yearlyturnover'); ?>"> Add Turnover</a></li><?php */ ?>
                            <li><a href="<?= base_url('orders/monthlyturnover/'); ?>" class="slide-item <?= activate_menu('orders/monthlyturnover'); ?>"> Add Turnover</a></li>
                            <li><a href="<?= base_url('orders/turnoversheet/'); ?>" class="slide-item <?= activate_menu('orders/turnoversheet'); ?>"> Turnover Sheet</a></li>
                            <li><a href="<?= base_url('reports/assignmentreports/'); ?>" class="slide-item <?= activate_menu('reports/assignmentreports'); ?>"> Assignment Reports</a></li>
                            <li><a href="<?= base_url('invoices/'); ?>" class="slide-item <?= activate_menu('invoices'); ?>"> Invoices</a></li>
                        </ul>
                    </li>
                    <li class="slide <?= activate_dropdown(['employees', 'wallet']) ?>">
                        <a class="side-menu__item <?= activate_dropdown(['employees', 'wallet']) ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-users"></i><span class="side-menu__label">Employees</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Employees</a></li>
                            <li><a href="<?= base_url('employees/'); ?>" class="slide-item <?= activate_menu('employees'); ?>"> Employees</a></li>
                            <li><a href="<?= base_url('employees/add/'); ?>" class="slide-item <?= activate_menu('employees/add'); ?>">Add Employee</a></li>
                            <li><a href="<?= base_url('wallet/employeeearnings/'); ?>" class="slide-item <?= activate_menu('wallet/employeeearnings'); ?>">Employee Earnings</a></li>
                            <li><a href="<?= base_url('employees/employeepayment/'); ?>" class="slide-item <?= activate_menu('employees/employeepayment'); ?>">Employee Payment</a></li>
                            <li><a href="<?= base_url('employees/employeepaymentlist/'); ?>" class="slide-item <?= activate_menu('employees/employeepaymentlist'); ?>">Employee Payment List</a></li>
                        </ul>
                    </li>
                    <li class="slide <?= activate_dropdown('users') ?>">
                        <a class="side-menu__item <?= activate_dropdown('users') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-users"></i><span class="side-menu__label">Users</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Users</a></li>
                            <li><a href="<?= base_url('users/'); ?>" class="slide-item <?= activate_menu('users'); ?>"> Users</a></li>
                            <li><a href="<?= base_url('users/roles/'); ?>" class="slide-item <?= activate_menu('users/roles'); ?>"> User Roles</a></li>
                        </ul>
                    </li>
                    <li class="slide <?= activate_dropdown(['reports/adminincome', 'reports/servicecustomers', 'reports/gstsalesreport', 'reports/gstsalesreportb2c']) ?>">
                        <a class="side-menu__item <?= activate_dropdown(['reports/adminincome', 'reports/servicecustomers', 'reports/gstsalesreport', 'reports/gstsalesreportb2c']) ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-line-chart"></i><span class="side-menu__label">Income Reports</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Income Reports</a></li>
                            <li><a href="<?= base_url('reports/adminincome/'); ?>" class="slide-item <?= activate_menu('reports/adminincome'); ?>"> Income by Service</a></li>
                            <li><a href="<?= base_url('reports/servicecustomers/'); ?>" class="slide-item <?= activate_menu('reports/servicecustomers'); ?>"> Service Customers</a></li>
                            <li><a href="<?= base_url('reports/gstsalesreport/'); ?>" class="slide-item <?= activate_menu('reports/gstsalesreport'); ?>"> GST Sales Report B2B</a></li>
                            <li><a href="<?= base_url('reports/gstsalesreportb2c/'); ?>" class="slide-item <?= activate_menu('reports/gstsalesreportb2c'); ?>"> GST Sales Report B2C</a></li>
                        </ul>
                    </li>
                    <?php if ($this->session->role == 'superadmin' || $this->session->role == 'admin' || $this->session->role == 'employee') { ?>
                        <li class="slide">
                            <a class="side-menu__item <?= activate_menu('reports/pendingpackagerenewals'); ?>" data-bs-toggle="slide" href="<?= base_url('reports/pendingpackagerenewals/'); ?>"><i class="side-menu__icon fa fa-refresh"></i><span class="side-menu__label">Pending Package Renewals</span></a>
                        </li>
                    <?php } ?>
                    <li class="slide">
                         <a class="side-menu__item <?= activate_menu(['creditlimit', 'creditlimit/index']); ?>" data-bs-toggle="slide" href="<?= base_url('creditlimit'); ?>"><i class="side-menu__icon fa fa-credit-card"></i><span class="side-menu__label">Credit Limit</span></a>
                    </li>
                    <li class="slide">
                         <a class="side-menu__item <?= activate_menu('creditlimit/percentage'); ?>" data-bs-toggle="slide" href="<?= base_url('creditlimit/percentage'); ?>"><i class="side-menu__icon fa fa-percent"></i><span class="side-menu__label">Credit Limit Percentage</span></a>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('chat'); ?>" data-bs-toggle="slide" href="<?= base_url('chat/'); ?>"><i class="side-menu__icon fa fa-comments"></i><span class="side-menu__label">Chat</span></a>
                    </li>
                <?php } elseif ($this->session->role == 'customer') {
                ?>
                    <li class="slide <?= activate_dropdown(['profile', 'home'], ['bankstatement']) ?>">
                        <a class="side-menu__item <?= activate_dropdown(['profile', 'home'], ['bankstatement']) ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-id-card"></i><span class="side-menu__label">Profile</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Profile</a></li>
                            <li><a href="<?= base_url('profile/'); ?>" class="slide-item <?= activate_menu('profile'); ?>"> Profile</a></li>
                            <li><a href="<?= base_url('profile/kyc/'); ?>" class="slide-item <?= activate_menu('profile/kyc'); ?>"> KYC</a></li>
                            <li><a href="<?= base_url('profile/certificates/'); ?>" class="slide-item <?= activate_menu('profile/certificates'); ?>"> Certificates</a></li>
                            <li><a href="<?= base_url('editpassword/'); ?>" class="slide-item <?= activate_menu('home/editpassword'); ?>"> Edit Password</a></li>
                        </ul>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('services'); ?>" data-bs-toggle="slide" href="<?= base_url('services/'); ?>"><i class="side-menu__icon fa fa-list"></i><span class="side-menu__label">Services</span></a>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('firms'); ?>" data-bs-toggle="slide" href="<?= base_url('firms/'); ?>"><i class="side-menu__icon fa fa-building"></i><span class="side-menu__label">Firms</span></a>
                    </li>
                    <li class="slide">
                        <?php
                        $active = array(
                            'services/purchasedservices',
                            'services/monthlyservices',
                            'services/openform',
                            'services/previewform'
                        );
                        ?>
                        <a class="side-menu__item <?= activate_menu($active); ?>" data-bs-toggle="slide" href="<?= base_url('services/purchasedservices/'); ?>"><i class="side-menu__icon fa fa-list"></i><span class="side-menu__label">Purchased Services</span></a>
                    </li>
                    <li class="slide">
                        <!-- Using fa-file-text-o for a simple document icon -->
                        <a class="side-menu__item <?= activate_menu('invoices'); ?>" data-bs-toggle="slide" href="<?= base_url('invoices/'); ?>"><i class="side-menu__icon fa fa-file-text-o"></i><span class="side-menu__label">My Bills / Invoices</span></a>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('home/workreports'); ?>" data-bs-toggle="slide" href="<?= base_url('home/workreports/'); ?>"><i class="side-menu__icon fa fa-file-text"></i><span class="side-menu__label">Work Reports</span></a>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('profile/olddata'); ?>" data-bs-toggle="slide" href="<?= base_url('profile/olddata/'); ?>"><i class="side-menu__icon fa fa-archive"></i><span class="side-menu__label">Old Data</span></a>
                    </li>
                    <?php /*?><li class="slide <?= activate_dropdown('firms') ?>">
                                <a class="side-menu__item <?= activate_dropdown('firms') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-users"></i><span class="side-menu__label">Firms</span><i class="angle fe fe-chevron-right"></i></a>
                                <ul class="slide-menu">
                                    <li class="side-menu-label1"><a href="javascript:void(0)">firms</a></li>
                                    <li><a href="<?= base_url('firms/'); ?>" class="slide-item <?= activate_menu('firms'); ?>"> firms</a></li>
                                    <li><a href="<?= base_url('users/roles/'); ?>" class="slide-item <?= activate_menu('users/roles'); ?>"> User Roles</a></li>
                                </ul>
                            </li><?php */ ?>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('wallet/mywallet'); ?>" data-bs-toggle="slide" href="<?= base_url('mywallet/'); ?>"><i class="side-menu__icon fa fa-money"></i><span class="side-menu__label">Wallet</span></a>
                    </li>
                    <li class="slide">
                         <a class="side-menu__item <?= activate_menu('creditlimit'); ?>" data-bs-toggle="slide" href="<?= base_url('creditlimit'); ?>"><i class="side-menu__icon fa fa-credit-card"></i><span class="side-menu__label">Credit Limit</span></a>
                    </li>
                    <?php
                    if (checkaccountancy(getuser(), $this->session->firm)) {
                    ?>
                        <li class="slide">
                            <a class="side-menu__item <?= activate_menu('profile/bankstatement'); ?>" data-bs-toggle="slide" href="<?= base_url('bankstatement/'); ?>"><i class="side-menu__icon fa fa-file-pdf-o"></i><span class="side-menu__label">Monthly Bank Statement</span></a>
                        </li>
                    <?php
                    }
                    ?>
                    <?php
                    // Check if user has Account Work package and its type
                    $is_monthly_type = false;
                    if ($this->session->role == 'customer') {
                        $user = getuser();
                        $year = $this->session->year;
                        $firm_id = $this->session->firm;
                        if (!empty($user) && !empty($year) && !empty($firm_id)) {
                            $where = array('user_id' => $user['id'], 'status' => 1, 'firm_id' => $firm_id, 'year' => $year);
                            $query = $this->db->get_where('customer_packages', $where);
                            if ($query->num_rows() > 0) {
                                $cpackage = $query->unbuffered_row('array');
                                $pkg_type = !empty($cpackage['package_type']) ? $cpackage['package_type'] : 'Turnover';
                                // Check if Monthly type package
                                if ($pkg_type == 'Monthly') {
                                    $is_monthly_type = true;
                                }
                            }
                        }
                    }
                    ?>
                    <li class="slide <?= activate_dropdown(['reports'], []) ?>">
                        <a class="side-menu__item <?= activate_dropdown(['reports'], []) ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-list-alt"></i><span class="side-menu__label">Fee Report</span><i class="angle fe fe-chevron-right"></i></a>
                        <ul class="slide-menu">
                            <li class="side-menu-label1"><a href="javascript:void(0)">Fee Report</a></li>
                            <li>
                                <?php if ($is_monthly_type) : ?>
                                    <a href="javascript:void(0);" class="slide-item text-muted" style="opacity: 0.5; cursor: not-allowed;" onclick="alert('Accounting Fee is not available for Monthly type packages. Monthly packages are auto-debited based on the fixed amount.'); return false;" title="Not available for Monthly type packages">
                                        <i class="fe fe-lock me-1"></i> Accounting Fee
                                    </a>
                                <?php else : ?>
                                    <a href="<?= base_url('reports/'); ?>" class="slide-item <?= activate_menu('reports'); ?>"> Accounting Fee</a>
                                <?php endif; ?>
                            </li>
                            <li><a href="<?= base_url('reports/otherfee/'); ?>" class="slide-item <?= activate_menu('reports/otherfee'); ?>"> Other Fee</a></li>
                            <li>
                                <?php if ($is_monthly_type) : ?>
                                    <a href="javascript:void(0);" class="slide-item text-muted" style="opacity: 0.5; cursor: not-allowed;" onclick="alert('Accounting Pay is not available for Monthly type packages. Monthly packages are auto-debited based on the fixed amount.'); return false;" title="Not available for Monthly type packages">
                                        <i class="fe fe-lock me-1"></i> Accounting Pay
                                    </a>
                                <?php else : ?>
                                    <a href="<?= base_url('reports/payment/'); ?>" class="slide-item <?= activate_menu('reports/payment'); ?>"> Accounting Pay</a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </li>
                    <li class="slide">
                        <a class="side-menu__item <?= activate_menu('chat'); ?>" data-bs-toggle="slide" href="<?= base_url('chat/'); ?>"><i class="side-menu__icon fa fa-comments"></i><span class="side-menu__label">Chat</span></a>
                    </li>
                    <?php } else {
                    $sidebar = $sidebar;
                    if (!empty($sidebar)) {
                        foreach ($sidebar as $menu) {
                            if (empty($menu['submenu'])) {
                    ?>
                                <li class="slide">
                                    <a class="side-menu__item <?= activate_menu($menu['active']); ?>" data-bs-toggle="slide" href="<?= base_url($menu['link']); ?>">
                                        <?php
                                        if (strpos($menu['icon'], 'assets') !== false) {
                                            echo '<img src="' . file_url($menu['icon']) . '" class="side-menu__icon" alt="">';
                                        } else {
                                            echo '<i class="side-menu__icon ' . $menu['icon'] . '"></i>';
                                        }
                                        ?>
                                        <span class="side-menu__label"><?= $menu['title']; ?></span>
                                    </a>
                                </li>
                            <?php
                            } else {
                            ?>
                                <li class="slide <?= activate_dropdown($menu['active'], $menu['not']); ?>">
                                    <a class="side-menu__item <?= activate_dropdown($menu['active'], $menu['not']); ?>" data-bs-toggle="slide" href="javascript:void(0)">
                                        <?php
                                        if (strpos($menu['icon'], 'assets') !== false) {
                                            echo '<img src="' . file_url($menu['icon']) . '" class="side-menu__icon" alt="">';
                                        } else {
                                            echo '<i class="side-menu__icon ' . $menu['icon'] . '"></i>';
                                        }
                                        ?>
                                        <span class="side-menu__label"><?= $menu['title']; ?></span><i class="angle fe fe-chevron-right"></i></a>
                                    <ul class="slide-menu">
                                        <li class="side-menu-label1"><a href="javascript:void(0)"><?= $menu['title']; ?></a></li>
                                        <?php
                                        $submenus = $menu['submenu'];
                                        foreach ($submenus as $submenu) {
                                        ?>
                                            <li>
                                                <a href="<?= base_url($submenu['link']); ?>" class="slide-item <?= activate_menu($submenu['active']); ?>"> <?= $submenu['title']; ?></a>
                                            </li>
                                        <?php
                                        }
                                        ?>
                                    </ul>
                                </li>
                        <?php
                            }
                        }
                    } else {
                        ?>
                        <li class="slide <?= activate_dropdown('customers') ?>">
                            <a class="side-menu__item <?= activate_dropdown('customers') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-users"></i><span class="side-menu__label">Customers</span><i class="angle fe fe-chevron-right"></i></a>
                            <ul class="slide-menu">
                                <li class="side-menu-label1"><a href="javascript:void(0)">Customers</a></li>
                                <li><a href="<?= base_url('customers/'); ?>" class="slide-item <?= activate_menu('customers'); ?>"> Customers</a></li>
                                <li><a href="<?= base_url('customers/addcustomer/'); ?>" class="slide-item <?= activate_menu('customers/addcustomer'); ?>"> Add Customer</a></li>
                                <li><a href="<?= base_url('customers/customerpurchases/'); ?>" class="slide-item <?= activate_menu('customers/customerpurchases'); ?>"> Customer Purchases</a></li>
                                <li><a href="<?= base_url('customers/customerfirmdetails/'); ?>" class="slide-item <?= activate_menu('customers/customerfirmdetails'); ?>"> Customer Firm Details</a></li>
                            </ul>
                        </li>
                        <li class="slide <?= activate_dropdown('orders') ?>">
                            <a class="side-menu__item <?= activate_dropdown('orders') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-file-text"></i><span class="side-menu__label">Orders</span><i class="angle fe fe-chevron-right"></i></a>
                            <ul class="slide-menu">
                                <li class="side-menu-label1"><a href="javascript:void(0)">Orders</a></li>
                                <li><a href="<?= base_url('orders/'); ?>" class="slide-item <?= activate_menu('orders'); ?>"> Orders</a></li>
                                <li><a href="<?= base_url('orders/myassessments/'); ?>" class="slide-item <?= activate_menu('orders/myassessments'); ?>"> My Assessments</a></li>
                                <?php /*?><li><a href="<?= base_url('orders/yearlyturnover/'); ?>" class="slide-item <?= activate_menu('orders/yearlyturnover'); ?>"> Add Turnover</a></li><?php */ ?>
                                <li><a href="<?= base_url('orders/monthlyturnover/'); ?>" class="slide-item <?= activate_menu('orders/monthlyturnover'); ?>"> Add Turnover</a></li>
                                <li><a href="<?= base_url('orders/turnoversheet/'); ?>" class="slide-item <?= activate_menu('orders/turnoversheet'); ?>"> Turnover Sheet</a></li>
                            </ul>
                        </li>
                        <li class="slide <?= activate_dropdown('wallet') ?>">
                            <a class="side-menu__item <?= activate_dropdown('wallet') ?>" data-bs-toggle="slide" href="javascript:void(0)"><i class="side-menu__icon  fa fa-money"></i><span class="side-menu__label">Wallet</span><i class="angle fe fe-chevron-right"></i></a>
                            <ul class="slide-menu">
                                <li class="side-menu-label1"><a href="javascript:void(0)">Wallet</a></li>
                                <li><a href="<?= base_url('wallet/'); ?>" class="slide-item <?= activate_menu('wallet'); ?>"> My Earnings</a></li>
                                <li><a href="<?= base_url('wallet/mypayments/'); ?>" class="slide-item <?= activate_menu('wallet/mypayments'); ?>"> My Payments</a></li>
                            </ul>
                        </li>
                        <li class="slide">
                            <a class="side-menu__item <?= activate_menu('creditlimit'); ?>" data-bs-toggle="slide" href="<?= base_url('creditlimit'); ?>"><i class="side-menu__icon fa fa-credit-card"></i><span class="side-menu__label">Credit Limit</span></a>
                        </li>
                        <li class="slide">
                            <a class="side-menu__item <?= activate_menu('chat'); ?>" data-bs-toggle="slide" href="<?= '#';
                                                                                                                    base_url('chat/'); ?>"><i class="side-menu__icon fa fa-comments"></i><span class="side-menu__label">Chat</span></a>
                        </li>
                <?php
                    }
                }
                ?>
            </ul>
            <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z" />
                </svg></div>
        </div>
    </div>
    <!--/APP-SIDEBAR-->
</div>


<!--app-content open-->
<div class="main-content app-content mt-0">
    <div class="side-app">

        <!-- CONTAINER -->
        <div class="main-container container-fluid">
            <?php if (empty($noheader)) { ?>
                <?php $this->load->view("includes/page-header"); ?>
            <?php } ?>
            <!-- ROW-1 OPEN -->
            <div class="row row-cards">
                <div class="col-md-12">
                    <?php if (empty($nocard)) { ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title"><?= $title; ?></div>
                            </div>
                        <?php } ?>