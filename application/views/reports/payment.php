<style>
    .cell-right {
        text-align: right;
    }

    .payment-summary {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>
<div class="card-body">
    <?= form_open_multipart('reports/processpayment/'); ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <h4>Accountancy Payment</h4>
            <p class="text-muted">Review and pay your outstanding accountancy fees</p>
        </div>
    </div>

    <?php 
    $records_to_display = !empty($payment_records) ? $payment_records : (!empty($unpaid_months) ? $unpaid_months : array());
    if (!empty($records_to_display)) { 
    ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <?php
                        $row_gst_rate = isset($gst_rate) ? floatval($gst_rate) : 18.0;
                        $total_gst_col = 0;
                        $previous_unpaid_balance = 0;
                        ?>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="cell-right">Outstanding</th>
                                <th class="cell-right">Accounts Fee</th>
                                <th class="cell-right">Other Fee</th>
                                <th class="cell-right">Late Fee</th>
                                <th class="cell-right">Total</th>
                                <th class="cell-right">GST</th>
                                <th class="cell-right">Paid</th>
                                <th class="cell-right">Balance</th>
                                <th>Due Date</th>
                                <th>Delay in Days</th>
                                <th class="text-center">Auto Debit Status</th>
                                <th class="text-center">Action / Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records_to_display as $month) {
                                $is_paid = ($month['balance'] <= 0 && $month['paid'] > 0);
                                $row_fee = (float)($month['acc_fees'] ?? 0);
                                $row_gst = ($row_gst_rate > 0 && !empty($gst_enabled) && $row_fee > 0) ? round(($row_fee * $row_gst_rate) / 100, 2) : 0;
                                if (!$is_paid && $month['balance'] > 0) {
                                    $total_gst_col += $row_gst;
                                }
                                // For display: use actual values for unpaid, display_ values for paid months
                                $show_penalty = $is_paid ? ($month['display_penalty'] ?? $month['penalty']) : $month['penalty'];
                                $show_days    = $is_paid ? ($month['display_days']   ?? $month['days'])    : ($month['days'] ?? $month['due_days'] ?? 0);
                                // Other fee for this month from purchases
                                $month_ym = !empty($month['date']) ? date('Y-m', strtotime($month['date'])) : '';
                                $month_other_fee = !empty($other_fee_by_month) && !empty($month_ym) && isset($other_fee_by_month[$month_ym]) ? $other_fee_by_month[$month_ym] : 0;
                            ?>
                                <tr>
                                    <td><?= $month['month']; ?></td>
                                    <td class="cell-right"><?= $this->amount->toDecimal($month['outstanding'], false); ?></td>
                                    <td class="cell-right"><?= $this->amount->toDecimal($month['acc_fees'], false); ?></td>
                                    <td class="cell-right">
                                        <?php if ($month_other_fee > 0) { ?>
                                            <span class="text-primary"><?= $this->amount->toDecimal($month_other_fee, false); ?></span>
                                        <?php } else { ?>
                                            --
                                        <?php } ?>
                                    </td>
                                    <td class="cell-right">
                                        <?php if ($show_penalty > 0) { ?>
                                            <span class="text-danger"><?= $this->amount->toDecimal($show_penalty, false); ?></span>
                                        <?php } else { ?>
                                            <?= $this->amount->toDecimal($show_penalty, false); ?>
                                        <?php } ?>
                                    </td>
                                    <td class="cell-right"><?= $this->amount->toDecimal(isset($month['gross_total']) ? $month['gross_total'] : $month['total'], false); ?></td>
                                    <td class="cell-right">
                                        <?php if ($row_gst > 0) { ?>
                                            <span class="<?= $is_paid ? 'text-success' : 'text-info'; ?>"><?= $this->amount->toDecimal($row_gst, false); ?></span>
                                        <?php } else { ?>
                                            --
                                        <?php } ?>
                                    </td>
                                    <td class="cell-right">
                                        <?php if ($month['paid'] > 0) { ?>
                                            <span class="badge bg-success"><?= $this->amount->toDecimal($month['paid'], false); ?></span>
                                        <?php } else { ?>
                                            <?= $this->amount->toDecimal($month['paid'], false); ?>
                                        <?php } ?>
                                    </td>
                                    <td class="cell-right"><strong><?= $this->amount->toDecimal($month['balance'], false); ?></strong></td>
                                    <td><?= $month['due_date']; ?></td>
                                    <td>
                                        <?php if ($show_days > 0) { ?>
                                            <span class="<?= $is_paid ? 'text-warning' : 'text-danger'; ?> fw-bold"><?= $show_days; ?> days</span>
                                        <?php } else { ?>
                                            0
                                        <?php } ?>
                                    </td>
                                    <td class="text-center"><?= $month['auto_debit_status_label'] ?? $month['auto_debit_status'] ?? '--'; ?></td>
                                    <td class="text-center"><?= $month['action_date'] ?? '--'; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <th>Total</th>
                                <th class="cell-right">--</th>
                                <th class="cell-right"><?= isset($total_fees) ? $this->amount->toDecimal($total_fees, false) : '--'; ?></th>
                                <th class="cell-right"><?= !empty($total_other_fee) ? $this->amount->toDecimal($total_other_fee, false) : '--'; ?></th>
                                <th class="cell-right"><?= isset($total_penalty) ? $this->amount->toDecimal($total_penalty, false) : '--'; ?></th>
                                <th class="cell-right"><?= isset($total_sum) ? $this->amount->toDecimal($total_sum, false) : '--'; ?></th>
                                <th class="cell-right"><?= !empty($gst_amount) ? $this->amount->toDecimal($gst_amount, false) : ($total_gst_col > 0 ? $this->amount->toDecimal($total_gst_col, false) : '--'); ?></th>
                                <th class="cell-right">
                                    <?php if (isset($total_paid) && $total_paid > 0) { ?>
                                        <span class="badge bg-success"><?= $this->amount->toDecimal($total_paid, false); ?></span>
                                    <?php } elseif (isset($total_paid)) { ?>
                                        <?= $this->amount->toDecimal($total_paid, false); ?>
                                    <?php } else { ?>
                                        --
                                    <?php } ?>
                                </th>
                                <th class="cell-right"><?= isset($last_month_balance) ? $this->amount->toDecimal($last_month_balance, false) : '--'; ?></th>
                                <th>--</th>
                                <th><?= $total_days ?? 0; ?></th>
                                <th></th>
                                <th></th>
                            </tr>
                            <?php if (!empty($last_month_balance) && $last_month_balance > 0 && !empty($unpaid_months)) { ?>
                                <?php if (!empty($gst_enabled) && $gst_enabled && $gst_amount > 0) { ?>
                                    <tr>
                                        <th colspan="7" class="cell-right">Subtotal:</th>
                                        <th class="cell-right"><?= $this->amount->toDecimal($last_month_balance, false); ?></th>
                                        <th colspan="4"></th>
                                    </tr>
                                    <?php if (!empty($states_match) && $states_match && ($sgst_amount > 0 || $cgst_amount > 0)) { ?>
                                        <tr>
                                            <th colspan="7" class="cell-right">SGST (9%):</th>
                                            <th class="cell-right"><?= $this->amount->toDecimal($sgst_amount, false); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                        <tr>
                                            <th colspan="7" class="cell-right">CGST (9%):</th>
                                            <th class="cell-right"><?= $this->amount->toDecimal($cgst_amount, false); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                    <?php } elseif (!empty($igst_amount) && $igst_amount > 0) { ?>
                                        <tr>
                                            <th colspan="7" class="cell-right">IGST (18%):</th>
                                            <th class="cell-right"><?= $this->amount->toDecimal($igst_amount, false); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                    <?php } else { ?>
                                        <tr>
                                            <th colspan="7" class="cell-right">GST (<?= $gst_rate; ?>%):</th>
                                            <th class="cell-right"><?= $this->amount->toDecimal($gst_amount, false); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                                <tr>
                                    <th colspan="7" class="cell-right">Total Amount to Pay:</th>
                                    <th class="cell-right"><?= $this->amount->toDecimal(!empty($total_with_gst) ? $total_with_gst : $last_month_balance, false); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                            <?php } ?>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!empty($last_month_balance) && $last_month_balance > 0 && !empty($unpaid_months)) { ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="payment-summary">
                        <h5>Payment Summary</h5>
                        <div class="row">
                            <div class="col-md-12">
                                <?php if (!empty($last_month_data)) { ?>
                                    <p><strong>Payment Month:</strong> <?= !empty($payment_month_range) ? $payment_month_range : $last_month_data['month']; ?></p>
                                    <p><strong>Due Date:</strong> <?= $last_month_data['due_date']; ?></p>
                                    <?php if (!empty($gst_enabled) && $gst_enabled && $gst_amount > 0) { ?>
                                        <p><strong>Subtotal:</strong> ₹<?= $this->amount->toDecimal($last_month_balance, false); ?></p>
                                        <?php if (!empty($states_match) && $states_match && ($sgst_amount > 0 || $cgst_amount > 0)) { ?>
                                            <p><strong>SGST (9%):</strong> ₹<?= $this->amount->toDecimal($sgst_amount, false); ?></p>
                                            <p><strong>CGST (9%):</strong> ₹<?= $this->amount->toDecimal($cgst_amount, false); ?></p>
                                        <?php } elseif (!empty($igst_amount) && $igst_amount > 0) { ?>
                                            <p><strong>IGST (18%):</strong> ₹<?= $this->amount->toDecimal($igst_amount, false); ?></p>
                                        <?php } else { ?>
                                            <p><strong>GST (<?= $gst_rate; ?>%):</strong> ₹<?= $this->amount->toDecimal($gst_amount, false); ?></p>
                                        <?php } ?>
                                    <?php } ?>
                                    <p><strong>Amount to Pay:</strong> <span class="text-success" style="font-size: 1.2em;">₹<?= $this->amount->toDecimal(!empty($total_with_gst) ? $total_with_gst : $last_month_balance, false); ?></span></p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <?= create_form_input('hidden', 'year', '', true, $year); ?>
                    <?= create_form_input('hidden', 'firm_id', '', true, $firm_id); ?>
                    <?= create_form_input('hidden', 'user_id', '', true, $user_id); ?>
                    <?= create_form_input('hidden', 'amount', '', true, !empty($total_with_gst) ? $total_with_gst : $last_month_balance); ?>
                    <?= create_form_input('hidden', 'makepayment', '', true, '1'); ?>
                    <?php if (!empty($last_month_data)) { ?>
                        <?= create_form_input('hidden', 'last_month_id', '', true, $last_month_data['id']); ?>
                        <?= create_form_input('hidden', 'last_month_date', '', true, $last_month_data['date']); ?>
                    <?php } ?>
                    <button type="button" id="payButton" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#paymentConfirmModal">
                        <i class="fa fa-money"></i> Pay ₹<?= $this->amount->toDecimal(!empty($total_with_gst) ? $total_with_gst : $last_month_balance, false); ?>
                    </button>
                    <a href="<?= base_url('reports/'); ?>" class="btn btn-secondary btn-lg">Cancel</a>
                </div>
            </div>

            <!-- Payment Confirmation Modal -->
            <div class="modal fade" id="paymentConfirmModal" tabindex="-1" aria-labelledby="paymentConfirmModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="paymentConfirmModalLabel">
                                <i class="fa fa-money me-2"></i>Confirm Payment
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info mb-3" role="alert">
                                <h6 class="alert-heading"><i class="fa fa-info-circle me-2"></i>Payment Details</h6>
                                <hr>
                                <div class="row mb-2">
                                    <div class="col-5"><strong>Payment Period:</strong></div>
                                    <div class="col-7"><?= !empty($payment_month_range) ? $payment_month_range : (!empty($last_month_data) ? $last_month_data['month'] : 'N/A'); ?></div>
                                </div>
                                <?php if (!empty($last_month_data)) { ?>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Due Date:</strong></div>
                                        <div class="col-7"><?= $last_month_data['due_date']; ?></div>
                                    </div>
                                <?php } ?>
                                <?php if (!empty($gst_enabled) && $gst_enabled && $gst_amount > 0) { ?>
                                    <div class="row mb-2">
                                        <div class="col-5"><strong>Subtotal:</strong></div>
                                        <div class="col-7">₹<?= $this->amount->toDecimal($last_month_balance, false); ?></div>
                                    </div>
                                    <?php if (!empty($states_match) && $states_match && ($sgst_amount > 0 || $cgst_amount > 0)) { ?>
                                        <div class="row mb-2">
                                            <div class="col-5"><strong>SGST (9%):</strong></div>
                                            <div class="col-7">₹<?= $this->amount->toDecimal($sgst_amount, false); ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-5"><strong>CGST (9%):</strong></div>
                                            <div class="col-7">₹<?= $this->amount->toDecimal($cgst_amount, false); ?></div>
                                        </div>
                                    <?php } elseif (!empty($igst_amount) && $igst_amount > 0) { ?>
                                        <div class="row mb-2">
                                            <div class="col-5"><strong>IGST (18%):</strong></div>
                                            <div class="col-7">₹<?= $this->amount->toDecimal($igst_amount, false); ?></div>
                                        </div>
                                    <?php } else { ?>
                                        <div class="row mb-2">
                                            <div class="col-5"><strong>GST (<?= $gst_rate; ?>%):</strong></div>
                                            <div class="col-7">₹<?= $this->amount->toDecimal($gst_amount, false); ?></div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                                <div class="row mb-2">
                                    <div class="col-5"><strong>Total Amount:</strong></div>
                                    <div class="col-7"><span class="text-success fw-bold" style="font-size: 1.1em;">₹<?= $this->amount->toDecimal(!empty($total_with_gst) ? $total_with_gst : $last_month_balance, false); ?></span></div>
                                </div>
                                <div class="row">
                                    <div class="col-5"><strong>Months to Pay:</strong></div>
                                    <div class="col-7"><?= count($unpaid_months); ?> month(s)</div>
                                </div>
                            </div>
                            <div class="alert alert-warning mb-0" role="alert">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <strong>Please confirm:</strong> This amount will be deducted from your wallet balance. Are you sure you want to proceed?
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fa fa-times me-1"></i>Cancel
                            </button>
                            <button type="button" id="confirmPayButton" class="btn btn-success">
                                <i class="fa fa-check me-1"></i>Confirm & Pay
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> All payments are up to date! No outstanding balance.
                    </div>
                    <a href="<?= base_url('reports/'); ?>" class="btn btn-primary">Back to Reports</a>
                </div>
            </div>
        <?php } ?>
    <?php } else { ?>
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No payment records found.
                </div>
                <a href="<?= base_url('reports/'); ?>" class="btn btn-primary">Back to Reports</a>
            </div>
        </div>
    <?php } ?>

    <?= form_close(); ?>
</div>

<script>
    $(document).ready(function() {
        // Handle confirm payment button click
        $('#confirmPayButton').on('click', function() {
            var $btn = $(this);
            // Disable button to prevent double submission
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');

            // Close modal
            var modalElement = $('#paymentConfirmModal');
            modalElement.modal('hide');

            // Submit the form after a short delay to allow modal to close
            setTimeout(function() {
                $('form').submit();
            }, 300);
        });

        // Reset button state if modal is closed without confirming
        $('#paymentConfirmModal').on('hidden.bs.modal', function() {
            $('#confirmPayButton').prop('disabled', false).html('<i class="fa fa-check me-1"></i>Confirm & Pay');
        });
    });
</script>
