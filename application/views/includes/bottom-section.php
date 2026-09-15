
    <?php
        if(isset($page) && $page=='login'){
    ?>
    </div>
    <?php
        }
    ?>
    <!-- BACK-TO-TOP -->
    <a href="#top" id="back-to-top"><i class="fa fa-angle-up"></i></a>


    <!-- BOOTSTRAP JS -->
    <script src="<?= file_url('includes/plugins/bootstrap/js/popper.min.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/bootstrap/js/bootstrap.min.js'); ?>"></script>

    <?php
        if(isset($page) && $page=='login'){
    ?>
    <!-- SHOW PASSWORD JS -->
    <script src="<?= file_url('includes/js/show-password.min.js'); ?>"></script>

    <!-- GENERATE OTP JS -->
    <script src="<?= file_url('includes/js/generate-otp.js'); ?>"></script>
    <!-- Perfect SCROLLBAR JS-->
    <script src="<?= file_url('includes/plugins/p-scroll/perfect-scrollbar.js'); ?>"></script>
    <!-- Color Theme js -->
    <script src="<?= file_url('includes/js/themeColors.js'); ?>"></script>


    <!-- CUSTOM JS -->
    <script src="<?= file_url('includes/js/custom.js'); ?>"></script>


    <?php
        }
    else{
    ?>
    <!-- SIDE-MENU JS -->
    <script src="<?= file_url('includes/plugins/sidemenu/sidemenu.js'); ?>"></script>

    <!-- SIDEBAR JS -->
    <script src="<?= file_url('includes/plugins/sidebar/sidebar.js'); ?>"></script>
    <!-- Perfect SCROLLBAR JS-->
    <script src="<?= file_url('includes/plugins/p-scroll/perfect-scrollbar.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/p-scroll/pscroll.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/p-scroll/pscroll-1.js'); ?>"></script>

    <!-- INTERNAL Notifications js -->
    <script src="<?= file_url('includes/plugins/notify/js/rainbow.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/notify/js/sample.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/notify/js/jquery.growl.js'); ?>"></script>
    <script src="<?= file_url('includes/plugins/notify/js/notifIt.js'); ?>"></script>

    <!-- Color Theme js -->
    <script src="<?= file_url('includes/js/themeColors.js'); ?>"></script>
    <!-- Sticky js -->
    <script src="<?= file_url('includes/js/sticky.js'); ?>"></script>

    <!-- SWEETALERT2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CUSTOM JS -->
    <script src="<?= file_url('includes/js/custom.js'); ?>"></script>
    <script src="<?= file_url('includes/js/myscript.js'); ?>"></script>


    <?php
        }
    ?>

    <?php
		if(!empty($bottom_script)){
		  foreach($bottom_script as $key=>$script){
			  if($key=="link"){
					if(is_array($script)){
						foreach($script as $single_script){
							echo "<script src='$single_script'></script>\n\t\t";
						}
					}
					else{
						echo "<script src='$script'></script>\n\t\t";
					}
			  }
			  elseif($key=="file"){
				if(is_array($script)){
					foreach($script as $single_script){
						echo "<script src='".file_url("$single_script")."'></script>\n\t\t";
					}
				}
				else{
					echo "<script src='".file_url("$script")."'></script>\n\t\t";
				}
			  }
		  }
		}
		?>
    <script src="<?= file_url('includes/js/script.js'); ?>"></script>
        
</body>

</html>
<?php
if(isset($_SESSION['__ci_vars']) && is_array($_SESSION['__ci_vars'])){
    foreach($_SESSION['__ci_vars'] as $key=>$value){
        if($value=='old'){
            unset($_SESSION[$key]);
            unset($_SESSION['__ci_vars'][$key]);
        }
    }
    if(empty($_SESSION['__ci_vars'])){
        unset($_SESSION['__ci_vars']);
    }
}
?>