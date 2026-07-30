<?php
$cont=mysqli_connect('localhost','root','Admin12345','AppIOT');
$sql="select * from usuarios where usuario='".$_GET['usu']."' and clave='
".$_GET['pass']."'";
$result=mysqli_query($cont,$sql);
$num=mysqli_num_rows($result);
$val=array('estado'=>$num==null?'0':$num);
echo json_encode($val); 

?>
