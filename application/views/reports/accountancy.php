<style>
    .cell-right {
        text-align: right;
    }
</style>
<div class="card-body">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="form-group">
                <?php
                echo create_form_input('text', '', "Package", true, $package['name'] ?? '', ['id' => 'package', 'readonly' => 'true']);
                ?>
            </div>
        </div>
    </div>
    <div class="row my-4">
        <div class="col-md-12">
            <div id="result">
                <table class="table table-bordered" id="acc_table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="cell-right">GTO</th>
                            <th class="cell-right">Accounts Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rows = !empty($report) ? count($report) : 0;
                        if (!empty($report)) {
                            foreach ($report as $key => $row) {
                                if ($key == $rows - 1) {
                                    $footer = $row;
                                    break;
                                }
                            ?>
                                <tr>
                                    <td><?= $row['month']; ?></td>
                                    <td class="cell-right"><?= is_numeric($row['gto']) ? $this->amount->toDecimal($row['gto'], false) : $row['gto']; ?></td>
                                    <td class="cell-right"><?= is_numeric($row['acc_fees']) ? $this->amount->toDecimal($row['acc_fees'], false) : $row['acc_fees']; ?></td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                    <?php if (!empty($footer)) { ?>
                        <tfoot>
                            <tr class="fw-bold">
                                <th><?= $footer['month']; ?></th>
                                <th class="cell-right"><?= is_numeric($footer['gto']) ? $this->amount->toDecimal($footer['gto'], false) : $footer['gto']; ?></th>
                                <th class="cell-right"><?= is_numeric($footer['acc_fees']) ? $this->amount->toDecimal($footer['acc_fees'], false) : $footer['acc_fees']; ?></th>
                            </tr>
                        </tfoot>
                    <?php } ?>
                </table>
            </div>

            <?php if (!empty($last_month_balance) && $last_month_balance > 0) { ?>
                <div class="alert alert-warning mt-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5><i class="fa fa-exclamation-triangle"></i> Outstanding Balance</h5>
                            <p class="mb-0">Amount to Pay (<?= !empty($payment_month_range) ? $payment_month_range : (!empty($last_month_name) ? $last_month_name : ''); ?>): <strong class="text-danger" style="font-size: 1.3em;">₹<?= $this->amount->toDecimal($last_month_balance, false); ?></strong></p>
                            <small class="text-muted">Click the button below to view detailed payment records and pay on the Payment page</small>
                        </div>
                        <div class="col-md-4 text-end text-right">
                            <a href="<?= base_url('reports/payment/'); ?>" class="btn btn-success btn-lg">
                                <i class="fa fa-money"></i> Pay Now ₹<?= $this->amount->toDecimal($last_month_balance, false); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="alert alert-success mt-4">
                    <i class="fa fa-check-circle"></i> All payments are up to date! No outstanding balance.
                </div>
            <?php } ?>
        </div>
    </div>
</div>
