<?php
if (!isset($_POST['data'])) die();
header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=scheduler.ics');
echo $_POST["data"];
?>