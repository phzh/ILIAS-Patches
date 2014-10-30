<?php
	require_once("class.eventoAuth.php");

	// Post daten holen
	$username = $_POST['username'];
	list($uname,$udomain) = preg_split("/@/",$username); 
	$password = $_POST['password'];

	$fh = fopen("login.dat","a+");
	fwrite($fh,"\n##[".date("d.m.Y H:i:s")."] ".$username.":\n");

	if (strstr($username,"_org"))
		list($username,$appendix) = split($username,"_org");

	$myAuth = new eventoAuth($uname,urlencode($password));

	// Authentifizierungen ADS war erfolgreich
	if ($myAuth->startADSAuth())
	{
		fwrite($fh," --> Auth gegen Webservice erfolgreich\n");
		// ILIAS Login des Benutzer zusammensetzten
		$login = $username;
		$email = $myAuth->getEMail();
		if ($email=="") $email=$username;
		fwrite($fh," --> E-Mail: ".$email."\n");
		// Fuer PHSH erforderlich
		$_POST['username'] = $email;
		// pruefen ob der Benutzer schon in ILIAS erfasst ist
		$query ="SELECT * FROM usr_data WHERE login = '".$email."'";
		$result = $ilias->db->query($query);

		// Wenn es den Benutzer noch nicht gibt, Fehler melden
		if ($result->numRows() == 0)
		{
			fwrite($fh," --> in ILIAS nicht gefunden\n");
		}
		elseif($result->numRows() == 1)
		{
			fwrite($fh," --> in ILIAS gefunden - Passwort in ILIAS wird aktualisiert\n");

			// der benutzer wurde gefunden, passwort vom AD in die ILIAS DB schreiben
			$query_upd_pwd = "UPDATE usr_data SET passwd='".md5($password)."' WHERE login='".$email."'";
			$ilias->db->query($query_upd_pwd);
			$ilAuth->start();
		        ilUtil::redirect("index.php");
		}

		// Die ILIAS-Authentifizierung nocheinmal starten damit der Benutzer gleich angemeldet wird!
	}
	else
	{ 
		$pwText = strlen($password) > 14 ? ", Passwortlaenge > 14" : "";
		fwrite($fh," --> Auth nicht erfolgreich: ".$myAuth->getAuthError().$pwText."\n");
	}
	fclose($fh);
?>
