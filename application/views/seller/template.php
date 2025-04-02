<!DOCTYPE html>
<html>
<?php $this->load->view('seller/include-head.php'); ?>
<div id="loading">
    <div class="lds-ring">
        <div></div>
    </div>
</div>

<body class="hold-transition sidebar-mini layout-fixed ">
    <div class=" wrapper ">
        <?php $this->load->view('seller/include-navbar.php') ?>
        <?php $this->load->view('seller/include-sidebar.php'); ?>
        <?php $this->load->view('seller/pages/' . $main_page); ?>
        <?php $this->load->view('seller/include-footer.php'); ?>
    </div>
    <?php $this->load->view('seller/include-script.php'); ?>
</body>

</html>