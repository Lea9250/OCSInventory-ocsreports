<?php
/*
 * Created on 7 mai 2009
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 function function_admin($size="width=650,height=450"){
 	global $l,$pages_refs;
 	echo "<a href=# onclick=window.open(\"index.php?".PAG_INDEX."=".$pages_refs['ms_custom_admin_rsx']."&prov=add&head=1\",\"ADD_RSX\",\"location=0,status=0,scrollbars=1,menubar=0,resizable=0,".$size."\")><input type = button value='".$l->g(835)."'></a>";
	echo "&nbsp;";
	echo "<a href=# onclick=window.open(\"index.php?".PAG_INDEX."=".$pages_refs['ms_custom_admin_type']."&head=1\",\"ADD_TYPE\",\"location=0,status=0,scrollbars=1,menubar=0,resizable=0,width=800,height=500\")><input type = button value='".$l->g(836)."'></a>";				
 	
 	
 }
?>