<?php
/* 
	Schreibt die PHZH/PHSH E-Mail Adressen in ILIAS Loginnamen um 
	muss im File Services/Init/classes/class.ilInitialisation.php in der 
	'function initILIAS($context = "web")' eingebunden werden.

	Pascal Schmitt, 17.02.09
	Letzte Aenderung: 16.08.11 - Studiaccounts werden umgeschrieben, Direktlinks gehen so wieder
*/

// Username nur umschreiben beim Login und nicht bei jedem Aufruf
if (isset($_POST['username']))
{
	$phzh_username = trim($_POST['username']);

	// Erst mal alle Ausnahmen behandeln: _admin, .admin, _test und _lp
	if (strpos($phzh_username,"_admin") ||  strpos($phzh_username,".admin") ||  strpos($phzh_username,"_test") ||  strpos($phzh_username,"_lp") ||  strpos($phzh_username,"soap") || strpos($phzh_username,"cronadmin")  || strpos($phzh_username,"consultant")  || strpos($phzh_username,"_tst") || strpos($phzh_username,"_ext") || strpos($phzh_username,"_user"))
		$login = $phzh_username;

    	// Enthaelt das Login keinen Punkt  und kein @ ist es ein Stud und kann mit @stud.phzh.ch erweitert werden
    	elseif (!strpos($phzh_username,".") && !strpos($phzh_username,"@"))
        	$login = $phzh_username . "@stud.phzh.ch";

    	// Enthaelt das Login hingegen einen Punkt ist es ein/e Mitarbeiter/in und kann mit @phzh.ch erweiter werden
	elseif (strpos($phzh_username,".") && !strpos($phzh_username,"@"))
	 	$login = $phzh_username . "@phzh.ch";
	else
       		$login = $phzh_username;

	$_POST['username'] = $login;
}
?>
