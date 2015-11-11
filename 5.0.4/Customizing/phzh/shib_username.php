/*******************************************************/
/* Liefert den Benutzernamen beim Login ueber AAI      */
/* Pascal Schmitt				       */
/* 31.10.13 PSM: Anpassungen fuer Logins der PHZH      */
/*******************************************************/

<?php
	if ($newUser)
	{
		// Falls PH User dann @aai anhaengen damit es mit dem normalen Login keine Probleme gibt
		if (strpos($newUser["email"],"@phzh.ch"))
		{
			$userObj->setlogin($newUser["email"]."@aai");
		}
		else if (strpos($newUser["email"],"@stud.phzh.ch"))
		{
			$userObj->setlogin($newUser["email"]."@aai");
		}
		else
		{ 
			$userObj->setlogin($newUser["email"]);
		}
	}
?>
