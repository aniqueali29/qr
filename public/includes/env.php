<?php
// Environment loader inside public directory

// Internal cache for parsed env file
static $ENV_CACHE = null;

function env_load_file(): void {
	global $ENV_CACHE;
	if ($ENV_CACHE !== null) {
		return;
	}
	$ENV_CACHE = [];
	$envPath = realpath(__DIR__ . '/../config.env');
	if ($envPath === false || !is_readable($envPath)) {
		error_log('[ENV] config.env not found at public/config.env');
		return;
	}
	$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return;
	}
	foreach ($lines as $line) {
		$trim = trim($line);
		if ($trim === '' || $trim[0] === '#') {
			continue;
		}
		$pos = strpos($trim, '=');
		if ($pos === false) {
			continue;
		}
		$key = trim(substr($trim, 0, $pos));
		$val = trim(substr($trim, $pos + 1));
		// Remove optional surrounding quotes
		if (strlen($val) >= 2) {
			$first = $val[0];
			$last = $val[strlen($val) - 1];
			if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
				$val = substr($val, 1, -1);
			}
		}
		$ENV_CACHE[$key] = $val;
	}
}

function env_get(string $key, $default = null) {
	global $ENV_CACHE;
	if ($ENV_CACHE === null) {
		env_load_file();
	}
	if ($ENV_CACHE !== null && array_key_exists($key, $ENV_CACHE)) {
		return $ENV_CACHE[$key];
	}
	$value = getenv($key);
	if ($value === false) {
		return $default;
	}
	return $value;
}

function env_bool(string $key, bool $default = false): bool {
	$val = env_get($key, $default ? '1' : '0');
	if (is_bool($val)) return $val;
	$val = strtolower((string)$val);
	return in_array($val, ['1', 'true', 'yes', 'on'], true);
}

function env_int(string $key, int $default = 0): int {
	$val = env_get($key, $default);
	return (int)$val;
}

function env_required(array $keys): void {
	foreach ($keys as $key) {
		if (getenv($key) === false) {
			// Do not hard-fail in dev; log a warning instead
			error_log("[ENV] Missing required env var: $key");
		}
	}
}

function storage_path(string $subPath = ''): string {
	$root = realpath(__DIR__ . '/..');
	$path = $root . DIRECTORY_SEPARATOR . 'storage';
	if (!empty($subPath)) {
		$path .= DIRECTORY_SEPARATOR . ltrim($subPath, DIRECTORY_SEPARATOR);
	}
	return $path;
}

?>

