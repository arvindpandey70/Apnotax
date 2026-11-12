<table class="table table-bordered" id="acc_table">
    <thead>
        <tr>
            <th>Month</th>
            <th class="cell-right">Outstanding</th>
            <th class="cell-right">GTO (in Lacs)</th>
            <th class="cell-right">Accounts Fee</th>
            <th class="cell-right">Other Fee</th>
            <th class="cell-right">Subtotal</th>
            <th class="cell-right">GST</th>
            <th class="cell-right">Total</th>
            <th class="cell-right">Paid</th>
            <th class="cell-right">Balance</th>
            <th>Due date</th>
            <th>Delay in Days</th>
            <th class="text-center">Auto Debit Status</th>
            <th class="text-center">Review / Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $date = date('Y-m-d');
        $result = array();
        $prev = array();
        if (!empty($accountancy)) {
            $total_all_gto = 0;
            $total_all_fees = 0;
            $total_all_other = 0;
            $total_all_subtotal = 0;
            $total_all_gst = 0;
            $total_all_total = 0;
            $total_all_paid = 0;
            $total_all_balance = 0;
            $total_days = 0;
            $outstanding = 0;
            $balance = 0;

            $multiplier = !empty($this->multiplier) ? (float)$this->multiplier : 1;
            $total_turnover *= $multiplier;
            $pkg_name = $package['name'] ?? (!empty($cpackage) && $cpackage['package_id'] == 1 ? 'Accountancy Prime' : 'Accountancy Premium');
            if (isset($package_fee) && is_numeric($package_fee)) {
                $fees = (float)$package_fee;
            } else if (function_exists('calculate_accountancy_fee')) {
                $fee_info = calculate_accountancy_fee($pkg_name, $total_turnover);
                $fees = (float)$fee_info['fees'];
                if (empty($package)) {
                    $package = $fee_info['package'];
                }
            } else {
                $turnover_div = !empty($package['turnover']) ? (float)$package['turnover'] : 1;
                $rate_mult = !empty($package['rate']) ? (float)$package['rate'] : 0;
                $fees = ($total_turnover / $turnover_div) * $rate_mult;
            }

            // Customer GST setting (resolve before fee calculation for legacy row fallback)
            if (!isset($gst_enabled)) {
                $CI = &get_instance();
                $cust_u_id = !empty($accountancy[0]['user_id']) ? $accountancy[0]['user_id'] : ($user_id ?? 0);
                $cust_record = $CI->customer->getcustomers(['t1.user_id' => $cust_u_id], 'single');
                $gst_enabled = !empty($cust_record) && !empty($cust_record['gst_enabled']) && $cust_record['gst_enabled'] == 1;
                $row_gst_rate = $gst_enabled ? 18.0 : 0.0;
            } else {
                $row_gst_rate = !empty($gst_enabled) ? (isset($gst_rate) ? (float)$gst_rate : 18.0) : 0.0;
            }

            $turnover_calc = calculate_turnover_monthly_fees($accountancy, $pkg_name, !empty($gst_enabled));
            $monthly_fees = $turnover_calc['monthly_fees'];
            if (empty($package) && !empty($turnover_calc['package'])) {
                $package = $turnover_calc['package'];
            }

            foreach ($accountancy as $idx => $single) {
                $days = 0;
                $single_turnover = !empty($single['turnover']) ? (float)$single['turnover'] : 0;
                $paid = !empty($single['paid']) ? (float)$single['paid'] : 0;
                $outstanding = $balance;

                $acc_fees = isset($monthly_fees[$idx]) ? (float)$monthly_fees[$idx] : 0;

                $is_turnover_pkg = (isset($pkg_name) && ($pkg_name === 'Accountancy Prime' || $pkg_name === 'Accountancy Premium'));
                $other_fee = (!$is_turnover_pkg && !empty($single['other_fee'])) ? (float)$single['other_fee'] : 0;
                $row_fee = $acc_fees + $other_fee; // Subtotal
                $row_gst = ($row_gst_rate > 0 && !empty($gst_enabled) && $row_fee > 0) ? round(($row_fee * $row_gst_rate) / 100, 2) : 0;
                $row_base = $outstanding + $row_fee;
                $row_total = round($row_base + $row_gst, 2);

                // Sanitize due_date to avoid sentinel 0000-00-00 dates
                $raw_due = $single['due_date'] ?? '';
                if (!empty($raw_due) && $raw_due != '0000-00-00' && strtotime($raw_due) !== false) {
                    $row_due_date = $raw_due;
                } else if (!empty($single['date'])) {
                    $row_due_date = date('Y-m-06', strtotime('+1 month', strtotime($single['date'])));
                } else {
                    $row_due_date = '';
                }

                $is_confirmed = ($single['auto_debit_status'] ?? '') === 'Confirmed';
                $is_paid = $is_confirmed || ($row_total > 0 && $paid >= $row_total) || ($row_total == 0 && $paid == 0);

                if ($is_confirmed || ($row_total > 0 && $paid >= $row_total)) {
                    $display_paid = ($paid > 0) ? $paid : $row_total;
                    $balance = 0;
                } else {
                    $display_paid = ($paid < $row_total) ? $paid : 0;
                    $balance = max(0, round($row_total - $display_paid, 2));
                }

                if (!empty($row_due_date) && $row_due_date < $date && $balance > 0) {
                    $date1 = new DateTime($row_due_date);
                    $date2 = new DateTime($date);
                    $interval = $date1->diff($date2);
                    $days = $interval->days;
                    $total_days += $days;
                }

                $total_all_gto += $single_turnover;
                $total_all_fees += $acc_fees;
                $total_all_other += $other_fee;
                $total_all_subtotal += $row_fee;
                $total_all_gst += $row_gst;
                $total_all_total += $row_total;
                $total_all_paid += $display_paid;
                $total_all_balance += $balance;

                $month = $single['date'] != '' ? date("Ym", strtotime($single['date'])) : '';
                if ($month != '') {
                    if ($paid > 0 && !empty($prev)) {
                        foreach ($prev as $m) {
                            $result[$m]['paid'] = 1;
                        }
                        $prev = array();
                    }
                    $result[$month] = array(
                        'turnover' => $single['turnover'],
                        'due_date' => $row_due_date,
                        'paid' => $display_paid
                    );
                    $prev[] = $month;
                }

                // --- DYNAMIC RENEWAL LOGIC ---
                $isPastMonth = false;
                $renewalMethod = 'AUTO_WALLET';
                $auto_debit_status_label = $single['auto_debit_status'] ?? 'Pending';
                
                if ($single['date'] != '') {
                    $todayDate = new DateTime(date('Y-m-d'));
                    $todayDate->setTime(0, 0, 0);
                    $rowDate = new DateTime($single['date']);
                    $rowDate->setTime(0, 0, 0);
                    
                    $isPastMonth = (date('Y-m', $rowDate->getTimestamp()) < date('Y-m', $todayDate->getTimestamp()));
                    $renewalMethod = $isPastMonth ? 'ADMIN' : 'AUTO_WALLET';
                    
                    if ($auto_debit_status_label !== 'Confirmed') {
                        if ($row_total <= 0 && $balance <= 0) {
                            $auto_debit_status_label = ($single_turnover > 0) ? 'No Extra Fee' : 'Renewed';
                        } elseif ($isPastMonth) {
                            $auto_debit_status_label = ($balance > 0) ? 'Admin Renew' : 'Renewed';
                        } else {
                            if (!empty($row_due_date)) {
                                $dueDateObj = new DateTime($row_due_date);
                                $dueDateObj->setTime(0, 0, 0);
                                $interval = $todayDate->diff($dueDateObj);
                                $daysLeft = (int)$interval->format('%R%a');
                                if ($daysLeft > 0) {
                                    $auto_debit_status_label = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left auto debit';
                                } else if ($daysLeft === 0) {
                                    $auto_debit_status_label = 'Auto debit today';
                                } else {
                                    $auto_debit_status_label = 'Auto debit processing';
                                }
                            } else {
                                $auto_debit_status_label = 'Pending';
                            }
                        }
                    }
                }
                // -----------------------------
        ?>
                <tr>
                    <td>
                        <?= $single['date'] != '' ? date('F-y', strtotime($single['date'])) : '--'; ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($outstanding, false); ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($single['turnover'], false); ?>
                    </td>
                    <td class="cell-right">
                        <?= (!empty($acc_fees) && (float)$acc_fees > 0) ? $this->amount->toDecimal($acc_fees, false) : '--'; ?>
                    </td>
                    <td class="cell-right">
                        <?php if ($other_fee > 0) { ?>
                            <span class="text-primary"><?= $this->amount->toDecimal($other_fee, false); ?></span>
                        <?php } else { ?>
                            --
                        <?php } ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($row_fee, false); ?>
                    </td>
                    <td class="cell-right">
                        <?php if ($row_gst > 0) { ?>
                            <span class="<?= $is_paid ? 'text-success' : 'text-info'; ?>"><?= $this->amount->toDecimal($row_gst, false); ?></span>
                        <?php } else { ?>
                            --
                        <?php } ?>
                    </td>
                    <td class="cell-right font-weight-bold">
                        <?= $this->amount->toDecimal($row_total, false); ?>
                    </td>
                    <td class="cell-right">
                        <?php if ($display_paid > 0) { ?>
                            <span class="badge bg-success"><?= $this->amount->toDecimal($display_paid, false); ?></span>
                        <?php } else { ?>
                            <?= $this->amount->toDecimal($display_paid, false); ?>
                        <?php } ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($balance, false); ?>
                    </td>
                    <td>
                        <?= !empty($row_due_date) ? date('d-m-Y F', strtotime($row_due_date)) : '--'; ?>
                    </td>
                    <td><?= $days; ?></td>
                    <td class="text-center">
                        <?php if ($auto_debit_status_label === 'Confirmed' || $auto_debit_status_label === 'No Extra Fee') { ?>
                            <span class="badge bg-success"><?= $auto_debit_status_label ?></span>
                        <?php } elseif ($auto_debit_status_label === 'Renewed') { ?>
                            <span class="badge bg-secondary">Renewed</span>
                        <?php } else { ?>
                            <span class="badge bg-warning text-dark"><?= $auto_debit_status_label ?></span>
                        <?php } ?>
                    </td>
                    <?php if ($renewalMethod === 'ADMIN') { ?>
                        <?php if ($balance > 0) { ?>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary renew-btn" 
                                    data-id="<?= $single['id']; ?>" 
                                    data-amount="<?= $balance; ?>" 
                                    data-userid="<?= $single['user_id']; ?>" 
                                    data-firmid="<?= $single['firm_id']; ?>" 
                                    data-date="<?= $single['date']; ?>" 
                                    data-accfee="<?= $acc_fees; ?>" 
                                    data-latefee="0" 
                                    title="Renew this month">
                                    <i class="fa fa-refresh"></i> Renew
                                </button>
                            </td>
                        <?php } else {
                        ?>
                            <td class="done text-center font-weight-bold text-success">
                                <?= !empty($single['payment_date']) ? date('d-m-Y', strtotime($single['payment_date'])) : '<i class="fa fa-check-circle" style="font-size: 1.5em;"></i>' ?>
                            </td>
                        <?php
                        }
                        ?>
                    <?php } else { ?>
                        <td class="text-center">
                            <span class="text-muted">Auto Debit</span>
                        </td>
                    <?php } ?>
                </tr>
        <?php
            }
        }
        ?>
    </tbody>
    <?php if (!empty($accountancy)) { ?>
        <tfoot>
            <tr class="fw-bold">
                <th>Total</th>
                <th class="cell-right">--</th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_turnover / (!empty($this->multiplier) ? $this->multiplier : 1), false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_all_fees, false); ?>
                </th>
                <th class="cell-right">--</th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_all_subtotal, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_all_gst, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_all_total, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_all_paid, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($balance, false); ?>
                </th>
                <th></th>
                <th><?= $total_days; ?></th>
                <th></th>
                <th></th>
            </tr>
        </tfoot>
    <?php } ?>
</table>
<div id="acc_json" class="d-none"><?= json_encode($result); ?></div>
