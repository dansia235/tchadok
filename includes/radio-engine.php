<?php
/**
 * Helpers d'integration moteur radio (Icecast/Liquidsoap/LibreTime)
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Convertit une variable env en booleen.
 */
function radioEnvBool($key, $default = false) {
    $value = env($key, $default ? 'true' : 'false');
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

/**
 * Nettoie une URL potentielle.
 */
function radioNormalizeUrl($url) {
    if ($url === null) {
        return null;
    }
    $value = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($value === '') {
        return null;
    }
    return str_replace('&amp;', '&', $value);
}

/**
 * Rend une URL absolue si necessaire.
 */
function radioMakeAbsoluteUrl($url) {
    $clean = radioNormalizeUrl($url);
    if (!$clean) {
        return null;
    }

    if (preg_match('#^https?://#i', $clean)) {
        return $clean;
    }

    $base = rtrim((string) env('SITE_URL', SITE_URL), '/');
    return $base . '/' . ltrim($clean, '/');
}

/**
 * Retourne l'URL de stream configuree (DB > env).
 */
function radioGetConfiguredStreamUrl($dbStreamUrl = null) {
    $envUrl = radioMakeAbsoluteUrl(env('RADIO_STREAM_PUBLIC_URL', ''));
    $preferEnv = radioEnvBool('RADIO_ENGINE_PREFER_STREAM', true);

    if ($preferEnv && $envUrl) {
        return $envUrl;
    }

    $dbUrl = radioMakeAbsoluteUrl($dbStreamUrl);
    if ($dbUrl) {
        return $dbUrl;
    }

    if ($envUrl) {
        return $envUrl;
    }

    return null;
}

/**
 * URL publique utilisee par l'application.
 */
function radioGetPublicStreamUrl($dbStreamUrl = null) {
    $configured = radioGetConfiguredStreamUrl($dbStreamUrl);
    if ($configured) {
        return $configured;
    }

    $fallback = radioMakeAbsoluteUrl(env('RADIO_STREAM_FALLBACK_URL', '/api/radio/stream'));
    if ($fallback) {
        return $fallback;
    }

    return rtrim((string) env('SITE_URL', SITE_URL), '/') . '/api/radio/stream';
}

/**
 * Parse un titre "Artist - Track" si possible.
 */
function radioParseNowPlaying($source) {
    $artist = null;
    $title = null;

    if (!empty($source['artist']) || !empty($source['title'])) {
        $artist = $source['artist'] ?? null;
        $title = $source['title'] ?? null;
    } elseif (!empty($source['yp_currently_playing'])) {
        $raw = trim((string) $source['yp_currently_playing']);
        if (strpos($raw, ' - ') !== false) {
            [$artist, $title] = array_map('trim', explode(' - ', $raw, 2));
        } else {
            $title = $raw;
        }
    } elseif (!empty($source['server_description'])) {
        $title = trim((string) $source['server_description']);
    }

    if (!$artist && !$title) {
        return null;
    }

    return [
        'artist' => $artist ?: 'Tchadok Radio',
        'title' => $title ?: 'Direct Radio'
    ];
}

/**
 * Recupere les metadonnees Icecast si configure.
 */
function radioFetchEngineStatus() {
    if (!radioEnvBool('RADIO_ENGINE_ENABLED', false)) {
        return [
            'success' => false,
            'error' => 'RADIO_ENGINE_ENABLED=false',
            'engine' => 'icecast'
        ];
    }

    $statusUrl = radioMakeAbsoluteUrl(env('ICECAST_STATUS_URL', ''));
    if (!$statusUrl) {
        return [
            'success' => false,
            'error' => 'ICECAST_STATUS_URL non configure',
            'engine' => 'icecast'
        ];
    }

    $timeout = max(2, (int) env('RADIO_ENGINE_TIMEOUT', 5));
    $authUser = trim((string) env('ICECAST_ADMIN_USER', ''));
    $authPass = trim((string) env('ICECAST_ADMIN_PASSWORD', ''));
    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        if ($authUser !== '' && $authPass !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $authUser . ':' . $authPass);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return [
                'success' => false,
                'error' => $curlError ?: ('HTTP ' . $httpCode . ' sur status Icecast'),
                'engine' => 'icecast'
            ];
        }
    } else {
        $headers = [];
        if ($authUser !== '' && $authPass !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($authUser . ':' . $authPass);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers)
            ]
        ]);

        $response = @file_get_contents($statusUrl, false, $context);
        if ($response === false) {
            return [
                'success' => false,
                'error' => 'Impossible de recuperer le status Icecast',
                'engine' => 'icecast'
            ];
        }
    }

    $payload = json_decode($response, true);
    if (!is_array($payload) || !isset($payload['icestats'])) {
        return [
            'success' => false,
            'error' => 'Reponse status Icecast invalide',
            'engine' => 'icecast'
        ];
    }

    $sourcesRaw = $payload['icestats']['source'] ?? null;
    if (!$sourcesRaw) {
        return [
            'success' => false,
            'error' => 'Aucune source active',
            'engine' => 'icecast'
        ];
    }

    $sources = [];
    if (isset($sourcesRaw['listenurl']) || isset($sourcesRaw['mount'])) {
        $sources[] = $sourcesRaw;
    } elseif (is_array($sourcesRaw)) {
        // Icecast retourne un tableau de sources en cas de plusieurs mounts
        $isAssoc = array_keys($sourcesRaw) !== range(0, count($sourcesRaw) - 1);
        if ($isAssoc && (isset($sourcesRaw['listenurl']) || isset($sourcesRaw['mount']))) {
            $sources[] = $sourcesRaw;
        } else {
            foreach ($sourcesRaw as $item) {
                if (is_array($item)) {
                    $sources[] = $item;
                }
            }
        }
    }

    if (!$sources) {
        return [
            'success' => false,
            'error' => 'Aucune source exploitable',
            'engine' => 'icecast'
        ];
    }

    $preferredMount = trim((string) env('RADIO_ENGINE_MOUNT', '/tchadok.mp3'));
    $selected = $sources[0];

    foreach ($sources as $source) {
        $mount = (string) ($source['mount'] ?? '');
        $listenUrl = (string) ($source['listenurl'] ?? '');
        if ($preferredMount !== '' && ($mount === $preferredMount || strpos($listenUrl, $preferredMount) !== false)) {
            $selected = $source;
            break;
        }
    }

    $nowPlaying = radioParseNowPlaying($selected);
    $streamUrl = radioMakeAbsoluteUrl($selected['listenurl'] ?? null);

    return [
        'success' => true,
        'stream_url' => $streamUrl,
        'mount' => $selected['mount'] ?? $preferredMount,
        'listeners' => (int) ($selected['listeners'] ?? 0),
        'now_playing' => $nowPlaying,
        'status_url' => $statusUrl,
        'engine' => 'icecast'
    ];
}
