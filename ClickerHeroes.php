<?php

/**
 * Clicker Heroes API - A game save api for php.
 * This class has methods to create, encrypt, decrypt
 * and manipulate game play data.
 *
 * @package	Clicker Heroes API
 * @author	Michael Curry <kernelcurry@gmail.com>
 * @author	Éamonn "Wing" Kearns <eamonn.kearns@so-4pt.net>
 */

class ClickerHeroes
{

	/**
	 * Known salts
	 * @var array
	 */
	private $salts = [
		'af0ik392jrmt0nsfdghy0'
	];

	/**
	 * Salt that is found to work in with the imported save
	 * @var mixed
	 */
	private $salt_used = null;

	/**
	 * Encrypted (imported) save.
	 * @var mixed
	 */
	protected $save_encrypted = null;

	/**
	 * Decrypted (array) save.
	 * @var mixed
	 */
	protected $save_decrypted = null;

	/**
	 * Anti-cheat delimiter that is placed between the game
	 * data and the hack check.
	 * @var mixed
	 */
	protected $delimiter = null;

	/**
	 * If this variable is not empty, something went wrong.
	 * @var array
	 */
	protected $errors = [];

	/**
	 * This method loads a game save from a pasteBinURL
	 * The format of a pastebin URI is http://pastebin.com/WLbfZShj
	 * The raw text file is http://pastebin.com/raw.php?i=WLbfZShj
	 * @param string $pasteBinURI The URI of the paste bin file
	 */
	public function loadFromPasteBin($pasteBinURI)
	{
		$rawURL = $pasteBinURI;
		$standardURI = strstr($pasteBinURI, 'raw.php')===FALSE;
		if($standardURI)
		{
			$key = array_pop(explode('/', $pasteBinURI));
			$rawURL = 'http://pastebin.com/raw.php?i='.$key;
		}
		$file = file_get_contents(
			$rawURL,
			false,
			stream_context_create(
				array(
					'http'=>array(
						'method'=>"GET",
						'header'=>"Accept-language: en\r\nCookie: foo=bar\r\n"
					)
				)
			)
		);
		
		$file = trim($file);
		return $this->importSave($file);
	}
	
	public function asJSON()
	{
		return json_encode($this->save_decrypted);
	}
	
	public function __toString()
	{
		return $this->exportSave();
	}

	/**
	 * This function uses a game save to populate required variables.
	 *
	 * @param string $value
	 * @return ClickerHeroes $this
	 */
	public function importSave($value)
	{
		$this->save_encrypted = trim($value);
		$this->getDelimiter();
		$this->decryptSave();
		return $this;
	}

	/**
	 * Take the game save you have manipulated and
	 * export it into an encrypted save that can be used in the game.
	 *
	 * @return string
	 */
	public function exportSave()
	{
		$new = base64_encode(json_encode($this->save_decrypted));
		$hash = md5($new . $this->salt_used);
		$new_save = '';

		for ($i = 0; $i < strlen($new); $i++)
		{
			$new_save .= $new[$i].$this->randomCharacter();
		}
		$new_save .= $this->delimiter;
		$new_save .= $hash;

		return $new_save;
	}

	protected function decryptSave()
	{
		list($saltedData, $saveHash) = explode($this->delimiter, $this->save_encrypted);
		$dataLength = strlen($saltedData); 
		$saltFound = false;
		$saltCounts = count($this->salts);
		$saltIndex = 0;
		
		// this should not be in the loop, it is not contingent on changes in the salt stuff, so we shouldn't be doing it more than once
		$check = '';
		for ($i = 0; $i < $dataLength; $i += 2)
		{
			$check .= $saltedData[$i];
		}
		
		while(!$saltFound && $saltIndex < $saltCounts)
		{
			$salt = $this->salts[$saltIndex];
			$hash = md5($check . $salt);
			if($hash == $saveHash)
			{
				$this->salt_used = $salt;
				$this->save_decrypted = json_decode(base64_decode($check));
				$saltFound = true;
			}
			$saltIndex++;
		}
		
		if(!$saltFound)
		{
			// salts do not work
			throw new Exception('Salts not working');
		}
	}

	protected function getDelimiter()
	{
		// don't need to subtract 48 from the string length, substr allows negative values to do just that
		$this->delimiter = substr($this->save_encrypted, -48, 16);
	}

	public function resetCooldowns()
	{
		foreach ($this->save_decrypted->skillCooldowns as &$cooldown)
		{
			$cooldown = 0;
		}

		return $this;
	}

	protected function randomCharacter()
	{
		$characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		return$characters[mt_rand(0,strlen($characters)-1)];
	}

}