<div class="row">
    <div class="col-md-6 col-lg-4 col-xl-4">
        <div class="card bg-primary img-card box-primary-shadow">
            <div class="card-body">
                <div class="d-flex">
                    <div class="text-white">
                        <h2 class="mb-0 number-font">₹ <?= number_format($available_limit, 2) ?></h2>
                        <p class="text-white mb-0">Available Credit Limit</p>
                    </div>
                    <div class="ms-auto"> <i class="fa fa-credit-card text-white fs-30 me-2 mt-2"></i> </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-12 col-lg-8 col-xl-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Credit Information</h3>
            </div>
            <div class="card-body">
                <p>Welcome to your Credit Limit dashboard. Your admin has assigned you a credit limit which allows you to purchase services seamlessly up to the available amount.</p>
                <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Total Credit Limit:</strong> 
                        <span>₹ <?= number_format($credit_limit, 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Used Credit Limit:</strong> 
                        <span class="text-danger">₹ <?= number_format($used_credit, 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Percentage:</strong> 
                        <span class="text-primary fw-bold"><?= !empty($credit_percent) ? htmlspecialchars($credit_percent) . '%' : '0%' ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Tax Amount (<?= !empty($credit_percent) ? htmlspecialchars($credit_percent) . '%' : '0%' ?> of Used Credit):</strong> 
                        <span class="text-danger">₹ <?= number_format($tax_amount, 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Total Used Credit (including Tax):</strong> 
                        <span class="text-danger fw-bold">₹ <?= number_format($total_used_with_tax, 2) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>Available Limit:</strong> 
                        <span class="text-success fw-bold">₹ <?= number_format($available_limit, 2) ?></span>
                    </li>
                </ul>
                <?php if ($available_limit > 0): ?>
                    <div class="alert alert-success mt-4">
                        <i class="fa fa-check-circle-o"></i> Your credit line is active!
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-4">
                        <i class="fa fa-warning"></i> Your credit limit is currently zero. Please contact the administrator to assign a limit.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
