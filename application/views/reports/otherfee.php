<style>
    .cell-right{
        text-align: right;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
    }
    #acc_table {
        min-width: 100%;
        white-space: nowrap;
    }
    #acc_table th,
    #acc_table td {
        white-space: nowrap;
        padding: 8px;
    }
    #acc_table tfoot tr {
        font-weight: bold;
        background-color: #f8f9fa;
    }
    #acc_table tfoot td {
        font-weight: bold;
    }
</style>
<?php
$user_id=$year='';
if($this->session->flashdata('user_id')!==NULL){
    $user_id=$this->session->flashdata('user_id');
}
if($this->session->flashdata('year')!==NULL){
    $year=$this->session->flashdata('year');
}
?>
                                <div class="card-body">
                                    <div class="row my-4">
                                        <div class="col-md-12">
                                            <div class="table-responsive">
                                                <div id="result">
                                                    <table class="table table-bordered" id="acc_table">
                                                    <thead>
                                                        <tr>
                                                            <?php
                                                                foreach($report[0] as $value){
                                                            ?>
                                                            <th><?= $value; ?></th>
                                                            <?php
                                                                }
                                                            ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                            $rows=count($report);
                                                            if(!empty($report)){
                                                                foreach($report as $key=>$row){
                                                                    if($key==0){ continue; }
                                                                    if($key==$rows-1){
                                                                        $footer=$row;
                                                                        break;
                                                                    }
                                                        ?>
                                                        <tr>
                                                            <?php
                                                                foreach($row as $value){
                                                            ?>
                                                            <td><?= $value; ?></td>
                                                            <?php
                                                                }
                                                            ?>
                                                        </tr>
                                                        <?php
                                                                }
                                                            }
                                                        ?>
                                                    </tbody>
                                                    <?php if(!empty($footer)){ ?>
                                                    <tfoot>
                                                        <tr class="total-row">
                                                            <?php
                                                                foreach($footer as $value){
                                                            ?>
                                                            <td><strong><?= $value; ?></strong></td>
                                                            <?php
                                                                }
                                                            ?>
                                                        </tr>
                                                    </tfoot>
                                                    <?php } ?>
                                                    </table>
                                                    <div id="acc_json" class="d-none"><?= json_encode($result ?? $report ?? []); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

            <script>
                $(document).ready(function(e) {
                    $('body').on('change','#user_id',function(){
                        var user_id=$(this).val();
                        $.ajax({
                            type:"post",
                            url:"<?= base_url('orders/getpackage/'); ?>",
                            data:{user_id:user_id},
                            success:function(data){
                                $('#package').val(data);
                                getfirm(user_id);
                            }
                        });
                    });
                    $('body').on('change','#fyear',function(){
                        getreport();
                    });
                    <?php
                    if($year!=''){
                    ?>
                    $('#fyear').trigger('change');
                    <?php
                    }
                    ?>
                });
                
                function getfirm(user_id){
                    $.ajax({
                        type:"post",
                        url:"<?= base_url('orders/getfirms/'); ?>",
                        data:{user_id:user_id},
                        success:function(data){
                            $('#firm_id').html(data);
                            getreport();
                        }
                    });     
                }
                
                function getreport(){
                    resetFields();
                    var user_id=$('#user_id').val();
                    var firm_id=$('#firm_id').val();
                    var year=$('#fyear').val();
                    $.ajax({
                        type:"post",
                        url:"<?= base_url('orders/getyearlyreport/'); ?>",
                        data:{user_id:user_id,firm_id:firm_id,year:year},
                        success:function(data){
                            $('#result').html(data);
                            $('#result table tr').each(function(){
                                $(this).children().last().remove();
                            });
                            //var rows = $('tr:has(td.done)').first().prevAll();
                            //console.log(rows); // Logs the 
                            //rows.addClass('bg-danger');
                            //rows.each(function(){
                                //$(this).children().last().html('');
                            //});
                        }
                    });
                }
                function resetFields(){
                    $('#turnover,#due_date').val('');
                }
                function reloadAjax(){
                    getreport()
                }
            </script>