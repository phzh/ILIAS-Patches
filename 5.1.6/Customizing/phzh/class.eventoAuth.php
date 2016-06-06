<?php
	/*
	*	File: class.EventoAuth.php
	*	Führt ein Login über den Webservice http://10.20.51.41/WSauth/auth.asmx?wsdl aus.
	*	@author $Author: pascal.schmitt $
	*	@version $Rev: 2 $; $Author: pascal.schmitt $; $Date: 2007-10-26 10:38:23 +0200 (Fr, 26 Okt 2007) $
	*	@id	$Id: class.ADSAuth.php 2 2007-10-26 08:38:23Z pascal.schmitt $ 	
	*
	*	20.08.08: Anpassung an den neuen Webservice gemacht (SOAP-Schnittstelle)
	*   05.11.08: Funktionen getAdsGroups und checkGroup erstellt(F.Andres)
	*   06.11.08: Bugfix in checkGroup, ps
	*/ 
 
	class EventoAuth
	{
		protected $lastError 		= "";
		protected $userData;
		protected $XML_ADS_Response;
		protected $auth_scriptUrl 	= "https://eventophzh.phzh.ch/evtoWS/evtoWS.asmx?WSDL";
		protected $soap_client;
		protected $ADSGroups;
		protected $auth_response;

		public function __construct($login,$password)
		{
			// Wurd ein Login und Passwort eingegeben?
			if ($login == "")
			{
				$this->lastError = "ADS_EMPTY_LOGIN";
			}
			elseif ($password == "")
			{
				$this->lastError = "ADS_EMPTY_PASSWORD";
			}
			// $this->auth_postvars nur zusammen stellen wenn bis hier kein Fehler besteht 
			if ($this->lastError == "")
			{
				$this->userData['benutzerName'] = $login;
				$this->userData['pw'] = $password;
				$this->userData['evtoWeb'] = "false";
				$this->userData['appName'] = "ILIAS PHZH";
			}
		}

		public function startADSAuth()
		{
			// Wurde bereits im __Contruct ein Fehler gefunden dann hier false zurück geben.
			if ($this->lastError <> "")
			{
				return false;
			}
			else
			{
				// Versuchen zum SOAP-Server zu verbinden
		        	try {
				        $this->soap_client = new SoapClient($this->auth_scriptUrl);
			    	    }
		    		catch(SoapFault $s)
	    	    		{
    		    			$this->lastError = $s;
					return false;
			        }
				// Versuchen Abfrage auszuführen
		        	try {
    			            $this->auth_response = $this->soap_client->__soapCall("Authenticate", array($this->userData));
	   			    }
			    	    catch(SoapFault $s)
				    {
					$this->lastError = $s;
					return false;
				    }
				// Auth erfolgreich?
		       	        if ($this->auth_response->AuthenticateResult->Token == "Anmeldung nicht erfolgreich") 
				{
					$this->lastError = "ADS_LOGIN_FAILED";
					return false;
				}
				else 
				{
					// Login erfolgreich! Daten zurück geben
					$this->hash = $this->auth_response->AuthenticateResult->Token;
					return true;
				}
			}
		}

		public function getAll()
		{
                	return $this->auth_response->AuthenticateResult;
		}

		public function getUserData()
		{
			$daten["token"] = $this->hash;
			return $this->auth_response = $this->soap_client->__soapCall("getPersonenDaten", array($daten));

		}

		public function getUserName()
		{
                	return false;
		}


		public function getGivenName()
		{
			return $this->auth_response->AuthenticateResult->PersonVorname;
		}

		public function getName()
		{
			return $this->auth_response->AuthenticateResult->PersonNachname;
		}

		public function getEMail()
		{
			return $this->auth_response->AuthenticateResult->PersoneMail;
		}

		public function getDomain()
		{
			return false;
		}

		public function getLogin()
		{
			return $this->auth_response->AuthenticateResult->Benutzername;
		}

		public function getAuthError()
		{
			return $this->lastError;
		}

	}
?>
