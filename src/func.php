<?php

function secEnv($name)
{
	$value = getenv($name);
	if (strpos($value, "VAULT:") === 0)
	{
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Vault-Token: '.getenv('VAULT_TOKEN')]);
		curl_setopt($ch, CURLOPT_URL, getenv('VAULT_ADDR').'/v1/secret/'.substr($value, 6));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 100);

		$res = curl_exec($ch);

		if ($res === false)
		{
			error_log('secEnv(): curl_error: ' . curl_error($ch));
		} 
		else
		{
			$r = json_decode($res, true);

			if (!isset($r['data']['value']))
			{
				error_log('secEnv(): no data. Vault response: ' . $res);
			}

			$value = empty($r['data']['value']) ? false : strval($r['data']['value']);
		}

		curl_close($ch);
	}

	return $value;
}