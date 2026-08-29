<table class="table table-bordered" id="acc_table">
    <thead>
        <tr>
            <th>Month</th>
            <th>Outstanding</th>
            <th>GTO (in Lacs)</th>
            <th>Accounts Fee</th>
            <th>Late Fee</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Due date</th>
            <th>Delay in Days</th>
            <th>Auto Debit Status</th>
            <th>Review / Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        //print_pre($accountancy);
        $date = date('Y-m-d');
        $percent = 2 / 100;
        $result = array();
        $prev = array();
        if (!empty($accountancy)) {
            $total_fees = $total_other = $total_paid = $total_penalty = 0;
            $total_days = 0;
            $outstanding = $total = $balance = 0;
            $total_turnover *= $this->multiplier;
            $fees = $total_turnover / $package['turnover'];
            $fees *= $package['rate'];
            $count = count($accountancy);
            $last = end($accountancy);
            if ($last['date'] == '') {
                $count--;
            }
            $acc_fees = $fees / $count;
            foreach ($accountancy as $single) {
                $days = $paid = $penalty = 0;
                $paid = !empty($single['paid']) ? $single['paid'] : 0;
                $outstanding = $balance;
                if ($single['date'] != '') {
                    $acc_fees = $fees / $count;
                } else {
                    $acc_fees = 0;
                }
                $other_fee = $single['other_fee'] ?? 0;
                $total_other += $other_fee;
                $balance = $outstanding + $acc_fees + $other_fee;

                // Sanitize due_date to avoid sentinel 0000-00-00 dates
                $raw_due = $single['due_date'] ?? '';
                if (!empty($raw_due) && $raw_due != '0000-00-00' && strtotime($raw_due) !== false) {
                    $row_due_date = $raw_due;
                } else if (!empty($single['date'])) {
                    $row_due_date = date('Y-m-06', strtotime('+1 month', strtotime($single['date'])));
                } else {
                    $row_due_date = '';
                }

                if (!empty($row_due_date) && $row_due_date < $date && $paid < $balance) {
                    $balance -= $paid;
                    $date1 = new DateTime($row_due_date);
                    $date2 = new DateTime($date);

                    // Calculate the difference
                    $interval = $date1->diff($date2);

                    // Get the difference in days
                    $days = $interval->days;
                    $penalty = ($percent * $balance);
                    if ($days < 30) {
                        $penalty /= 30;
                        $penalty *= $days;
                    }
                    $penalty = round($penalty);
                    $total_penalty += $penalty;
                    $total_days += $days;
                } else {
                    $balance -= $paid;
                }
                // Ensure balance doesn't go negative
                if ($balance < 0) {
                    $balance = 0;
                }
                $total = $balance + $penalty;
                $total_fees += $acc_fees;
                $total_paid += $paid;
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
                        'paid' => $paid
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
                            if ($isPastMonth) {
                                $auto_debit_status_label = ($total > 0) ? 'Admin Renew' : 'Renewed';
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
                        <?= $this->amount->toDecimal($acc_fees, false); ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($penalty, false); ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($total, false); ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($paid, false); ?>
                    </td>
                    <td class="cell-right">
                        <?= $this->amount->toDecimal($balance, false); ?>
                    </td>
                    <td>
                        <?= !empty($row_due_date) ? date('d-m-Y F', strtotime($row_due_date)) : '--'; ?>
                    </td>
                    <td><?= $days; ?></td>
                    <td class="text-center">
                        <?php if ($auto_debit_status_label === 'Confirmed') { ?>
                            <span class="badge bg-success">Confirmed</span>
                        <?php } else { ?>
                            <span class="badge bg-warning text-dark"><?= $auto_debit_status_label ?></span>
                        <?php } ?>
                    </td>
                    <?php if ($renewalMethod === 'ADMIN') { ?>
                        <?php if ($balance > 0 || $total > 0) { ?>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary renew-btn" 
                                    data-id="<?= $single['id']; ?>" 
                                    data-amount="<?= $total; ?>" 
                                    data-userid="<?= $single['user_id']; ?>" 
                                    data-firmid="<?= $single['firm_id']; ?>" 
                                    data-date="<?= $single['date']; ?>" 
                                    data-accfee="<?= $acc_fees; ?>" 
                                    data-latefee="<?= $penalty; ?>" 
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
            <tr>
                <th></th>
                <th></th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_turnover / (!empty($this->multiplier) ? $this->multiplier : 1), false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_fees, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_penalty, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_fees + $total_penalty - $total_paid, false); ?>
                </th>
                <th class="cell-right">
                    <?= $this->amount->toDecimal($total_paid, false); ?>
                </th>
                <th></th>
                <th></th>
                <th><?= $total_days; ?></th>
                <th></th>
                <th>
                </th>
            </tr>
        </tfoot>
    <?php } ?>
</table>
<div id="acc_json" class="d-none"><?= json_encode($result); ?></div>