<style>
    .cell-right {
        text-align: right;
    }

    .table {
        min-width: 2100px;
    }

    .table th,
    .table td {
        width: 160px;
    }
</style>
<?php
$current_fy = get_financial_year();
$year = (!empty($current_fy['id']) && isset($years[$current_fy['id']])) ? $current_fy['id'] : '';
if ($this->session->flashdata('user_id') !== NULL) {
    $user_id = $this->session->flashdata('user_id');
}
if ($this->session->flashdata('year') !== NULL) {
    $year = $this->session->flashdata('year');
}
//print_pre($allcustomers);
?>
<div class="card-body">
    <?= form_open_multipart('orders/saveturnover/'); ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="form-group">
                <?php
                echo create_form_input('select', 'year', "Financial Year", true, $year, ['id' => 'fyear'], $years);
                ?>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-condensed table-bordered">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Firm</th>
                            <?php
                            $fyear1     = substr($year, 0, 4); // e.g. "2024"
                            $fyear2     = substr($year, 4, 4); // e.g. "2025"
                            $monthNames = [
                                'April',
                                'May',
                                'June',
                                'July',
                                'August',
                                'September',
                                'October',
                                'November',
                                'December',
                                'January',
                                'February',
                                'March'
                            ];
                            foreach ($monthNames as $idx => $mName):
                                $mYear = ($idx <= 8) ? $fyear1 : $fyear2;
                            ?>
                                <th class="months"><?= $mName . '-' . $mYear ?></th>
                            <?php endforeach; ?>
                            <th>GTO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($allcustomers)) {
                            foreach ($allcustomers as $customer) {
                        ?>
                                <tr>
                                    <td>
                                        <?= $customer['customer_name'] ?>
                                    </td>
                                    <td>
                                        <?= $customer['firm_name'] ?>
                                        <?= create_form_input('hidden', 'firm_id[' . $customer['user_id'] . '][]', "", true, $customer['firm_id'], ['class' => 'firm_id']);
                                        ?>
                                    </td>
                                    <?php
                                    $start = date('Y-04-01');
                                    for ($i = 0; $i < 12; $i++) {
                                    ?>
                                        <td>
                                            <?= create_form_input('text', 'turnover[' . $customer['user_id'] . '][' . $customer['firm_id'] . '][]', "", false, '', ['class' => 'turnover form-control-sm', 'pattern' => "\d+(\.\d+)?", 'title' => 'Enter valid Turnover Value']); ?>
                                        </td>
                                    <?php } ?>
                                    <td>
                                        <?= create_form_input('text', '', "", false, '', ['class' => 'gto form-control-sm', 'readonly' => 'true']); ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <button type="submit" name="savemonthlyturnover" class="btn btn-sm btn-success" id="save-btn">Save Turnover</button>
            <button type="button" class="btn btn-sm btn-danger btn-cancel d-none">Cancel</button>
        </div>
    </div>
    <?= form_close(); ?>
    <div class="row my-4">
        <div class="col-md-12">
            <div id="result">
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function(e) {
        $('body').on('change', '#user_id', function() {
            var user_id = $(this).val();
            $.ajax({
                type: "post",
                url: "<?= base_url('orders/getpackage/'); ?>",
                data: {
                    user_id: user_id
                },
                success: function(data) {
                    $('#package').val(data);
                    getfirm(user_id)
                }
            });
        });
        $('body').on('change', '#fyear', function() {
            var year = $(this).val();
            if (year != '') {
                var firstYear = year.substr(0, 4);
                var lastYear = year.substr(4, 4);
                var monthNames = ['April', 'May', 'June', 'July', 'August', 'September',
                    'October', 'November', 'December', 'January', 'February', 'March'
                ];
                $('.months').each(function(index) {
                    var suffix = (index <= 8) ? firstYear : lastYear;
                    $(this).text(monthNames[index] + '-' + suffix);
                });
            }
            $('.turnover,.gto').val('');
            $.ajax({
                type: "post",
                url: "<?= base_url('orders/getturnoverdata/'); ?>",
                data: {
                    year: year
                },
                success: function(data) {
                    console.log(data);
                    if (data != '[]') {
                        data = JSON.parse(data);
                        for (var i = 0; i < data.length; i++) {
                            var to = $('input[name="turnover[' + data[i]['index1'] + '][' + data[i]['index2'] + '][]"]').eq(data[i]['monthindex']);
                            to.val(data[i]['turnover']);
                            if (data[i]['turnover'] != '') {
                                to.trigger('change');
                            }
                        }
                    }
                }
            });
        });
        $('body').on('change keyup', '.turnover', function() {
            var $row = $(this).closest('tr');
            var turnover = 0;
            $row.find('.turnover').each(function() {
                let value = Number($(this).val());
                value = isNaN(value) ? 0 : value;
                turnover += value;
            });
            $row.find('.gto').val(turnover);;
        });
        <?php
        if ($year != '') {
        ?>
            $('#fyear').trigger('change');
        <?php
        }
        ?>
    });

    function getfirm(user_id) {
        $.ajax({
            type: "post",
            url: "<?= base_url('orders/getfirms/'); ?>",
            data: {
                user_id: user_id
            },
            success: function(data) {
                $('#firm_id').html(data);
                getreport();
            }
        });
    }

    function getreport() {
        resetFields();
        var user_id = $('#user_id').val();
        var year = $('#fyear').val();
        $('.turnover,.due_date').val('');
        $('.turnover,.due_date').prop('readonly', false);;
        $.ajax({
            type: "post",
            url: "<?= base_url('orders/getyearlyreport/'); ?>",
            data: {
                user_id: user_id,
                year: year
            },
            success: function(data) {
                $('#result').html(data);
                $('#result table tr').each(function() {
                    // Remove the last cell in each row
                    $(this).find('td:last, th:last').remove();
                    var result = $('#acc_json').text();
                    if (result != '[]') {
                        result = JSON.parse(result);
                        var i = 0;
                        $('.month').each(function() {
                            if (result.hasOwnProperty($(this).val())) {
                                var turnover = result[$(this).val()].turnover;
                                var due_date = result[$(this).val()].due_date;
                                var paid = result[$(this).val()].paid;
                                $(this).closest('tr').find('.turnover').val(turnover);
                                $(this).closest('tr').find('.due_date').val(due_date);
                                if (paid > 0) {
                                    $(this).closest('tr').find('.turnover').prop('readonly', true);
                                    $(this).closest('tr').find('.due_date').prop('readonly', true);
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    function resetFields() {
        $('#turnover,#due_date').val('');
    }

    function reloadAjax() {
        getreport()
    }
</script>