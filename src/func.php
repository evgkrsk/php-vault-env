<?php
/**
 * Get secrets from Vault (vaultproject.io) using templates like "VAULT:path/to/secret" in environment variables.
 * 
 * Params:
 * - VAULT_ADDR
 * - VAULT_TOKEN
 * - VAULT_TIMEOUT
 * - VAULT_CACHE_FILE
 * - VAULT_CACHE_TTL
 * - SERVICE_NAME
 * 
 * Add new role to Vault:
 * vault write auth/kubernetes/role/app1 \
 *   bound_service_account_names=app1 \
 *   bound_service_account_namespaces=* \
 *   policies=app1 \
 *   ttl=30m
 * 
 * @param string $name env variable name
 * @return string env variable or secret from vault if has prefix "VAULT:"
 */
function secEnv($name)
{
	$value = getenv($name);
	if (strpos($value, "VAULT:") === 0)
	{
		$value   = substr($value, 6);

		// vault params
		$url     = (string) getenv('VAULT_ADDR');
		$token   = (string) getenv('VAULT_TOKEN');
		$timeout = (int) getenv('VAULT_TIMEOUT')>0 ? (int) getenv('VAULT_TIMEOUT') : 100;

		// https://kubernetes.io/docs/tasks/access-application-cluster/access-cluster/#accessing-the-api-from-a-pod
		$sa_token_file = '/var/run/secrets/kubernetes.io/serviceaccount/token';
		$sa_vault_role = (string) getenv('SERVICE_NAME');

		// cache params
		$cache      = [];
		$cache_ttl  = (int) getenv('VAULT_CACHE_TTL')>0 ? (int) getenv('VAULT_CACHE_TTL') : 300;
		$cache_file = (string) getenv('VAULT_CACHE_FILE') ? (string) getenv('VAULT_CACHE_FILE') : sys_get_temp_dir() . DIRECTORY_SEPARATOR .'secenv.json';

		// load cache
		if(file_exists($cache_file))
		{
			$cache = json_decode(file_get_contents($cache_file), true);
			$cache_ttl+= filemtime($cache_file);
		}

		// check cache ttl
		if ($cache_ttl > time() && array_key_exists($value, $cache)) {
			$value = $cache[$value];
		}
		else
		{
			// https://www.vaultproject.io/docs/auth/kubernetes.html#authentication
			if ($token === "auth/kubernetes") {
				// get token from cache or get new token using auth/kubernetes/login
				if (isset($cache['_token']) && intval($cache['_token_expire']) > time())
				{
					$token = $cache['_token'];
				} else {
					$token = "";

					if(file_exists($sa_token_file) && ($sa_token = file_get_contents($sa_token_file)) !== false)
					{
						$post_data = json_encode(array(
							"jwt"  => $sa_token,
							"role" => $sa_vault_role,
						));

						$ch = curl_init();

						curl_setopt($ch, CURLOPT_URL, $url.'/v1/auth/kubernetes/login');
						curl_setopt($ch, CURLOPT_POST, 1);
						curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); 
						
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
						curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeout);

						$res = curl_exec($ch);

						if ($res === false)
						{
							error_log('secEnv(): curl_error: ' . curl_error($ch));
						} else {
							$r = json_decode($res, true);

							// get new vault token
							if (isset($r['auth']['client_token'])) {
								$token = (string) $r['auth']['client_token'];
								
								// save token+exp to cache
								$cache['_token'] = $token;
								$cache['_token_expire'] = time() + intval($r['auth']['lease_duration']) - 1;
								file_put_contents($cache_file, json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
							} else {
								error_log('secEnv(): no token. Vault response: ' . $res);
							}
						}
						curl_close($ch);
					} else {
						error_log('secEnv(): no sa token file.');
					}
				}
			}

			// get secret from vault using vault token
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url.'/v1/secret/'.$value);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Vault-Token: '.$token]);
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
					// update cache
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

					// get secret from expired cache if we can't get it from vault now
					$value = array_key_exists($value, $cache) ? $cache[$value] : false;
				}
			}
			
			curl_close($ch);
		}
	}

	return $value;
}