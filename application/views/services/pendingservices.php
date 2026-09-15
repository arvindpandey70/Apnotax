<?php
$display_packages = !empty($all_packages) ? $all_packages : (!empty($expired_packages) ? $expired_packages : []);

// Determine true single month rate for Monthly packages (e.g. 200 or 500)
$monthly_single_rate = 0;
if (!empty($display_packages)) {
    foreach ($display_packages as $_dp) {
        if (($_dp['package_type'] ?? '') === 'Monthly') {
            $b = (float)($_dp['bill_amount'] ?? 0);
            $a = (float)($_dp['amount'] ?? 0);
            if ($b > 0 && ($monthly_single_rate == 0 || $b < $monthly_single_rate)) {
                $monthly_single_rate = $b;
            }
            if ($a > 0 && ($monthly_single_rate == 0 || $a < $monthly_single_rate)) {
                $monthly_single_rate = $a;
            }
        }
    }
}

$total_expired = 0;
$total_due = 0;

if (!empty($expired_packages)) {
    foreach ($expired_packages as $_ep) {
        $total_expired++;
        $_b = (float)($_ep['bill_amount'] ?? 0);
        if (($_ep['package_type'] ?? '') === 'Monthly' && $monthly_single_rate > 0) {
            $_b = $monthly_single_rate;
        } else {
            if ($_b <= 0 && !empty($_ep['amount'])) $_b = (float)$_ep['amount'];
            if ($_b <= 0) $_b = 500;
        }
        $total_due += $_b;
    }
}
$wallet_bal = isset($wallet_balance) ? (float)$wallet_balance : 0;
$credit_lim = isset($credit_limit) ? (float)$credit_limit : 0;
?>

