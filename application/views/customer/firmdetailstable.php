<?php
$package_name = "None";
if (!empty($cpackage)) {
    if (strtolower($cpackage['package_type']) == 'turnover') {
        $package_name = "Turnover (" . ($cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium') . ")";
    } else {
        $package_name = $cpackage['package_type'];
    }
}
?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title text-white">Package Type</h5>
                <h3><?= $package_name ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title text-white">Available Credit Limit</h5>
                <h3>₹ <?= number_format($credit_limit, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title text-white">Wallet Balance</h5>
                <h3>₹ <?= number_format($wallet_balance, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php
if (!empty($pending_monthly)) {
    echo '<div class="row mb-4"><div class="col-md-12"><h4 class="mb-3 text-primary"><i class="fe fe-calendar me-1"></i> Monthly Package Renewals</h4>';
    echo '<div class="table-responsive"><table class="table table-bordered table-striped">';
    echo '<thead class="bg-light"><tr><th>Month</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    foreach ($pending_monthly as $pm) {
        $month_name = date('F Y', strtotime($pm['purchase_date']));
        $amt = number_format($pm['bill_amount'], 2);
        $is_paid = ($pm['payment_status'] == 1);
        echo '<tr>';
        echo '<td>' . $month_name . '</td>';
        echo '<td>₹ ' . $amt . '</td>';
        if ($is_paid) {
            echo '<td><span class="badge bg-success"><i class="fa fa-check-circle me-1"></i> Renewed</span></td>';
            echo '<td class="text-center font-weight-bold text-success"><i class="fa fa-check-circle text-success me-1" style="font-size: 1.2em;"></i> Renewed</td>';
        } else {
            echo '<td><span class="badge bg-warning text-dark">Pending</span></td>';
            echo '<td><button class="btn btn-sm btn-danger renew-monthly-btn" data-id="'.$pm['id'].'" data-amount="'.$pm['bill_amount'].'" data-userid="'.$pm['user_id'].'" data-firmid="'.$pm['firm_id'].'"><i class="fe fe-credit-card me-1"></i> Pay</button></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></div></div>';
}

if (!empty($cpackage)) {
    if (isset($cpackage['package_type']) && $cpackage['package_type'] == 'Monthly') {
        // Do not show the turnover table for Monthly packages
    } else {
        $this->load->view('orders/acc_table', $data ?? []);
    }
} else {
    echo '<h3 class="text-danger text-center mt-4">No Active Package Found For This Firm!</h3>';
}
?>
