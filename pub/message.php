<?php if (isset($error) && $error) {
?>
<div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
<?php
}
?>
<?php if (isset($info) && $info) {
?>
<div class="alert alert-info" role="alert"><?php echo $info; ?></div>
<?php
}
?>