<div class="card-body">
    <!-- Header Banner / Summary Chips -->
    <div class="card mb-4 border-0 shadow-sm" style="border-radius:12px; background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%); border: 1px solid #f8d7da !important;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="fw-bold text-danger mb-1">
                        <i class="fe fe-alert-triangle me-2"></i>Package Renewals & Status
                    </h4>
                    <p class="text-muted mb-0" style="font-size:.9rem;">
                        View all your monthly package statuses and renew any expired packages.
                    </p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-flex align-items-center flex-wrap gap-2 bg-white p-2 px-3 rounded-3 border shadow-sm">
                        <div class="text-start me-2">
                            <small class="text-muted d-block" style="font-size:.72rem;">Wallet Balance</small>
                            <strong class="text-success" style="font-size:1.05rem;">₹<?= number_format($wallet_bal, 2) ?></strong>
                        </div>
                        <div class="border-end pe-2" style="height:30px; border-color:#dee2e6!important;"></div>
                        <div class="text-start ms-2 me-2">
                            <small class="text-muted d-block" style="font-size:.72rem;">Credit Limit</small>
                            <strong class="text-primary" style="font-size:1.05rem;">₹<?= number_format($credit_lim, 2) ?></strong>
                        </div>
                        <a href="<?= base_url('mywallet/') ?>" class="btn btn-sm btn-outline-primary ms-1">
                            <i class="fe fe-plus-circle me-1"></i>Recharge
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($display_packages)) : ?>
        <div class="row mb-3">
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fe fe-list me-1"></i>All Package Months List (<?= count($display_packages) ?>)
                </h6>
                <?php if ($total_expired > 0) : ?>
                    <span class="badge bg-danger-transparent text-danger fw-semibold px-3 py-2" style="font-size:.85rem; border:1px solid #f8d7da;">
                        Pending Expired Due: ₹<?= number_format($total_due, 2) ?>
                    </span>
                <?php else : ?>
                    <span class="badge bg-success-transparent text-success fw-semibold px-3 py-2" style="font-size:.85rem; border:1px solid #d4edda;">
                        <i class="fe fe-check-circle me-1"></i>All Expired Dues Cleared
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <?php
            // Extract and sort monthly pending packages chronologically for cumulative calculations
            $monthly_pkgs = array_filter($expired_packages, function($p) {
                return ($p['package_type'] ?? '') === 'Monthly';
            });
            usort($monthly_pkgs, function($a, $b) {
                $da = !empty($a['purchase_date']) ? strtotime($a['purchase_date']) : 0;
                $db = !empty($b['purchase_date']) ? strtotime($b['purchase_date']) : 0;
                if ($da == $db) return ((int)$a['id']) - ((int)$b['id']);
                return $da - $db;
            });

            // Sort all display packages chronologically by purchase_date ASC
            usort($display_packages, function($a, $b) {
                $da = !empty($a['purchase_date']) ? strtotime($a['purchase_date']) : 0;
                $db = !empty($b['purchase_date']) ? strtotime($b['purchase_date']) : 0;
                if ($da == $db) return ((int)$a['id']) - ((int)$b['id']);
                return $da - $db;
            });

            foreach ($display_packages as $epkg) :
                $pkg_type   = !empty($epkg['package_type']) ? $epkg['package_type'] : 'Yearly';
                $is_expired = isset($epkg['is_expired']) ? (bool)$epkg['is_expired'] : false;
                
                $bill = (float)($epkg['bill_amount'] ?? 0);
                if ($pkg_type === 'Monthly' && $monthly_single_rate > 0) {
                    $bill = $monthly_single_rate;
                } else {
                    if ($bill <= 0 && !empty($epkg['amount'])) $bill = (float)$epkg['amount'];
                    if ($bill <= 0) $bill = 500;
                }
                
                $exp_date   = !empty($epkg['expiry_date']) ? date('d M Y', strtotime($epkg['expiry_date'])) : '—';
                
                // Formatted Month name for Monthly packages
                $purchase_date = !empty($epkg['purchase_date']) ? $epkg['purchase_date'] : null;
                $month_name    = ($pkg_type === 'Monthly' && !empty($purchase_date)) ? date('F Y', strtotime($purchase_date)) : null;

                // Formatted Financial Year (e.g. 20262027 -> 2026-2027)
                $fy_raw        = $epkg['year'] ?? '';
                $fy_formatted  = (strlen($fy_raw) == 8) ? substr($fy_raw, 0, 4) . '-' . substr($fy_raw, 4) : $fy_raw;

                // Cumulative calculations for Monthly type (only if expired)
                $cum_count       = 1;
                $cum_subtotal    = $bill;
                $cum_month_names = [];
                if ($is_expired && $pkg_type === 'Monthly' && !empty($monthly_pkgs)) {
                    $sel_pdate = !empty($epkg['purchase_date']) ? strtotime($epkg['purchase_date']) : 0;
                    $sel_id    = (int)$epkg['id'];
                    
                    $incl_pkgs = array_filter($monthly_pkgs, function($mp) use ($sel_pdate, $sel_id) {
                        $mp_date = !empty($mp['purchase_date']) ? strtotime($mp['purchase_date']) : 0;
                        $mp_id   = (int)$mp['id'];
                        return ($mp_date < $sel_pdate || ($mp_date == $sel_pdate && $mp_id <= $sel_id));
                    });

                    if (count($incl_pkgs) > 0) {
                        $cum_count       = count($incl_pkgs);
                        $cum_subtotal    = 0;
                        $cum_month_names = [];
                        foreach ($incl_pkgs as $ipkg) {
                            $ibill = (float)($ipkg['bill_amount'] ?? 0);
                            if (($ipkg['package_type'] ?? '') === 'Monthly' && $monthly_single_rate > 0) {
                                $ibill = $monthly_single_rate;
                            } else {
                                if ($ibill <= 0 && !empty($ipkg['amount'])) $ibill = (float)$ipkg['amount'];
                                if ($ibill <= 0) $ibill = 500;
                            }
                            $cum_subtotal += $ibill;
                            if (!empty($ipkg['purchase_date'])) {
                                $cum_month_names[] = date('F Y', strtotime($ipkg['purchase_date']));
                            }
                        }
                    }
                }
                
                // Add GST to single and cumulative subtotals if customer has GST
                $customer_gst = !empty($user['gst_enabled']) && $user['gst_enabled'] == 1;
                $single_total = $customer_gst ? round($bill * 1.18, 2) : $bill;
                $cum_total    = $customer_gst ? round($cum_subtotal * 1.18, 2) : $cum_subtotal;
            ?>
            <div class="col-12 mb-3">
                <div class="card border-0 shadow-sm" style="border-radius:10px; border-left: 5px solid <?= $is_expired ? '#dc3545' : '#28a745' ?> !important; background:#fff;">
                    <div class="card-body p-3 p-md-4">
                        <div class="row align-items-center g-3">
                            <!-- Package Details -->
                            <div class="col-md-8">
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                    <?php if ($month_name) : ?>
                                        <span class="badge bg-primary px-3 py-1" style="font-size:.85rem; font-weight:600;">
                                            <i class="fe fe-calendar me-1"></i><?= $month_name ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="badge bg-secondary text-white px-2 py-1" style="font-size:.8rem;">
                                        <?= htmlspecialchars($pkg_type) ?>
                                    </span>

                                    <?php if ($is_expired) : ?>
                                        <span class="badge bg-light text-danger border border-danger-subtle px-2 py-1" style="font-size:.8rem;">
                                            <i class="fe fe-alert-circle me-1"></i>Expired: <?= $exp_date ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge bg-success text-white px-2 py-1" style="font-size:.8rem;">
                                            <i class="fe fe-check-circle me-1"></i>Active
                                        </span>
                                        <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1" style="font-size:.8rem;">
                                            <i class="fe fe-clock me-1"></i>Next Auto-Debit: <?= $exp_date ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($fy_formatted)) : ?>
                                        <span class="badge bg-light text-secondary border px-2 py-1" style="font-size:.8rem;">
                                            F.Y. <?= htmlspecialchars($fy_formatted) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h6 class="fw-bold text-dark mb-1" style="font-size:1rem;">
                                    <?php
                                    if (!empty($epkg['service_names'])) {
                                        echo htmlspecialchars(implode(', ', $epkg['service_names']));
                                    } else {
                                        echo 'Package Services';
                                    }
                                    ?>
                                </h6>

                                <?php if ($is_expired && $cum_count > 1) : ?>
                                    <div class="mt-2">
                                        <span class="badge bg-warning text-dark px-2 py-1" style="font-size:.78rem; font-weight:600; border:1px solid #ffeba5;">
                                            <i class="fe fe-layers me-1"></i>Includes <?= $cum_count ?> Months Auto-Renewal: <?= htmlspecialchars(implode(', ', $cum_month_names)) ?>
                                        </span>
                                    </div>
                                <?php elseif ($is_expired) : ?>
                                    <p class="text-muted small mb-0 mt-1">
                                        Click "Renew & Pay" to clear pending dues from your wallet.
                                    </p>
                                <?php else : ?>
                                    <p class="text-muted small mb-0 mt-1">
                                        <i class="fe fe-info me-1 text-primary"></i>Active package. Renewal will be auto-debited on <strong><?= $exp_date ?></strong>.
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Amount & Action -->
                            <div class="col-md-4 text-md-end border-start-md pt-2 pt-md-0">
                                <div class="mb-2">
                                    <small class="text-muted d-block" style="font-size:.75rem;"><?= $is_expired ? 'Renewal Amount' : 'Package Amount' ?></small>
                                    <span class="fw-bold <?= $is_expired ? 'text-danger' : 'text-success' ?>" style="font-size:1.3rem;">
                                        ₹<?= number_format($single_total, 2) ?>
                                    </span>
                                </div>

                                <?php if ($is_expired) : ?>
                                    <button class="btn btn-danger btn-sm px-4 rounded-pill renew-pkg-btn"
                                            data-pkg-id="<?= $epkg['id'] ?>"
                                            data-amount="<?= $cum_total ?>"
                                            data-months="<?= $cum_count ?>"
                                            data-months-str="<?= htmlspecialchars(implode(', ', $cum_month_names)) ?>">
                                        <i class="fe fe-credit-card me-1"></i><?= ($cum_count > 1) ? 'Renew All ' . $cum_count . ' Months' : 'Renew & Pay' ?>
                                    </button>
                                <?php else : ?>
                                    <div class="d-inline-flex align-items-center gap-1 p-2 px-3 rounded-pill bg-success-transparent text-success border border-success-subtle fw-semibold" style="font-size:.82rem;">
                                        <i class="fe fe-check-circle me-1"></i>Next Auto-Debit: <?= $exp_date ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php else : ?>
        <div class="card border-0 shadow-sm text-center py-5" style="border-radius:12px; background:#f8fff9; border: 1px solid #d4edda !important;">
            <div class="card-body">
                <div class="avatar avatar-xxl text-success rounded-circle mb-3 mx-auto" style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;background:#e2f0e6;">
                    <i class="fe fe-check-circle" style="font-size:2rem;"></i>
                </div>
                <h5 class="fw-bold text-success">No Packages Found</h5>
                <p class="text-muted mb-0">No active or expired packages found for this firm.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('body').on('click', '.renew-pkg-btn', function() {
        var btn    = $(this);
        var pkgId  = btn.data('pkg-id');
        var amt    = btn.data('amount');
        var mCount = parseInt(btn.data('months')) || 1;
        var mStr   = btn.data('months-str') || '';

        if (!pkgId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Error', text: 'Invalid package.', icon: 'error', confirmButtonText: 'OK' });
            } else {
                alert('Invalid package.');
            }
            return;
        }

        var originalBtnHtml = btn.html();

        function executeRenewal() {
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing…');

            $.ajax({
                type: 'POST',
                url: '<?= base_url("services/renewpackage"); ?>',
                data: { package_id: pkgId },
                dataType: 'json',
                success: function(resp) {
                    if (resp.status) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Renewal Successful!',
                                text: resp.message || 'Package renewed successfully.',
                                icon: 'success',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'rounded-4 shadow-lg border-0',
                                    confirmButton: 'btn btn-success px-4 rounded-pill'
                                },
                                buttonsStyling: false
                            }).then(function() {
                                window.location.reload();
                            });
                        } else {
                            alert(resp.message || 'Package renewed successfully.');
                            window.location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Renewal Failed',
                                text: resp.message || 'Unable to renew package.',
                                icon: 'error',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'rounded-4 shadow-lg border-0',
                                    confirmButton: 'btn btn-danger px-4 rounded-pill'
                                },
                                buttonsStyling: false
                            }).then(function() {
                                if (resp.redirect) window.location = resp.redirect;
                                else btn.prop('disabled', false).html(originalBtnHtml);
                            });
                        } else {
                            alert(resp.message || 'Unable to renew package.');
                            if (resp.redirect) window.location = resp.redirect;
                            btn.prop('disabled', false).html(originalBtnHtml);
                        }
                    }
                },
                error: function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Server Error',
                            text: 'Something went wrong. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'rounded-4 shadow-lg border-0',
                                confirmButton: 'btn btn-danger px-4 rounded-pill'
                            },
                            buttonsStyling: false
                        });
                    } else {
                        alert('Server error. Please try again.');
                    }
                    btn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        }

        if (typeof Swal !== 'undefined') {
            var modalHtml = (mCount > 1 && mStr)
                ? '<div class="text-center py-2">' +
                    '<div class="mb-3"><span class="badge bg-danger text-white px-3 py-2 rounded-pill font-semibold fs-13"><i class="fe fe-layers me-1"></i> Auto-Renewing ' + mCount + ' Months</span></div>' +
                    '<p class="text-secondary mb-2" style="font-size:0.92rem;">Months: <strong>' + mStr + '</strong></p>' +
                    '<div class="my-3 p-3 rounded-3" style="background:#f8f9fa; border:1px solid #eaedf1;">' +
                        '<small class="text-muted d-block uppercase fw-bold mb-1" style="font-size:0.75rem;">Total Payment Amount</small>' +
                        '<span class="fw-bold text-danger fs-24">₹' + parseFloat(amt).toFixed(2) + '</span>' +
                    '</div>' +
                    '<p class="text-muted mb-0" style="font-size:0.8rem;">Amount will be deducted from your wallet balance.</p>' +
                  '</div>'
                : '<div class="text-center py-2">' +
                    '<p class="text-secondary mb-2" style="font-size:0.92rem;">Confirm package renewal</p>' +
                    '<div class="my-3 p-3 rounded-3" style="background:#f8f9fa; border:1px solid #eaedf1;">' +
                        '<small class="text-muted d-block uppercase fw-bold mb-1" style="font-size:0.75rem;">Payment Amount</small>' +
                        '<span class="fw-bold text-danger fs-24">₹' + parseFloat(amt).toFixed(2) + '</span>' +
                    '</div>' +
                    '<p class="text-muted mb-0" style="font-size:0.8rem;">Amount will be deducted from your wallet balance.</p>' +
                  '</div>';

            Swal.fire({
                title: 'Package Renewal Confirmation',
                html: modalHtml,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fe fe-check-circle me-1"></i> Renew & Pay Now',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'rounded-4 shadow-lg border-0',
                    confirmButton: 'btn btn-danger btn-lg px-4 rounded-pill me-2',
                    cancelButton: 'btn btn-light btn-lg px-4 rounded-pill border'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    executeRenewal();
                }
            });
        } else {
            var confirmMsg = (mCount > 1 && mStr)
                ? 'Renew ' + mCount + ' months [' + mStr + '] for ₹' + parseFloat(amt).toFixed(2) + ' from your wallet?'
                : 'Renew this package for ₹' + parseFloat(amt).toFixed(2) + ' from your wallet?';
            if (confirm(confirmMsg)) {
                executeRenewal();
            }
        }
    });
});
</script>
