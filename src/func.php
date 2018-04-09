<?php

function secEnv($name)
{
	$value = getenv($name);
	if (strpos($value, "VAULT:") === 0)
	{
		$value   = substr($value, 6);

		$cache      = [];
		$cache_ttl  = getenv('VAULT_CACHE_TTL')>0 ? (int) getenv('VAULT_CACHE_TTL') : 300;
		$cache_file = getenv('VAULT_CACHE_FILE') ? (string) getenv('VAULT_CACHE_FILE') : sys_get_temp_dir() . DIRECTORY_SEPARATOR .'secenv.json';

		if(file_exists($cache_file))
		{
			$cache = json_decode(file_get_contents($cache_file), true);
			$cache_ttl+= filemtime($cache_file);
		}

		if (array_key_exists($value, $cache) && $cache_ttl > time()) {
			$value = $cache[$value];
		}
		else
		{
			$ch = curl_init();

			$token   = (string) getenv('VAULT_TOKEN');
			$timeout = (int) getenv('VAULT_TIMEOUT')>0 ? (int) getenv('VAULT_TIMEOUT') : 100;
			$url     = (string) getenv('VAULT_ADDR').'/v1/secret/'.$value;

			curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Vault-Token: '.$token]);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeout);
			
			$res = curl_exec($ch);
			
			if ($res === false)
			{
				error_log('secEnv(): curl_error: ' . curl_error($ch));
				$value = array_key_exists($value, $cache) ? $cache[$value] : false;
			}
			else
			{
				$r = json_decode($res, true);
				
				if (isset($r['data']['value']))
				{
					if (!array_key_exists($value, $cache) || $cache[$value] != $r['data']['value'])
					{
						$cache[$value] = (string) $r['data']['value'];
						file_put_contents($cache_file, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
					}

					$value = (string) $r['data']['value'];
				}
				else
				{
					error_log('secEnv(): no data. Vault response: ' . $res);
					$value = array_key_exists($value, $cache) ? $cache[$value] : false;
				}
			}
			
			curl_close($ch);
		}
	}

	return $value;
}
