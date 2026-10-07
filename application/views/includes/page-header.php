
                        <!-- PAGE-HEADER -->
                        <div class="page-header">
                            <h1 class="page-title text-theme"><?= $title; ?></h1>
                            <?php
                            if($this->session->role=='customer'){
                                $sess_years=year_dropdown();
                                $sess_firms=firm_dropdown();
                            ?>
                            <div>
                                <?php if (isset($title) && $title === 'My Package'): ?>
                                    <a href="<?= base_url('package/list') ?>" class="btn btn-primary"><i class="fe fe-list"></i> Package list</a>
                                <?php elseif (isset($title) && $title === 'Package list'): ?>
                                    <a href="<?= base_url('package') ?>" class="btn btn-secondary"><i class="fe fe-arrow-left"></i> Back</a>
                                <?php endif; ?>
                                <?php
                                    $s_year = $this->session->year;
                                    $s_firm = $this->session->firm;
                                    $disp_year = ($s_year !== null && isset($sess_years[$s_year])) ? $sess_years[$s_year] : '';
                                    $disp_firm = ($s_firm !== null && isset($sess_firms[$s_firm])) ? $sess_firms[$s_firm] : '';
                                ?>
                                <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#smallmodal" id="display_year" >Year: <?= $disp_year ?></a>
                                <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#smallmodal" id="display_firm">Firm: <?= $disp_firm ?></a>
                            </div>
                            <div class="modal  fade" id="smallmodal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
                                <div class="modal-dialog modal-sm" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"></h5>
                                            <?php
                                                if(empty($sess_firms) || count($sess_firms)==1){
                                                    $custfirm=false;
                                                }
                                                else{
                                                    $custfirm=true;
                                            ?>
                                            <button class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">×</span>
                                                </button>
                                            <?php
                                                }
                                            ?>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-12">
                                                    <?= create_form_input('select','','Year',false,$this->session->year??'',['id'=>'sess_year'],$sess_years); ?>
                                                </div>
                                                <div class="col-12">
                                                    <?php
                                                        if($custfirm){
                                                            echo create_form_input('select','','Firm',false,$this->session->firm??'',['id'=>'sess_firm'],$sess_firms);
                                                        }
                                                        else{
                                                    ?>
                                                    <p class="text-danger">Firm not Added!</p>
                                                    <a href="<?= base_url('firms/') ?>" class="btn btn-sm  btn-info">Add Firm</a>
                                                    <?php
                                                        }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <?php if($custfirm){ ?>
                                            <button class="btn btn-primary" id="save-sess-data">Save changes</button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <script>
                                $(document).ready(function(){
                                    $('body').on('click','#save-sess-data',function(){
                                        if($('#sess_year').val()=='' && $('#sess_firm').val()==''){
                                           alert('Select Year and Firm!');
                                            return false;
                                        }
                                        else if($('#sess_year').val()=='' && $('#sess_firm').val()!=''){
                                           alert('Select Year!');
                                            return false;
                                        }
                                        else if($('#sess_year').val()!='' && $('#sess_firm').val()==''){
                                           alert('Select Firm!');
                                            return false;
                                        }
                                        $.post('<?= base_url('home/savesessdata') ?>',
                                               {year:$('#sess_year').val(),firm:$('#sess_firm').val()},function(data){
                                            if(data==1){
                                               window.location.reload();
                                            }
                                        });
                                    });
                                    <?php
                                        if(($this->session->year===NULL || $this->session->firm===NULL) && $this->uri->segment(1)!='firms'){
                                    ?>
                                    const myModal = new bootstrap.Modal(document.getElementById('smallmodal'));
                                    myModal.show();
                                    <?php
                                        }
                                    ?>
                                });
                            </script>
                            <?php
                            }
                            ?>
                            <div>
                                <?php 
                                if(isset($breadcrumb) && is_array($breadcrumb)){ 
                                ?>
                                <ol class="breadcrumb">
                                    <?php
                                        if(is_array($breadcrumb)){
                                            $breadcrumb=$breadcrumb;
                                            if(!isset($breadcrumb['active']) && $this->uri->segment(1)!=''){ $breadcrumb['active']=$title; }
                                            foreach($breadcrumb as $link=>$crumb){
                                                if($link=='active'){
                                                    echo '<li class="breadcrumb-item active" aria-current="page">'.$crumb.'</li>';
                                                }
                                                else{
                                                    echo '<li class="breadcrumb-item"><a href="'.base_url($link).'">'.$crumb.'</a></li>';
                                                }
                                            }	
                                        }
                                    ?>
                                </ol>
                                <?php } ?>
                            </div>
                        </div>
                        <!-- PAGE-HEADER END -->