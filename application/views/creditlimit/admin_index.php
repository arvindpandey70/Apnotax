<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Manage Customer Credit Limits</h3>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" id="customSearchBox" class="form-control" placeholder="Search Customer by Name, Mobile, Email...">
                            <button class="btn btn-primary" type="button"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered text-nowrap" id="creditlimit-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Email</th>
                                <th>Total Credit Limit (₹)</th>
                                <th>Percentage (%)</th>
                                <th>Used Credit (₹)</th>
                                <th>Tax (₹)</th>
                                <th>Total Used (₹)</th>
                                <th>Available Limit (₹)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($customers)): ?>
                                <?php foreach ($customers as $customer): ?>
                                    <?php 
                                        $credit_limit = (float)$customer['credit_limit'];
                                        $credit_percent = isset($customer['credit_percent']) ? $customer['credit_percent'] : '';
                                        $used_credit = (float)$customer['used_credit'];
                                        $tax_amount = isset($customer['tax_amount']) ? (float)$customer['tax_amount'] : 0.00;
                                        $total_used = isset($customer['total_used']) ? (float)$customer['total_used'] : $used_credit + $tax_amount;
                                        $available = $credit_limit - $total_used;
                                        if ($available < 0) $available = 0;
                                    ?>
                                    <tr>
                                        <td><?= $customer['name'] ?></td>
                                        <td><?= $customer['mobile'] ?></td>
                                        <td><?= $customer['email'] ?></td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control credit-limit-input" id="limit_<?= $customer['customer_id'] ?>" value="<?= $credit_limit ?>">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control credit-percent-input" id="percent_<?= $customer['customer_id'] ?>" value="<?= htmlspecialchars($credit_percent) ?>" placeholder="e.g. 2, 3, 4, 5">
                                        </td>
                                        <td>₹ <?= number_format($used_credit, 2) ?></td>
                                        <td>₹ <?= number_format($tax_amount, 2) ?></td>
                                        <td>₹ <?= number_format($total_used, 2) ?></td>
                                        <td><span class="text-success fw-bold">₹ <?= number_format($available, 2) ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary update-limit-btn" data-id="<?= $customer['customer_id'] ?>">Update</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with 10 rows per page
    var table = $('#creditlimit-table').DataTable({
        "pageLength": 10,
        "dom": "<'row'<'col-sm-12'tr>>" + // Hide default search and length menu
               "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });
    
    // Link custom search box to DataTable
    $('#customSearchBox').on('keyup', function() {
        table.search(this.value).draw();
    });
    
    $('#creditlimit-table').on('click', '.update-limit-btn', function() {
        var btn = $(this);
        var id = btn.data('id');
        var limit = $('#limit_' + id).val();
        var percent = $('#percent_' + id).val();
        
        btn.html('<i class="fa fa-spinner fa-spin"></i>').prop('disabled', true);
        
        $.ajax({
            url: "<?= base_url('creditlimit/update_limit') ?>",
            type: 'POST',
            data: {
                customer_id: id,
                credit_limit: limit,
                credit_percent: percent
            },
            dataType: 'json',
            success: function(res) {
                if(res.status) {
                    alert(res.message);
                    window.location.reload();
                } else {
                    alert(res.message);
                    btn.html('Update').prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                btn.html('Update').prop('disabled', false);
            }
        });
    });
});
</script>
